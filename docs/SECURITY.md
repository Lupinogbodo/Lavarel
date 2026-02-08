# Security Considerations - Learning Platform

## Security Layers

```
┌────────────────────────────────────────────────────────┐
│  Layer 7: Application Security                         │
│  - Input Validation                                    │
│  - CSRF Protection                                     │
│  - XSS Prevention                                      │
│  - SQL Injection Prevention                            │
└────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────┐
│  Layer 6: API Security                                 │
│  - Authentication (Sanctum)                            │
│  - Authorization (Policies)                            │
│  - Rate Limiting                                       │
│  - API Key Management                                  │
└────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────┐
│  Layer 5: Data Security                                │
│  - Encryption at Rest                                  │
│  - Encryption in Transit (TLS 1.3)                     │
│  - PII Data Protection                                 │
│  - Secure Password Storage                             │
└────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────┐
│  Layer 4: Network Security                             │
│  - HTTPS Only                                          │
│  - CORS Configuration                                  │
│  - VPC / Private Networks                              │
│  - DDoS Protection                                     │
└────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────┐
│  Layer 3: Infrastructure Security                      │
│  - Firewall Rules                                      │
│  - Security Groups                                     │
│  - Server Hardening                                    │
│  - Regular Updates                                     │
└────────────────────────────────────────────────────────┘
```

## 1. Input Validation & Sanitization

### Laravel Form Requests

```php
// app/Http/Requests/EnrollStudentRequest.php
public function rules(): array
{
    return [
        'student.email' => ['required', 'email', 'max:255', 'unique:students,email'],
        'student.first_name' => ['required', 'string', 'min:2', 'max:100'],
        // Prevent script injection
        'student.notes' => ['nullable', 'string', 'max:1000'],
    ];
}

// Automatic escaping in Blade (prevents XSS)
{{ $student->first_name }}  // Safe: auto-escaped
{!! $student->first_name !!}  // Unsafe: not escaped (avoid)
```

### SQL Injection Prevention

```php
// ✅ SAFE: Eloquent ORM (parameterized queries)
Student::where('email', $email)->first();

// ✅ SAFE: Query Builder with bindings
DB::table('students')
    ->where('email', '=', $email)
    ->first();

// ❌ UNSAFE: Raw queries without bindings
DB::select("SELECT * FROM students WHERE email = '$email'");

// ✅ SAFE: Raw queries with bindings
DB::select("SELECT * FROM students WHERE email = ?", [$email]);
```

### XSS Prevention

```php
// In Controllers: Sanitize user input
use Illuminate\Support\Str;

$clean = Str::of($request->input('description'))
    ->stripTags()
    ->limit(500);

// Or use HTML Purifier for rich text
use HTMLPurifier;

$purifier = new HTMLPurifier();
$clean = $purifier->purify($request->input('content'));
```

## 2. Authentication & Authorization

### Laravel Sanctum Setup

```php
// config/sanctum.php
return [
    'expiration' => 60, // Token expiration in minutes
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
```

### Token Generation

```php
// Login
public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);
    
    if (!Auth::attempt($credentials)) {
        return response()->json([
            'message' => 'Invalid credentials'
        ], 401);
    }
    
    $user = Auth::user();
    
    // Create token with abilities
    $token = $user->createToken('api-token', ['enrollments:create', 'courses:view'])->plainTextToken;
    
    return response()->json([
        'token' => $token,
        'type' => 'Bearer',
    ]);
}
```

### Authorization Policies

```php
// app/Policies/EnrollmentPolicy.php
class EnrollmentPolicy
{
    public function view(User $user, Enrollment $enrollment): bool
    {
        // Users can only view their own enrollments
        return $user->id === $enrollment->student_id;
    }
    
    public function create(User $user): bool
    {
        // Check if user has active status
        return $user->status === 'active';
    }
}

// In Controller
public function show(Enrollment $enrollment)
{
    $this->authorize('view', $enrollment);
    
    return new EnrollmentResource($enrollment);
}
```

## 3. CSRF Protection

### API Tokens (Stateless)

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/enrollments', [EnrollmentController::class, 'store']);
});
// CSRF not needed for API routes with token authentication
```

### Session-Based (Stateful)

```php
// resources/views/enrollment-form.blade.php
<form method="POST" action="/enrollments">
    @csrf  <!-- Generates CSRF token field -->
    <!-- Form fields -->
</form>

// Middleware automatically validates CSRF token
```

### SPA Configuration

```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1')),

// Frontend must send cookies
fetch('/api/enrollments', {
    credentials: 'include',
    headers: {
        'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
    },
});
```

## 4. Rate Limiting

### Global Rate Limits

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        'throttle:60,1', // 60 requests per minute
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];
```

### Route-Specific Limits

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
    Route::post('/enrollments', [EnrollmentController::class, 'store']);
});
```

### Custom Rate Limiting

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot()
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });
    
    RateLimiter::for('enrollments', function (Request $request) {
        return [
            Limit::perMinute(10)->by($request->user()?->id),
            Limit::perDay(100)->by($request->user()?->id),
        ];
    });
}
```

