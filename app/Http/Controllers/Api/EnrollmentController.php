<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrollStudentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\{Student, Course, Enrollment, Payment, LessonProgress};
use App\Events\StudentEnrolled;
use App\Jobs\{SendWelcomeEmail, ProcessCourseAccess};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Cache, Log};
use Illuminate\Http\Response;

/**
 * Production-Level Enrollment Controller
 * 
 * Features:
 * - Handles deeply nested JSON requests
 * - Multiple database operations in transactions
 * - Complex input validation
 * - Event dispatching
 * - Queue job processing
 * - Structured error/success responses
 */
class EnrollmentController extends Controller
{
    /**
     * Display a listing of enrollments with caching
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'enrollments_' . ($request->user()->id ?? 'guest') . '_' . $request->get('page', 1);
        
        $enrollments = Cache::remember($cacheKey, 300, function () use ($request) {
            return Enrollment::with(['student', 'course', 'payment'])
                ->when($request->has('status'), function ($query) use ($request) {
                    $query->where('status', $request->status);
                })
                ->latest()
                ->paginate(15);
        });
        
        return response()->json([
            'success' => true,
            'data' => EnrollmentResource::collection($enrollments),
            'meta' => [
                'total' => $enrollments->total(),
                'per_page' => $enrollments->perPage(),
                'current_page' => $enrollments->currentPage(),
            ],
        ]);
    }

    /**
     * Display a specific enrollment
     */
    public function show(Enrollment $enrollment): JsonResponse
    {
        $enrollment->load(['student', 'course.modules.lessons', 'payment', 'lessonProgress']);
        
        return response()->json([
            'success' => true,
            'data' => new EnrollmentResource($enrollment),
        ]);
    }

