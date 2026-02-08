# API Documentation - Student Enrollment Endpoint

## Endpoint Overview

**POST** `/api/v1/enrollments`

Production-level API endpoint for enrolling students in courses with complex nested JSON handling.

## Authentication

```http
Authorization: Bearer {your_access_token}
```

## Request Headers

```http
Content-Type: application/json
Accept: application/json
```

## Example Request Payload

```json
{
  "student": {
    "email": "john.doe@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+1-555-123-4567",
    "date_of_birth": "1995-03-15",
    "address": {
      "street": "123 Main Street",
      "city": "San Francisco",
      "state": "CA",
      "country": "US",
      "postal_code": "94102"
    },
    "preferences": {
      "language": "en",
      "timezone": "America/Los_Angeles",
      "notifications": {
        "email": true,
        "sms": false,
        "push": true
      }
    }
  },
  "course": {
    "code": "WEB-301",
    "custom_fields": [
      {
        "key": "referral_source",
        "value": "linkedin_ad"
      },
      {
        "key": "company_name",
        "value": "Tech Corp Inc"
      }
    ]
  },
  "payment": {
    "amount": 299.00,
    "currency": "USD",
    "method": "credit_card",
    "coupon_code": "SUMMER2024",
    "card": {
      "number": "4242424242424242",
      "holder_name": "John Doe",
      "expiry_month": 12,
      "expiry_year": 2026,
      "cvv": "123"
    },
    "billing_address": {
      "same_as_student": false,
      "street": "456 Business Ave",
      "city": "San Francisco",
      "country": "US",
      "postal_code": "94103"
    }
  },
  "enrollment": {
    "start_immediately": true,
    "send_welcome_email": true,
    "grant_certificate": true,
    "notes": "Corporate training enrollment",
    "modules": [
      {
        "module_id": 1,
        "unlock_immediately": true,
        "lessons": [
          {
            "lesson_id": 1,
            "is_mandatory": true
          },
          {
            "lesson_id": 2,
            "is_mandatory": true
          }
        ]
      },
      {
        "module_id": 2,
        "unlock_immediately": false,
        "unlock_date": "2024-03-01",
        "lessons": [
          {
            "lesson_id": 3,
            "is_mandatory": false
          }
        ]
      }
    ]
  },
  "metadata": {
    "source": "web",
    "referrer": "https://google.com",
    "utm_campaign": "summer_courses",
    "utm_medium": "social",
    "utm_source": "linkedin",
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0..."
  }
}
```

## Validation Rules

### Student Object

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `email` | string | Yes | Valid email, unique, max 255 chars |
| `first_name` | string | Yes | Min 2, max 100 chars |
| `last_name` | string | Yes | Min 2, max 100 chars |
| `phone` | string | No | Valid international format |
| `date_of_birth` | date | No | Before today, after 1900-01-01 |

### Student Address (Nested)

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `street` | string | Conditional | Required if address provided |
| `city` | string | Conditional | Required if address provided |
| `country` | string | Conditional | 2-letter ISO code |
| `postal_code` | string | Conditional | Max 20 chars |

### Course Object

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `code` | string | Yes | Must exist in courses table |

### Payment Object

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `amount` | decimal | Yes | Min 0, max 999999.99 |
| `currency` | string | Yes | USD, EUR, GBP, CAD |
| `method` | string | Yes | credit_card, debit_card, paypal, bank_transfer |

### Payment Card (Nested, Conditional)

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `number` | string | Conditional | 13-19 digits, required for card payments |
| `holder_name` | string | Conditional | Max 100 chars |
| `expiry_month` | integer | Conditional | Between 1-12 |
| `expiry_year` | integer | Conditional | Current year or later |
| `cvv` | string | Conditional | 3-4 digits |

## Success Response (201 Created)

```json
{
  "success": true,
  "message": "Student enrolled successfully",
  "data": {
    "id": 123,
    "enrollment_number": "ENR-2024-A8F3B2D1",
    "status": "active",
    "student": {
      "id": 456,
      "name": "John Doe",
      "email": "john.doe@example.com",
      "phone": "+1-555-123-4567"
    },
    "course": {
      "id": 789,
      "code": "WEB-301",
      "title": "Advanced Web Development",
      "level": "advanced",
      "duration_hours": 40
    },
    "payment": {
      "transaction_id": "TXN-20240206-F8A3B2D1E5",
      "amount": 299.00,
      "discount": 30.00,
      "coupon_code": "SUMMER2024",
      "status": "completed",
      "paid_at": "2024-02-06T10:30:45Z"
    },
    "progress": {
      "percentage": 0,
      "enrolled_at": "2024-02-06T10:30:45Z",
      "started_at": "2024-02-06T10:30:45Z",
      "completed_at": null,
      "expires_at": "2025-02-06T10:30:45Z"
    },
    "created_at": "2024-02-06T10:30:45Z",
    "updated_at": "2024-02-06T10:30:45Z"
  },
  "meta": {
    "enrollment_number": "ENR-2024-A8F3B2D1",
    "transaction_id": "TXN-20240206-F8A3B2D1E5",
    "processing_time_ms": 234.56
  }
}
```

## Error Responses

### Validation Error (422 Unprocessable Entity)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "student.email": [
      "A student with this email address already exists in the system."
    ],
    "payment.amount": [
      "Payment amount does not match the course price. Expected: 299.00 USD"
    ],
    "course.code": [
      "The specified course code does not exist or is not available."
    ]
  },
  "error_code": "VALIDATION_ERROR"
}
```

### Resource Not Found (404)

```json
{
  "success": false,
  "message": "Resource not found",
  "errors": {
    "general": [
      "The requested course or related resource was not found."
    ]
  },
  "error_code": "RESOURCE_NOT_FOUND"
}
```

### Server Error (500)

```json
{
  "success": false,
  "message": "An error occurred while processing your enrollment",
  "errors": {
    "general": [
      "Internal server error"
    ]
  },
  "error_code": "ENROLLMENT_FAILED",
  "rollback": "All changes have been rolled back"
}
```

## Transaction Flow

1. **Validation** - All nested data is validated
2. **Student Creation** - New student record created
3. **Course Retrieval** - Course locked for update (prevents race conditions)
4. **Payment Processing** - Payment validated and processed
5. **Enrollment Creation** - Enrollment record created
6. **Progress Initialization** - Lesson progress records created
7. **Course Update** - Enrollment count incremented
8. **Commit** - All changes committed atomically

**If any step fails, all changes are rolled back automatically.**

## Post-Transaction Operations

After successful transaction commit:

1. **Event Dispatch** - `StudentEnrolled` event fired
2. **Queue Jobs** - Background jobs dispatched:
   - `SendWelcomeEmail` - Sends welcome email to student
   - `ProcessCourseAccess` - Sets up course access and materials
3. **Cache Invalidation** - Relevant caches cleared
4. **Logging** - Success logged with context

## Rate Limiting

- General API: 60 requests per minute
- Enrollment endpoint: 10 requests per minute (more restrictive)

## Testing with cURL

```bash
curl -X POST http://localhost:8000/api/v1/enrollments \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d @example-enrollment.json
```

## Notes

- Card numbers are never logged or stored in plain text
- Payment processing is simulated (integrate with Stripe/PayPal in production)
- All monetary amounts use decimal precision to avoid floating-point errors
- Timestamps use ISO 8601 format
- All database operations are wrapped in a transaction with retry logic
