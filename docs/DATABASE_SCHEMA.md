# Database Schema - Learning Platform

## Entity Relationship Diagram

```
┌──────────────────┐         ┌──────────────────┐
│    students      │         │     courses      │
├──────────────────┤         ├──────────────────┤
│ id (PK)          │         │ id (PK)          │
│ email (UQ)       │         │ code (UQ)        │
│ first_name       │         │ title            │
│ last_name        │         │ description      │
│ phone            │         │ slug (UQ)        │
│ date_of_birth    │         │ price            │
│ address          │         │ discount_price   │
│ city             │         │ level            │
│ country          │         │ status           │
│ postal_code      │         │ duration_hours   │
│ status           │         │ max_students     │
│ preferences(JSON)│         │ enrolled_count   │
│ created_at       │         │ start_date       │
│ updated_at       │         │ end_date         │
│ deleted_at       │         │ tags (JSON)      │
└────────┬─────────┘         │ prerequisites    │
         │                   │ created_at       │
         │                   │ updated_at       │
         │                   │ deleted_at       │
         │                   └────────┬─────────┘
         │                            │
         │                            │ 1:N
         │                            │
         │                   ┌────────▼─────────┐
         │                   │     modules      │
         │                   ├──────────────────┤
         │                   │ id (PK)          │
         │                   │ course_id (FK)   │
         │                   │ title            │
         │                   │ description      │
         │                   │ order            │
         │                   │ duration_minutes │
         │                   │ is_published     │
         │                   │ created_at       │
         │                   │ updated_at       │
         │                   └────────┬─────────┘
         │                            │
         │                            │ 1:N
         │                            │
         │                   ┌────────▼─────────┐
         │                   │     lessons      │
         │                   ├──────────────────┤
         │                   │ id (PK)          │
         │                   │ module_id (FK)   │
         │                   │ title            │
         │                   │ description      │
         │                   │ content          │
         │                   │ type             │
         │                   │ video_url        │
         │                   │ duration_minutes │
         │                   │ order            │
         │                   │ is_preview       │
         │                   │ is_published     │
         │                   │ resources (JSON) │
         │                   │ created_at       │
         │                   │ updated_at       │
         │                   └──────────────────┘
         │
         │ 1:N
         │
┌────────▼─────────┐
│   enrollments    │
├──────────────────┤
│ id (PK)          │
│ enrollment_number│◄──┐
│ student_id (FK)  │   │
│ course_id (FK)   │   │
│ status           │   │
│ amount_paid      │   │
│ discount_applied │   │
│ coupon_code      │   │
│ enrolled_at      │   │
│ started_at       │   │
│ completed_at     │   │
│ expires_at       │   │
│ progress_%       │   │
│ custom_fields    │   │
│ notes            │   │
│ created_at       │   │
│ updated_at       │   │
│ deleted_at       │   │
└────────┬─────────┘   │
         │             │ 1:1
         │             │
         │ 1:1  ┌──────┴──────────┐
         │      │    payments     │
         │      ├─────────────────┤
         │      │ id (PK)         │
         │      │ transaction_id  │
         │      │ enrollment_id   │
         │      │ amount          │
         │      │ currency        │
         │      │ status          │
         │      │ payment_method  │
         │      │ payment_gateway │
         │      │ gateway_txn_id  │
         │      │ metadata (JSON) │
         │      │ paid_at         │
         │      │ created_at      │
         │      │ updated_at      │
         │      └─────────────────┘
         │
         │ 1:N
         │
┌────────▼──────────┐
│ lesson_progress   │
├───────────────────┤
│ id (PK)           │
│ enrollment_id(FK) │
│ lesson_id (FK)    │
│ is_completed      │
│ time_spent_min    │
│ attempts          │
│ score             │
│ started_at        │
│ completed_at      │
│ data (JSON)       │
│ created_at        │
│ updated_at        │
└───────────────────┘
```

## Table Definitions

### students

**Purpose**: Store student/user information

| Column | Type | Constraints | Index | Description |
|--------|------|-------------|-------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | PRIMARY | Student ID |
| email | VARCHAR(255) | UNIQUE, NOT NULL | INDEX | Student email |
| first_name | VARCHAR(100) | NOT NULL | - | First name |
| last_name | VARCHAR(100) | NOT NULL | - | Last name |
| phone | VARCHAR(20) | NULLABLE | - | Phone number |
| date_of_birth | DATE | NULLABLE | - | Date of birth |
| address | TEXT | NULLABLE | - | Full address |
| city | VARCHAR(100) | NULLABLE | - | City |
| country | VARCHAR(2) | NULLABLE | - | Country code (ISO) |
| postal_code | VARCHAR(20) | NULLABLE | - | Postal/ZIP code |
| status | ENUM | NOT NULL, DEFAULT 'active' | INDEX | active, inactive, suspended |
| preferences | JSON | NULLABLE | - | User preferences |
| created_at | TIMESTAMP | NOT NULL | INDEX | Creation timestamp |
| updated_at | TIMESTAMP | NOT NULL | - | Last update timestamp |
| deleted_at | TIMESTAMP | NULLABLE | - | Soft delete timestamp |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (email)
- INDEX (email, status)
- INDEX (created_at)