    /**
     * Store a new enrollment with complex nested data
     * 
     * This production-level endpoint demonstrates:
     * 1. Deeply nested JSON handling (student, course, payment, modules, lessons)
     * 2. Multiple DB operations within a transaction
     * 3. Complex validation (handled by EnrollStudentRequest)
     * 4. Event dispatching (StudentEnrolled)
     * 5. Queue job dispatching (SendWelcomeEmail, ProcessCourseAccess)
     * 6. Structured success/error responses
     * 7. Comprehensive error handling with rollback
     * 8. Cache invalidation
     * 9. Logging and monitoring
     * 
     * @param EnrollStudentRequest $request Validated request with nested JSON
     * @return JsonResponse Structured response with enrollment data or errors
     */
    public function store(EnrollStudentRequest $request): JsonResponse
    {
        // Start performance monitoring
        $startTime = microtime(true);
        
        try {
            // Execute all database operations in a transaction
            // All operations will be rolled back automatically if any exception occurs
            $result = DB::transaction(function () use ($request) {
                
                // ============================================
                // STEP 1: Create or Update Student Record
                // ============================================
                $studentData = $request->input('student');
                
                // Format address if provided
                $address = null;
                if (isset($studentData['address'])) {
                    $address = implode(', ', [
                        $studentData['address']['street'],
                        $studentData['address']['city'],
                        $studentData['address']['state'] ?? '',
                        $studentData['address']['country'],
                        $studentData['address']['postal_code'],
                    ]);
                }
                
                $student = Student::create([
                    'email' => $studentData['email'],
                    'first_name' => $studentData['first_name'],
                    'last_name' => $studentData['last_name'],
                    'phone' => $studentData['phone'] ?? null,
                    'date_of_birth' => $studentData['date_of_birth'] ?? null,
                    'address' => $address,
                    'city' => $studentData['address']['city'] ?? null,
                    'country' => $studentData['address']['country'] ?? null,
                    'postal_code' => $studentData['address']['postal_code'] ?? null,
                    'status' => 'active',
                    'preferences' => $studentData['preferences'] ?? null,
                ]);
                
                Log::info('Student created', ['student_id' => $student->id, 'email' => $student->email]);
                
                // ============================================
                // STEP 2: Retrieve and Validate Course
                // ============================================
                $courseCode = $request->input('course.code');
                $course = Course::where('code', $courseCode)
                    ->lockForUpdate() // Prevent race conditions on enrollment count
                    ->firstOrFail();
                
                // Double-check availability (defense in depth)
                if (!$course->hasAvailableSlots()) {
                    throw new \Exception('Course enrollment is full');
                }
                
                if (!$course->isPublished()) {
                    throw new \Exception('Course is not available');
                }
                
                // ============================================
                // STEP 3: Process Payment Information
                // ============================================
                $paymentData = $request->input('payment');
                
                // Calculate final amount with discount
                $baseAmount = $course->effective_price;
                $discountAmount = 0;
                
                if (!empty($paymentData['coupon_code'])) {
                    // In production, this would query a coupons table and validate
                    $discountAmount = $baseAmount * 0.10; // Example: 10% discount
                }
                
                $finalAmount = $baseAmount - $discountAmount;
                
                // Create payment record
                $payment = new Payment([
                    'transaction_id' => Payment::generateTransactionId(),
                    'amount' => $finalAmount,
                    'currency' => $paymentData['currency'],
                    'status' => 'pending',
                    'payment_method' => $paymentData['method'],
                    'payment_gateway' => 'stripe', // Example gateway
                    'metadata' => [
                        'card_last_four' => isset($paymentData['card']['number']) 
                            ? substr($paymentData['card']['number'], -4) 
                            : null,
                        'billing_address' => $paymentData['billing_address'] ?? null,
                        'ip_address' => $request->input('metadata.ip_address'),
                        'user_agent' => $request->input('metadata.user_agent'),
                    ],
                ]);
                
                // Simulate payment processing (in production, call payment gateway API)
                // For demonstration, we'll mark as completed immediately
                $paymentProcessed = $this->processPayment($payment, $paymentData);
                
                if (!$paymentProcessed) {
                    throw new \Exception('Payment processing failed');
                }
                
                $payment->status = 'completed';
                $payment->paid_at = now();
                
                Log::info('Payment processed', ['transaction_id' => $payment->transaction_id]);
                
                // ============================================
                // STEP 4: Create Enrollment Record
                // ============================================
                $enrollmentConfig = $request->input('enrollment', []);
                
                $enrollment = new Enrollment([
                    'enrollment_number' => Enrollment::generateEnrollmentNumber(),
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'status' => 'pending',
                    'amount_paid' => $finalAmount,
                    'discount_applied' => $discountAmount,
                    'coupon_code' => $paymentData['coupon_code'] ?? null,
                    'enrolled_at' => now(),
                    'custom_fields' => $request->input('course.custom_fields'),
                    'notes' => $enrollmentConfig['notes'] ?? null,
                ]);
                
                // Start immediately if configured
                if ($enrollmentConfig['start_immediately'] ?? true) {
                    $enrollment->status = 'active';
                    $enrollment->started_at = now();
                    
                    // Set expiration date (e.g., 1 year from start)
                    $enrollment->expires_at = now()->addYear();
                }
                
                $enrollment->save();
                
                // Associate payment with enrollment
                $payment->enrollment_id = $enrollment->id;
                $payment->save();
                
                Log::info('Enrollment created', [
                    'enrollment_id' => $enrollment->id,
                    'enrollment_number' => $enrollment->enrollment_number,
                ]);
                
                // ============================================
                // STEP 5: Initialize Lesson Progress Records
                // ============================================
                if (!empty($enrollmentConfig['modules'])) {
                    $this->initializeLessonProgress($enrollment, $enrollmentConfig['modules']);
                }
                
                // ============================================
                // STEP 6: Update Course Enrollment Count
                // ============================================
                $course->incrementEnrolledCount();
                
                // ============================================
                // STEP 7: Store Metadata for Analytics
                // ============================================
                if ($request->has('metadata')) {
                    // In production, store in separate analytics table or service
                    Log::info('Enrollment metadata', [
                        'enrollment_id' => $enrollment->id,
                        'metadata' => $request->input('metadata'),
                    ]);
                }
                
                // Return all created records
                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                    'course' => $course,
                    'payment' => $payment,
                ];
            }, 5); // Transaction with 5 retry attempts on deadlock
            
            // ========================================
            // POST-TRANSACTION OPERATIONS
            // ========================================
            
            // Reload relationships for complete response
            $enrollment = $result['enrollment'];
            $enrollment->load(['student', 'course', 'payment']);
            
            // Invalidate relevant caches
            $this->invalidateEnrollmentCaches($enrollment);
            
            // Dispatch event (listeners will be executed synchronously or queued)
            event(new StudentEnrolled($enrollment));
            
            // Dispatch queued jobs (asynchronous processing)
            $enrollmentConfig = $request->input('enrollment', []);
            
            if ($enrollmentConfig['send_welcome_email'] ?? true) {
                SendWelcomeEmail::dispatch($enrollment)
                    ->onQueue('emails')
                    ->delay(now()->addSeconds(5));
            }
            
            ProcessCourseAccess::dispatch($enrollment)
                ->onQueue('default')
                ->delay(now()->addSeconds(10));
            
            // Log successful enrollment
            Log::info('Enrollment completed successfully', [
                'enrollment_number' => $enrollment->enrollment_number,
                'student_email' => $enrollment->student->email,
                'course_code' => $enrollment->course->code,
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            
            // Return structured success response
            return response()->json([
                'success' => true,
                'message' => 'Student enrolled successfully',
                'data' => new EnrollmentResource($enrollment),
                'meta' => [
                    'enrollment_number' => $enrollment->enrollment_number,
                    'transaction_id' => $enrollment->payment->transaction_id,
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
            ], Response::HTTP_CREATED);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Handle specific not found errors
            Log::warning('Resource not found during enrollment', [
                'error' => $e->getMessage(),
                'request_data' => $request->except(['payment.card']),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'errors' => [
                    'general' => ['The requested course or related resource was not found.']
                ],
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors (from within transaction)
            Log::warning('Validation error during enrollment', [
                'errors' => $e->errors(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_ERROR',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Throwable $e) {
            // Handle any other errors - transaction will auto-rollback
            // Log the error with full context for debugging
            Log::error('Enrollment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['payment.card']), // Exclude sensitive data
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            
            // Return structured error response
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your enrollment',
                'errors' => [
                    'general' => [config('app.debug') ? $e->getMessage() : 'Internal server error']
                ],
                'error_code' => 'ENROLLMENT_FAILED',
                'rollback' => 'All changes have been rolled back',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Initialize lesson progress records for specified modules
     */
    private function initializeLessonProgress(Enrollment $enrollment, array $modules): void
    {
        foreach ($modules as $moduleConfig) {
            $moduleId = $moduleConfig['module_id'];
            $lessons = $moduleConfig['lessons'] ?? [];
            
            if (!empty($lessons)) {
                foreach ($lessons as $lessonConfig) {
                    LessonProgress::create([
                        'enrollment_id' => $enrollment->id,
                        'lesson_id' => $lessonConfig['lesson_id'],
                        'is_completed' => false,
                        'data' => [
                            'is_mandatory' => $lessonConfig['is_mandatory'] ?? false,
                            'unlock_immediately' => $moduleConfig['unlock_immediately'] ?? true,
                            'unlock_date' => $moduleConfig['unlock_date'] ?? null,
                        ],
                    ]);
                }
            }
        }
        
        Log::info('Lesson progress initialized', [
            'enrollment_id' => $enrollment->id,
            'modules_count' => count($modules),
        ]);
    }

    /**
     * Process payment through payment gateway
     * 
     * In production, this would integrate with Stripe, PayPal, etc.
     */
    private function processPayment(Payment $payment, array $paymentData): bool
    {
        // Simulated payment processing
        // In production, call payment gateway API:
        // 
        // $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        // $charge = $stripe->charges->create([
        //     'amount' => $payment->amount * 100,
        //     'currency' => strtolower($payment->currency),
        //     'source' => $paymentData['card']['token'],
        //     'description' => 'Course enrollment',
        // ]);
        // 
        // $payment->gateway_transaction_id = $charge->id;
        
        // For demonstration, simulate successful payment
        $payment->gateway_transaction_id = 'ch_' . uniqid();
        
        return true;
    }

    /**
     * Invalidate relevant caches after enrollment
     */
    private function invalidateEnrollmentCaches(Enrollment $enrollment): void
    {
        Cache::tags(['enrollments', 'courses', 'students'])->flush();
        
        // Clear specific cache keys
        Cache::forget("course_{$enrollment->course_id}_enrollments");
        Cache::forget("student_{$enrollment->student_id}_enrollments");
    }
}
