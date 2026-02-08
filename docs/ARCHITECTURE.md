# System Architecture - Learning Platform

## Overview

The Learning Platform is a scalable, production-ready web application built with Laravel (backend) and vanilla JavaScript (frontend) that demonstrates enterprise-level architecture patterns.

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENT LAYER                                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌──────────────────┐         ┌──────────────────┐                 │
│  │  Web Browser     │         │  Mobile App      │                 │
│  │  (Vanilla JS)    │         │  (React Native)  │                 │
│  └────────┬─────────┘         └────────┬─────────┘                 │
│           │                             │                            │
│           └─────────────┬───────────────┘                            │
│                         │                                            │
└─────────────────────────┼────────────────────────────────────────────┘
                          │ HTTPS
                          │
┌─────────────────────────▼────────────────────────────────────────────┐
│                      LOAD BALANCER                                    │
│                  (Nginx / AWS ALB)                                    │
└─────────────────────────┬────────────────────────────────────────────┘
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
┌───────▼──────┐  ┌───────▼──────┐  ┌───────▼──────┐
│  App Server  │  │  App Server  │  │  App Server  │
│  (Laravel)   │  │  (Laravel)   │  │  (Laravel)   │
│              │  │              │  │              │
│  - API       │  │  - API       │  │  - API       │
│  - Business  │  │  - Business  │  │  - Business  │
│  - Logic     │  │  - Logic     │  │  - Logic     │
└───────┬──────┘  └───────┬──────┘  └───────┬──────┘
        │                 │                 │
        └─────────────────┼─────────────────┘
                          │
        ┌─────────────────┼─────────────────────────────┐
        │                 │                             │
┌───────▼─────────┐ ┌─────▼──────────┐  ┌─────────▼────────────┐
│                 │ │                │  │                      │
│  MySQL/Postgres │ │  Redis Cache   │  │  Redis Queue         │
│  (Primary DB)   │ │  - Sessions    │  │  - Jobs              │
│                 │ │  - API Cache   │  │  - Events            │
│  - Students     │ │  - Query Cache │  │                      │
│  - Courses      │ │                │  │  Queue Workers:      │
│  - Enrollments  │ │                │  │  - SendWelcomeEmail  │
│  - Payments     │ │                │  │  - ProcessAccess     │
│  - Modules      │ │                │  │  - NotifyInstructors │
│  - Lessons      │ │                │  │                      │
│                 │ │                │  │                      │
└─────────────────┘ └────────────────┘  └──────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                    EXTERNAL SERVICES                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │   Stripe     │  │   SendGrid   │  │   AWS S3     │              │
│  │   Payment    │  │   Email      │  │   Storage    │              │
│  └──────────────┘  └──────────────┘  └──────────────┘              │
│                                                                       │
└───────────────────────────────────────────────────────────────────────┘
```

## Component Architecture

### 1. Frontend Layer

**Technology**: Vanilla JavaScript (ES6+)

**Components**:
- Real-time Search UI
- Course Catalog
- Enrollment Forms
- Student Dashboard

**Features**:
- Debounced API calls
- Keyboard navigation
- Dynamic content rendering
- Error handling
- Responsive design

### 2. Application Layer

**Technology**: Laravel 10+ (PHP 8.1+)

**Structure**:
```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── EnrollmentController.php
│   │   └── SearchController.php
│   ├── Requests/
│   │   └── EnrollStudentRequest.php
│   ├── Resources/
│   │   └── EnrollmentResource.php
│   └── Middleware/
├── Models/
│   ├── Student.php
│   ├── Course.php
│   ├── Enrollment.php
│   └── Payment.php
├── Events/
│   └── StudentEnrolled.php
├── Jobs/
│   ├── SendWelcomeEmail.php
│   └── ProcessCourseAccess.php
├── Listeners/
│   └── NotifyInstructors.php
└── Services/
    └── EnrollmentService.php