### courses

**Purpose**: Store course information and catalog

| Column | Type | Constraints | Index | Description |
|--------|------|-------------|-------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | PRIMARY | Course ID |
| code | VARCHAR(50) | UNIQUE, NOT NULL | INDEX | Course code |
| title | VARCHAR(255) | NOT NULL | FULLTEXT | Course title |
| description | TEXT | NOT NULL | FULLTEXT | Course description |
| slug | VARCHAR(255) | UNIQUE, NOT NULL | - | URL-friendly slug |
| price | DECIMAL(10,2) | NOT NULL | - | Base price |
| discount_price | DECIMAL(10,2) | NULLABLE | - | Discounted price |
| level | ENUM | NOT NULL | INDEX | beginner, intermediate, advanced |
| status | ENUM | NOT NULL, DEFAULT 'draft' | INDEX | draft, published, archived |
| duration_hours | INT | NOT NULL, DEFAULT 0 | - | Total course duration |
| max_students | INT | NULLABLE | - | Maximum enrollment |
| enrolled_count | INT | NOT NULL, DEFAULT 0 | - | Current enrollment count |
| start_date | DATE | NULLABLE | - | Course start date |
| end_date | DATE | NULLABLE | - | Course end date |
| tags | JSON | NULLABLE | - | Course tags |
| prerequisites | JSON | NULLABLE | - | Prerequisites |
| created_at | TIMESTAMP | NOT NULL | - | Creation timestamp |
| updated_at | TIMESTAMP | NOT NULL | - | Last update timestamp |
| deleted_at | TIMESTAMP | NULLABLE | - | Soft delete timestamp |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (code)
- UNIQUE KEY (slug)
- INDEX (status)
- FULLTEXT INDEX (title, description)

### modules

**Purpose**: Store course modules (chapters/sections)

| Column | Type | Constraints | Index | Description |
|--------|------|-------------|-------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | PRIMARY | Module ID |
| course_id | BIGINT | FOREIGN KEY, NOT NULL | INDEX | Reference to course |
| title | VARCHAR(255) | NOT NULL | - | Module title |
| description | TEXT | NULLABLE | - | Module description |
| order | INT | NOT NULL, DEFAULT 0 | INDEX | Display order |
| duration_minutes | INT | NOT NULL, DEFAULT 0 | - | Total duration |
| is_published | BOOLEAN | NOT NULL, DEFAULT FALSE | - | Published status |
| created_at | TIMESTAMP | NOT NULL | - | Creation timestamp |
| updated_at | TIMESTAMP | NOT NULL | - | Last update timestamp |

**Indexes**:
- PRIMARY KEY (id)
- FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
- INDEX (course_id, order)

### lessons

**Purpose**: Store individual lessons within modules

| Column | Type | Constraints | Index | Description |
|--------|------|-------------|-------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | PRIMARY | Lesson ID |
| module_id | BIGINT | FOREIGN KEY, NOT NULL | INDEX | Reference to module |
| title | VARCHAR(255) | NOT NULL | - | Lesson title |
| description | TEXT | NULLABLE | - | Lesson description |
| content | TEXT | NULLABLE | - | Lesson content |
| type | ENUM | NOT NULL, DEFAULT 'text' | - | video, text, quiz, assignment |
| video_url | VARCHAR(255) | NULLABLE | - | Video URL if applicable |
| duration_minutes | INT | NOT NULL, DEFAULT 0 | - | Lesson duration |
| order | INT | NOT NULL, DEFAULT 0 | INDEX | Display order |
| is_preview | BOOLEAN | NOT NULL, DEFAULT FALSE | - | Free preview |
| is_published | BOOLEAN | NOT NULL, DEFAULT FALSE | - | Published status |
| resources | JSON | NULLABLE | - | Additional resources |
| created_at | TIMESTAMP | NOT NULL | - | Creation timestamp |
| updated_at | TIMESTAMP | NOT NULL | - | Last update timestamp |

**Indexes**:
- PRIMARY KEY (id)
- FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
- INDEX (module_id, order)

### enrollments

**Purpose**: Store student course enrollments

