<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Handle authorization in controller or middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Complex nested JSON validation with conditional rules
     */
    public function rules(): array
    {
        return [
            // Student Information (Level 1 - nested)
            'student' => ['required', 'array'],
            'student.email' => ['required', 'email', 'max:255', 'unique:students,email'],
            'student.first_name' => ['required', 'string', 'min:2', 'max:100'],
            'student.last_name' => ['required', 'string', 'min:2', 'max:100'],
            'student.phone' => ['nullable', 'string', 'regex:/^[\+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,9}$/'],
            'student.date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            
            // Student Address (Level 2 - deeply nested)
            'student.address' => ['nullable', 'array'],
            'student.address.street' => ['required_with:student.address', 'string', 'max:255'],
            'student.address.city' => ['required_with:student.address', 'string', 'max:100'],
            'student.address.state' => ['nullable', 'string', 'max:100'],
            'student.address.country' => ['required_with:student.address', 'string', 'size:2'], // ISO code
            'student.address.postal_code' => ['required_with:student.address', 'string', 'max:20'],
            
            // Student Preferences (Level 2 - deeply nested)
            'student.preferences' => ['nullable', 'array'],
            'student.preferences.language' => ['nullable', 'string', Rule::in(['en', 'es', 'fr', 'de', 'pt'])],
            'student.preferences.timezone' => ['nullable', 'string', 'timezone'],
            'student.preferences.notifications' => ['nullable', 'array'],
            'student.preferences.notifications.email' => ['nullable', 'boolean'],
            'student.preferences.notifications.sms' => ['nullable', 'boolean'],
            'student.preferences.notifications.push' => ['nullable', 'boolean'],
            
            // Course Information (Level 1)
            'course' => ['required', 'array'],
            'course.code' => ['required', 'string', 'exists:courses,code'],
            'course.custom_fields' => ['nullable', 'array'],
            'course.custom_fields.*.key' => ['required_with:course.custom_fields', 'string', 'max:100'],
            'course.custom_fields.*.value' => ['nullable', 'string', 'max:500'],
            
            // Payment Information (Level 1 - nested)
            'payment' => ['required', 'array'],
            'payment.amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'payment.currency' => ['required', 'string', 'size:3', Rule::in(['USD', 'EUR', 'GBP', 'CAD'])],
            'payment.method' => ['required', 'string', Rule::in(['credit_card', 'debit_card', 'paypal', 'bank_transfer'])],
            'payment.coupon_code' => ['nullable', 'string', 'max:50'],
            
            // Payment Card Details (Level 2 - deeply nested, conditional)
            'payment.card' => ['required_if:payment.method,credit_card,debit_card', 'array'],
            'payment.card.number' => ['required_with:payment.card', 'string', 'regex:/^[0-9]{13,19}$/'],
            'payment.card.holder_name' => ['required_with:payment.card', 'string', 'max:100'],
            'payment.card.expiry_month' => ['required_with:payment.card', 'integer', 'between:1,12'],
            'payment.card.expiry_year' => ['required_with:payment.card', 'integer', 'min:' . date('Y'), 'max:' . (date('Y') + 10)],
            'payment.card.cvv' => ['required_with:payment.card', 'string', 'regex:/^[0-9]{3,4}$/'],
            
            // Payment Billing Address (Level 2 - deeply nested)
            'payment.billing_address' => ['nullable', 'array'],
            'payment.billing_address.same_as_student' => ['nullable', 'boolean'],
            'payment.billing_address.street' => ['required_unless:payment.billing_address.same_as_student,true', 'nullable', 'string', 'max:255'],
            'payment.billing_address.city' => ['required_unless:payment.billing_address.same_as_student,true', 'nullable', 'string', 'max:100'],
            'payment.billing_address.country' => ['required_unless:payment.billing_address.same_as_student,true', 'nullable', 'string', 'size:2'],
            'payment.billing_address.postal_code' => ['required_unless:payment.billing_address.same_as_student,true', 'nullable', 'string', 'max:20'],
            
            // Enrollment Configuration (Level 1 - nested)
            'enrollment' => ['nullable', 'array'],
            'enrollment.start_immediately' => ['nullable', 'boolean'],
            'enrollment.send_welcome_email' => ['nullable', 'boolean'],
            'enrollment.grant_certificate' => ['nullable', 'boolean'],
            'enrollment.notes' => ['nullable', 'string', 'max:1000'],
            
            // Modules & Lessons Access (Level 2 - deeply nested arrays)
            'enrollment.modules' => ['nullable', 'array'],
            'enrollment.modules.*.module_id' => ['required', 'integer', 'exists:modules,id'],
            'enrollment.modules.*.unlock_immediately' => ['nullable', 'boolean'],
            'enrollment.modules.*.unlock_date' => ['nullable', 'date', 'after:today'],
            'enrollment.modules.*.lessons' => ['nullable', 'array'],
            'enrollment.modules.*.lessons.*.lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'enrollment.modules.*.lessons.*.is_mandatory' => ['nullable', 'boolean'],
            
            // Metadata (Level 1)
            'metadata' => ['nullable', 'array'],
            'metadata.source' => ['nullable', 'string', 'max:100'],
            'metadata.referrer' => ['nullable', 'string', 'max:255'],
            'metadata.utm_campaign' => ['nullable', 'string', 'max:100'],
            'metadata.utm_medium' => ['nullable', 'string', 'max:100'],
            'metadata.utm_source' => ['nullable', 'string', 'max:100'],
            'metadata.ip_address' => ['nullable', 'ip'],
            'metadata.user_agent' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student.email.unique' => 'A student with this email address already exists in the system.',
            'course.code.exists' => 'The specified course code does not exist or is not available.',
            'payment.card.number.regex' => 'Please provide a valid card number (13-19 digits).',
            'payment.card.expiry_year.min' => 'The card expiration year cannot be in the past.',
            'student.address.country.size' => 'Country code must be a valid 2-letter ISO code.',
            'enrollment.modules.*.module_id.exists' => 'One or more module IDs are invalid.',
            'enrollment.modules.*.lessons.*.lesson_id.exists' => 'One or more lesson IDs are invalid.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'student.first_name' => 'first name',
            'student.last_name' => 'last name',
            'student.date_of_birth' => 'date of birth',
            'payment.card.holder_name' => 'cardholder name',
            'payment.card.cvv' => 'CVV',
        ];
    }

    /**
     * Configure the validator instance for additional validation logic.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Custom validation: Check if course has available slots
            if ($this->has('course.code')) {
                $course = \App\Models\Course::where('code', $this->input('course.code'))->first();
                
                if ($course && !$course->hasAvailableSlots()) {
                    $validator->errors()->add('course.code', 'This course has reached maximum enrollment capacity.');
                }
                
                if ($course && !$course->isPublished()) {
                    $validator->errors()->add('course.code', 'This course is not currently available for enrollment.');
                }
            }
            
            // Custom validation: Validate payment amount matches course price
            if ($this->has('course.code') && $this->has('payment.amount')) {
                $course = \App\Models\Course::where('code', $this->input('course.code'))->first();
                $paymentAmount = (float) $this->input('payment.amount');
                $couponCode = $this->input('payment.coupon_code');
                
                if ($course) {
                    $expectedAmount = $course->effective_price;
                    
                    // Apply coupon discount if provided (simplified - would use coupon service in production)
                    if ($couponCode) {
                        // This would check against a coupons table in production
                        $expectedAmount = $expectedAmount * 0.9; // Example: 10% discount
                    }
                    
                    if (abs($paymentAmount - $expectedAmount) > 0.01) {
                        $validator->errors()->add('payment.amount', 
                            "Payment amount does not match the course price. Expected: {$expectedAmount} {$this->input('payment.currency')}");
                    }
                }
            }
            
            // Custom validation: Ensure modules belong to the course
            if ($this->has('enrollment.modules') && $this->has('course.code')) {
                $course = \App\Models\Course::where('code', $this->input('course.code'))->first();
                
                if ($course) {
                    $courseModuleIds = $course->modules()->pluck('id')->toArray();
                    $requestedModuleIds = collect($this->input('enrollment.modules'))
                        ->pluck('module_id')
                        ->toArray();
                    
                    $invalidModules = array_diff($requestedModuleIds, $courseModuleIds);
                    
                    if (!empty($invalidModules)) {
                        $validator->errors()->add('enrollment.modules', 
                            'Some specified modules do not belong to this course.');
                    }
                }
            }
        });
    }
}