### Rate Limit Headers

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
Retry-After: 60
```

## 5. Data Encryption

### Encryption at Rest

```php
// .env
APP_KEY=base64:GENERATED_32_BYTE_KEY

// Eloquent Encryption
use Illuminate\Database\Eloquent\Casts\Encrypted;

class Student extends Model
{
    protected $casts = [
        'phone' => Encrypted::class,
        'address' => Encrypted::class,
    ];
}
```

### Encryption in Transit

```nginx
# nginx.conf - Force HTTPS
server {
    listen 80;
    server_name learningplatform.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name learningplatform.com;
    
    ssl_certificate /path/to/cert.crt;
    ssl_certificate_key /path/to/cert.key;
    
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
```

### Password Hashing

```php
// Always use bcrypt/argon2
use Illuminate\Support\Facades\Hash;

// Hashing
$hashed = Hash::make($password);

// Verification
if (Hash::check($plainPassword, $hashedPassword)) {
    // Password matches
}
```

## 6. Secure Payment Handling

### PCI DSS Compliance

```php
// NEVER store card details directly
// ❌ BAD
Payment::create([
    'card_number' => $request->input('card_number'),
    'cvv' => $request->input('cvv'),
]);

// ✅ GOOD: Use payment gateway tokens
$stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

$paymentMethod = $stripe->paymentMethods->create([
    'type' => 'card',
    'card' => [
        'token' => $request->input('stripe_token'), // Tokenized on client
    ],
]);

Payment::create([
    'gateway_payment_method_id' => $paymentMethod->id,
    'last_four' => $request->input('last_four'),
]);
```

### Sensitive Data Logging

```php
// app/Http/Controllers/Api/EnrollmentController.php
Log::error('Enrollment failed', [
    'error' => $e->getMessage(),
    // ✅ GOOD: Exclude sensitive data
    'request_data' => $request->except(['payment.card']),
]);
```

## 7. CORS Configuration

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    'allowed_origins' => [
        'https://learningplatform.com',
        'https://app.learningplatform.com',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

## 8. Security Headers

```php
// app/Http/Middleware/SecurityHeaders.php
class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        
        return $response;
    }
}
```

## 9. File Upload Security

```php
public function uploadCertificate(Request $request)
{
    $request->validate([
        'certificate' => [
            'required',
            'file',
            'mimes:pdf,jpg,png',  // Whitelist allowed types
            'max:5120',  // 5MB max
        ],
    ]);
    
    $file = $request->file('certificate');
    
    // Generate secure filename
    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
    
    // Store in non-public directory
    $path = $file->storeAs('certificates', $filename, 'private');
    
    return response()->json(['path' => $path]);
}
```

## 10. Audit Logging

```php
// Log critical operations
Log::channel('audit')->info('Student enrolled', [
    'enrollment_id' => $enrollment->id,
    'student_id' => $student->id,
    'course_id' => $course->id,
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'timestamp' => now(),
]);
```

## 11. Environment Security

```bash
# .env - Never commit to version control
# Add to .gitignore
echo ".env" >> .gitignore

# Use different keys per environment
APP_KEY=base64:PRODUCTION_KEY_HERE

# Restrict debug mode
APP_DEBUG=false

# Use strong database passwords
DB_PASSWORD=STRONG_RANDOM_PASSWORD
```

## 12. Dependency Security

```bash
# Regular updates
composer update

# Security audit
composer audit

# Lock file
# Always commit composer.lock to ensure consistent versions
git add composer.lock
```

## Security Checklist

- [ ] All inputs validated and sanitized
- [ ] SQL injection prevented (using ORM/prepared statements)
- [ ] XSS prevented (auto-escaping enabled)
- [ ] CSRF protection enabled
- [ ] HTTPS enforced (TLS 1.2+)
- [ ] Strong password hashing (bcrypt/argon2)
- [ ] Sensitive data encrypted
- [ ] Rate limiting implemented
- [ ] Authentication required for protected routes
- [ ] Authorization policies enforced
- [ ] Security headers configured
- [ ] CORS properly configured
- [ ] File uploads validated
- [ ] Payment data tokenized (PCI DSS compliant)
- [ ] Error messages don't leak sensitive info
- [ ] Audit logging enabled
- [ ] Regular security updates
- [ ] Environment variables secured
- [ ] Database credentials rotated regularly
- [ ] API tokens expire appropriately

## Incident Response

### Steps if breach detected:

1. **Immediate**:
   - Isolate affected systems
   - Revoke compromised credentials
   - Enable maintenance mode

2. **Investigation**:
   - Review logs
   - Identify attack vector
   - Assess data exposure

3. **Remediation**:
   - Patch vulnerabilities
   - Reset passwords/tokens
   - Update dependencies

4. **Communication**:
   - Notify affected users
   - Report to authorities (if required)
   - Document incident

5. **Prevention**:
   - Implement additional controls
   - Review and update security policies
   - Conduct security training