| Column | Type | Constraints | Index | Description |
|--------|------|-------------|-------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | PRIMARY | Enrollment ID |
| enrollment_number | VARCHAR(50) | UNIQUE, NOT NULL | INDEX | Unique enrollment number |
| student_id | BIGINT | FOREIGN KEY, NOT NULL | INDEX | Reference to student |
| course_id | BIGINT | FOREIGN KEY, NOT NULL | INDEX | Reference to course |
| status | ENUM | NOT NULL, DEFAULT 'pending' | INDEX | pending, active, completed, cancelled, expired |
| amount_paid | DECIMAL(10,2) | NOT NULL | - | Amount paid |
| discount_applied | DECIMAL(10,2) | NOT NULL, DEFAULT 0 | - | Discount amount |
| coupon_code | VARCHAR(50) | NULLABLE | - | Applied coupon |
| enrolled_at | TIMESTAMP | NULLABLE | INDEX | Enrollment time |
| started_at | TIMESTAMP | NULLABLE | - | Start time |
| completed_at | TIMESTAMP | NULLABLE | - | Completion time |
| expires_at | TIMESTAMP | NULLABLE | - | Expiration time |
| progress_percentage | INT | NOT NULL, DEFAULT 0 | - | Progress (0-100) |
| custom_fields | JSON | NULLABLE | - | Custom data |
| notes | TEXT | NULLABLE | - | Admin notes |
| created_at | TIMESTAMP | NOT NULL | - | Creation timestamp |
| updated_at | TIMESTAMP | NOT NULL | - | Last update timestamp |
| deleted_at | TIMESTAMP | NULLABLE | - | Soft delete timestamp |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (enrollment_number)
- UNIQUE KEY (student_id, course_id) - Prevent duplicate enrollments
- FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
- FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
- INDEX (status, enrolled_at)

### payments

**Purpose**: Store payment transactions

| Column | Type | Constraints | Index | Description |
|--------|------|-------------|-------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | PRIMARY | Payment ID |
| transaction_id | VARCHAR(100) | UNIQUE, NOT NULL | INDEX | Unique transaction ID |
| enrollment_id | BIGINT | FOREIGN KEY, NOT NULL | INDEX | Reference to enrollment |
| amount | DECIMAL(10,2) | NOT NULL | - | Payment amount |
| currency | VARCHAR(3) | NOT NULL, DEFAULT 'USD' | - | Currency code |
| status | ENUM | NOT NULL, DEFAULT 'pending' | INDEX | pending, completed, failed, refunded |
| payment_method | ENUM | NOT NULL | - | credit_card, debit_card, paypal, bank_transfer, other |
| payment_gateway | VARCHAR(50) | NULLABLE | - | Gateway name |
| gateway_transaction_id | VARCHAR(255) | NULLABLE | - | Gateway trans ID |
| metadata | JSON | NULLABLE | - | Additional data |
| paid_at | TIMESTAMP | NULLABLE | INDEX | Payment time |
| created_at | TIMESTAMP | NOT NULL | - | Creation timestamp |
| updated_at | TIMESTAMP | NOT NULL | - | Last update timestamp |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (transaction_id)
- FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
- INDEX (status, paid_at)

### lesson_progress

**Purpose**: Track student progress through lessons

| Column | Type | Constraints | Index | Description |
|--------|------|-------------|-------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | PRIMARY | Progress ID |
| enrollment_id | BIGINT | FOREIGN KEY, NOT NULL | INDEX | Reference to enrollment |
| lesson_id | BIGINT | FOREIGN KEY, NOT NULL | INDEX | Reference to lesson |
| is_completed | BOOLEAN | NOT NULL, DEFAULT FALSE | INDEX | Completion status |
| time_spent_minutes | INT | NOT NULL, DEFAULT 0 | - | Time spent |
| attempts | INT | NOT NULL, DEFAULT 0 | - | Number of attempts |
| score | DECIMAL(5,2) | NULLABLE | - | Score if applicable |
| started_at | TIMESTAMP | NULLABLE | - | Start timestamp |
| completed_at | TIMESTAMP | NULLABLE | - | Completion timestamp |
| data | JSON | NULLABLE | - | Additional data |
| created_at | TIMESTAMP | NOT NULL | - | Creation timestamp |
| updated_at | TIMESTAMP | NOT NULL | - | Last update timestamp |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (enrollment_id, lesson_id)
- FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
- FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
- INDEX (enrollment_id, is_completed)

## Performance Optimization

### Indexing Strategy

1. **Primary Keys**: Auto-indexed on all tables
2. **Foreign Keys**: Indexed for JOIN performance
3. **Unique Constraints**: Email, codes, enrollment numbers
4. **Compound Indexes**: For common query patterns
5. **Full-Text Indexes**: For search functionality

### Query Optimization

1. **N+1 Prevention**: Use Eloquent eager loading
2. **Pagination**: Limit result sets
3. **SELECT Specific Columns**: Avoid SELECT *
4. **Connection Pooling**: Reuse database connections

### Data Volume Estimates (per year)

- Students: ~10,000
- Courses: ~500
- Modules: ~2,000
- Lessons: ~15,000
- Enrollments: ~50,000
- Payments: ~50,000
- Lesson Progress: ~750,000

**Total DB Size Estimate**: ~5-10 GB per year
