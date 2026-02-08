-- Sample Data for Learning Platform Testing (PostgreSQL)
-- Run this after migrations to populate database with test data

-- Clear existing data (optional)
TRUNCATE TABLE lesson_progress, payments, enrollments, lessons, modules, courses, students RESTART IDENTITY CASCADE;

-- Insert sample courses
INSERT INTO courses (
    title, 
    code, 
    description,
    slug,
    price, 
    discount_price, 
    duration_hours, 
    level,
    max_students, 
    status, 
    created_at, 
    updated_at
) VALUES
('Complete Laravel Development', 'LAR-2024-001', 'Master Laravel framework from basics to advanced topics. Build production-ready applications.', 'complete-laravel-development', 299.99, 399.99, 40, 'intermediate', 50, 'published', NOW(), NOW()),
('Vue.js Fundamentals', 'VUE-2024-002', 'Learn Vue.js 3 with Composition API. Build modern reactive web applications.', 'vuejs-fundamentals', 199.99, 249.99, 25, 'beginner', 40, 'published', NOW(), NOW()),
('React Advanced Patterns', 'REACT-2024-003', 'Advanced React patterns, hooks, context, and performance optimization.', 'react-advanced-patterns', 349.99, 449.99, 35, 'advanced', 30, 'published', NOW(), NOW()),
('PHP & MySQL Essentials', 'PHP-2024-004', 'Learn PHP programming and MySQL database design from scratch.', 'php-mysql-essentials', 149.99, 199.99, 30, 'beginner', 60, 'published', NOW(), NOW()),
('Docker for Developers', 'DOCK-2024-005', 'Containerize your applications with Docker. Learn Docker Compose and Kubernetes basics.', 'docker-for-developers', 179.99, 229.99, 20, 'intermediate', 35, 'published', NOW(), NOW());

-- Insert modules for Laravel course (course_id = 1)
INSERT INTO modules (
    course_id,
    title,
    description,
    "order",
    duration_minutes,
    created_at,
    updated_at
) VALUES
(1, 'Laravel Basics', 'Introduction to Laravel framework, installation, and routing.', 1, 180, NOW(), NOW()),
(1, 'Database & Eloquent ORM', 'Working with databases, migrations, and Eloquent relationships.', 2, 240, NOW(), NOW()),
(1, 'Authentication & Security', 'User authentication, authorization, and security best practices.', 3, 200, NOW(), NOW()),
(1, 'Advanced Features', 'Queues, events, caching, and API development.', 4, 280, NOW(), NOW());

-- Insert lessons for Module 1 (Laravel Basics)
INSERT INTO lessons (
    module_id,
    title,
    description,
    type,
    video_url,
    duration_minutes,
    "order",
    is_preview,
    created_at,
    updated_at
) VALUES
(1, 'Introduction to Laravel', 'Overview of Laravel framework and its ecosystem.', 'video', 'https://example.com/videos/laravel-intro.mp4', 15, 1, TRUE, NOW(), NOW()),
(1, 'Installing Laravel', 'Step-by-step guide to installing Laravel using Composer.', 'video', 'https://example.com/videos/laravel-install.mp4', 20, 2, TRUE, NOW(), NOW()),
(1, 'Your First Route', 'Creating routes and handling HTTP requests.', 'video', 'https://example.com/videos/laravel-routes.mp4', 25, 3, FALSE, NOW(), NOW()),
(1, 'Views & Blade Templates', 'Working with Blade templating engine.', 'video', 'https://example.com/videos/blade-templates.mp4', 30, 4, FALSE, NOW(), NOW());

-- Insert lessons for Module 2 (Database & Eloquent)
INSERT INTO lessons (
    module_id,
    title,
    description,
    type,
    video_url,
    duration_minutes,
    "order",
    is_preview,
    created_at,
    updated_at
) VALUES
(2, 'Database Configuration', 'Configuring database connections in Laravel.', 'video', 'https://example.com/videos/db-config.mp4', 15, 1, FALSE, NOW(), NOW()),
(2, 'Creating Migrations', 'Database migrations and schema building.', 'video', 'https://example.com/videos/migrations.mp4', 35, 2, FALSE, NOW(), NOW()),
(2, 'Eloquent Models', 'Creating and using Eloquent models.', 'video', 'https://example.com/videos/eloquent-models.mp4', 40, 3, FALSE, NOW(), NOW()),
(2, 'Relationships', 'One-to-many, many-to-many, and polymorphic relationships.', 'video', 'https://example.com/videos/relationships.mp4', 50, 4, FALSE, NOW(), NOW());

-- Insert sample students (for testing enrollments)
INSERT INTO students (
    first_name,
    last_name,
    email,
    password,
    phone,
    date_of_birth,
    address,
    city,
    country,
    postal_code,
    status,
    preferences,
    created_at,
    updated_at
) VALUES
('Alice', 'Johnson', 'alice@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1234567001', '1995-03-15', '123 Main St', 'New York', 'USA', '10001', 'active', '{"notifications":true,"newsletter":true}', NOW(), NOW()),
('Bob', 'Smith', 'bob@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1234567002', '1992-07-22', '456 Oak Ave', 'Los Angeles', 'USA', '90001', 'active', '{"notifications":true,"newsletter":false}', NOW(), NOW()),
('Carol', 'Williams', 'carol@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1234567003', '1998-11-30', '789 Pine Rd', 'Chicago', 'USA', '60007', 'active', '{"notifications":false,"newsletter":true}', NOW(), NOW());

-- Password for all test users is: "password"

-- Verify data
SELECT 'Courses' as "Table_Name", COUNT(*) as "Count" FROM courses
UNION ALL
SELECT 'Modules', COUNT(*) FROM modules
UNION ALL
SELECT 'Lessons', COUNT(*) FROM lessons
UNION ALL
SELECT 'Students', COUNT(*) FROM students;

-- Show course details
SELECT 
    c.title,
    c.code,
    c.price,
    c.max_students,
    COUNT(DISTINCT m.id) as total_modules,
    COUNT(l.id) as total_lessons
FROM courses c
LEFT JOIN modules m ON c.id = m.course_id
LEFT JOIN lessons l ON m.id = l.module_id
GROUP BY c.id, c.title, c.code, c.price, c.max_students;