```

### 3. Data Layer

**Primary Database**: MySQL 8.0+ / PostgreSQL 14+

**Key Tables**:
- `students` - Student information
- `courses` - Course catalog
- `modules` - Course modules
- `lessons` - Individual lessons
- `enrollments` - Enrollment records
- `payments` - Payment transactions
- `lesson_progress` - Progress tracking

**Relationships**:
- One student has many enrollments
- One course has many enrollments
- One course has many modules
- One module has many lessons
- One enrollment has one payment
- One enrollment has many lesson progress records

### 4. Cache Layer

**Technology**: Redis 6.0+

**Cache Strategies**:

1. **Query Result Caching**
   - Course search results: 5 minutes
   - Enrollment lists: 5 minutes
   - Course details: 15 minutes

2. **Session Storage**
   - User sessions
   - Authentication tokens

3. **Application State**
   - Feature flags
   - Configuration values

### 5. Queue Layer

**Technology**: Redis Queue

**Queue Types**:

1. **High Priority** (`emails` queue)
   - Welcome emails
   - Password resets
   - Critical notifications

2. **Default Priority** (`default` queue)
   - Course access setup
   - Progress calculations
   - Analytics updates

3. **Low Priority** (`notifications` queue)
   - Instructor notifications
   - Reminder emails
   - Batch operations

## Data Flow - Enrollment Process

```
1. Client Request
   ↓
2. API Gateway (Nginx)
   ↓
3. Laravel Router → EnrollmentController
   ↓
4. Validation (EnrollStudentRequest)
   ↓
5. Transaction Start
   ├─→ Create Student
   ├─→ Lock Course (prevent race conditions)
   ├─→ Process Payment
   ├─→ Create Enrollment
   ├─→ Initialize Progress
   └─→ Update Course Stats
   ↓
6. Transaction Commit (or Rollback on Error)
   ↓
7. Dispatch Event (StudentEnrolled)
   ↓
8. Queue Jobs
   ├─→ SendWelcomeEmail (queue: emails)
   └─→ ProcessCourseAccess (queue: default)
   ↓
9. Invalidate Cache
   ↓
10. Return Response (201 Created)
```

## Scalability Considerations

### Horizontal Scaling

1. **Application Servers**
   - Stateless design allows multiple instances
   - Load balanced with round-robin or least connections
   - Auto-scaling based on CPU/memory metrics

2. **Database**
   - Read replicas for query distribution
   - Write operations to primary only
   - Connection pooling (pgBouncer/ProxySQL)

3. **Cache**
   - Redis Cluster for distributed caching
   - Cache aside pattern
   - Consistent hashing for key distribution

4. **Queue Workers**
   - Multiple workers per queue
   - Horizontal Pods Autoscaler in Kubernetes
   - Dead letter queue for failed jobs

### Vertical Scaling

1. **Database Optimization**
   - Indexed columns (emails, enrollment numbers, status)
   - Full-text indexes on searchable fields
   - Partitioning for large tables

2. **Cache Optimization**
   - Increase Redis memory
   - Configure eviction policies (LRU)
   - Persistent storage for critical data

## High Availability

1. **Database**
   - Primary-replica setup
   - Automatic failover
   - Regular backups (daily full, hourly incremental)

2. **Application**
   - Multi-AZ deployment
   - Health checks
   - Circuit breakers

3. **Cache**
   - Redis Sentinel for automatic failover
   - Replica sets

## Security Architecture

1. **Network Level**
   - VPC with private subnets
   - Security groups
   - DDoS protection

2. **Application Level**
   - HTTPS only (TLS 1.3)
   - JWT/Sanctum authentication
   - CSRF protection
   - Rate limiting
   - Input validation

3. **Data Level**
   - Encrypted at rest
   - Encrypted in transit
   - PII data hashing
   - SQL injection prevention (parameterized queries)

## Monitoring & Observability

1. **Application Metrics**
   - Request rate
   - Response time
   - Error rate
   - Queue depth

2. **Infrastructure Metrics**
   - CPU/Memory usage
   - Disk I/O
   - Network throughput

3. **Business Metrics**
   - Enrollments per hour
   - Success/failure ratio
   - Revenue tracking

4. **Logging**
   - Structured logging (JSON)
   - Centralized log aggregation (ELK Stack)
   - Log levels (DEBUG, INFO, WARNING, ERROR, CRITICAL)

## Technology Stack Summary

| Layer | Technology | Purpose |
|-------|------------|---------|
| Frontend | Vanilla JavaScript ES6+ | User interface |
| Backend API | Laravel 10+ | Business logic |
| Primary Database | MySQL 8.0+ / PostgreSQL 14+ | Data persistence |
| Cache | Redis 6.0+ | Performance optimization |
| Queue | Redis Queue | Async processing |
| Web Server | Nginx | Reverse proxy, load balancing |
| Search | Full-text indexes | Course search |
| Payments | Stripe API | Payment processing |
| Email | SendGrid / SMTP | Email delivery |
| Storage | AWS S3 | File storage |
| Monitoring | Laravel Telescope / New Relic | Performance monitoring |
