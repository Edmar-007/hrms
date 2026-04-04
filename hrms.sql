CREATE DATABASE IF NOT EXISTS hrms_db;
USE hrms_db;

-- Companies (Tenants) for SaaS
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    logo_url VARCHAR(500),
    nav_settings JSON,
    timezone VARCHAR(50) DEFAULT 'Asia/Manila',
    currency VARCHAR(10) DEFAULT 'PHP',
    plan ENUM('free','starter','professional','enterprise') DEFAULT 'free',
    plan_expires_at DATE NULL,
    max_employees INT DEFAULT 5,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Subscription Plans
CREATE TABLE subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    price_monthly DECIMAL(10,2) DEFAULT 0,
    price_yearly DECIMAL(10,2) DEFAULT 0,
    max_employees INT DEFAULT 5,
    features JSON,
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    UNIQUE KEY uniq_dept(company_id, name)
);

CREATE TABLE positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    UNIQUE KEY uniq_pos(company_id, name)
);

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    employee_code VARCHAR(30),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(50),
    department_id INT NULL,
    position_id INT NULL,
    hire_date DATE NULL,
    basic_salary DECIMAL(10,2) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_emp_code(company_id, employee_code),
    UNIQUE KEY uniq_emp_email(company_id, email)
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NULL,
    employee_id INT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Super Admin','Admin','HR Officer','Manager','Employee') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME NULL,
    time_out TIME NULL,
    notes VARCHAR(255),
    UNIQUE KEY uniq_attendance(company_id, employee_id, date)
);

CREATE TABLE leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    days_allowed INT DEFAULT 0,
    is_paid TINYINT(1) DEFAULT 1
);

CREATE TABLE leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    user_id INT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    link VARCHAR(500),
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read(user_id, is_read)
);

-- Activity logs
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company_date(company_id, created_at)
);

-- User preferences
CREATE TABLE user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    theme ENUM('light','dark','auto') DEFAULT 'light',
    sidebar_collapsed TINYINT(1) DEFAULT 0,
    language VARCHAR(10) DEFAULT 'en'
);

-- Announcements
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    is_pinned TINYINT(1) DEFAULT 0,
    published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    created_by INT
);

-- Insert subscription plans
INSERT INTO subscription_plans (name, slug, price_monthly, price_yearly, max_employees, features) VALUES
('Free', 'free', 0, 0, 5, '{"attendance":true,"leaves":true,"payroll":false,"reports":false,"qr_scanner":false}'),
('Starter', 'starter', 499, 4990, 25, '{"attendance":true,"leaves":true,"payroll":true,"reports":false,"qr_scanner":true}'),
('Professional', 'professional', 999, 9990, 100, '{"attendance":true,"leaves":true,"payroll":true,"reports":true,"qr_scanner":true}'),
('Enterprise', 'enterprise', 2499, 24990, 9999, '{"attendance":true,"leaves":true,"payroll":true,"reports":true,"qr_scanner":true,"api":true}');

-- Create default company
INSERT INTO companies (name, slug, email, plan, max_employees) 
VALUES ('Demo Company', 'demo', 'admin@hrms.local', 'professional', 100);

-- Insert defaults
INSERT INTO departments(company_id, name) VALUES (1,'Operations'),(1,'Finance'),(1,'HR');
INSERT INTO positions(company_id, name) VALUES (1,'Loan Officer'),(1,'Accountant'),(1,'HR Specialist');
INSERT INTO leave_types(company_id, name, days_allowed) VALUES (1,'Vacation Leave',15),(1,'Sick Leave',15);

INSERT INTO employees(company_id, employee_code, first_name, last_name, email, department_id, position_id, basic_salary, status)
VALUES (1, 'EMP-001', 'System', 'Admin', 'admin@hrms.local', 3, 3, 50000, 'active');

-- password: admin123
INSERT INTO users(company_id, employee_id, email, password_hash, role, is_active)
VALUES (1, 1, 'admin@hrms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 1);

INSERT INTO user_preferences(user_id, theme) VALUES (1, 'light');

-- =====================================================
-- SAMPLE DATA / SEED DATA
-- =====================================================

-- More Departments
INSERT INTO departments(company_id, name) VALUES 
(1, 'IT'),
(1, 'Marketing'),
(1, 'Sales'),
(1, 'Customer Service'),
(1, 'Administration');

-- More Positions
INSERT INTO positions(company_id, name) VALUES 
(1, 'Manager'),
(1, 'Senior Developer'),
(1, 'Junior Developer'),
(1, 'Sales Representative'),
(1, 'Marketing Specialist'),
(1, 'Customer Support'),
(1, 'Team Lead'),
(1, 'Intern');

-- More Leave Types
INSERT INTO leave_types(company_id, name, days_allowed) VALUES 
(1, 'Maternity Leave', 60),
(1, 'Paternity Leave', 7),
(1, 'Emergency Leave', 5),
(1, 'Birthday Leave', 1);

-- Sample Employees (20 employees)
INSERT INTO employees(company_id, employee_code, first_name, last_name, email, phone, department_id, position_id, hire_date, basic_salary, status) VALUES
(1, 'EMP-0002', 'Maria', 'Santos', 'maria.santos@demo.com', '09171234567', 1, 4, '2023-01-15', 35000, 'active'),
(1, 'EMP-0003', 'Juan', 'Dela Cruz', 'juan.delacruz@demo.com', '09181234567', 2, 2, '2023-02-01', 45000, 'active'),
(1, 'EMP-0004', 'Ana', 'Garcia', 'ana.garcia@demo.com', '09191234567', 4, 5, '2023-03-10', 32000, 'active'),
(1, 'EMP-0005', 'Pedro', 'Reyes', 'pedro.reyes@demo.com', '09201234567', 5, 6, '2023-04-05', 28000, 'active'),
(1, 'EMP-0006', 'Sofia', 'Martinez', 'sofia.martinez@demo.com', '09211234567', 6, 7, '2023-05-20', 30000, 'active'),
(1, 'EMP-0007', 'Carlos', 'Lopez', 'carlos.lopez@demo.com', '09221234567', 7, 8, '2023-06-15', 38000, 'active'),
(1, 'EMP-0008', 'Rosa', 'Fernandez', 'rosa.fernandez@demo.com', '09231234567', 4, 9, '2023-07-01', 55000, 'active'),
(1, 'EMP-0009', 'Miguel', 'Torres', 'miguel.torres@demo.com', '09241234567', 4, 5, '2023-08-10', 42000, 'active'),
(1, 'EMP-0010', 'Elena', 'Ramos', 'elena.ramos@demo.com', '09251234567', 5, 6, '2023-09-05', 33000, 'active'),
(1, 'EMP-0011', 'Jose', 'Villanueva', 'jose.villanueva@demo.com', '09261234567', 6, 7, '2023-10-15', 36000, 'active'),
(1, 'EMP-0012', 'Carmen', 'Cruz', 'carmen.cruz@demo.com', '09271234567', 1, 1, '2022-01-10', 25000, 'active'),
(1, 'EMP-0013', 'Antonio', 'Mendoza', 'antonio.mendoza@demo.com', '09281234567', 2, 2, '2022-03-20', 48000, 'active'),
(1, 'EMP-0014', 'Lucia', 'Bautista', 'lucia.bautista@demo.com', '09291234567', 4, 10, '2024-01-05', 22000, 'active'),
(1, 'EMP-0015', 'Roberto', 'Aquino', 'roberto.aquino@demo.com', '09301234567', 4, 5, '2022-06-15', 40000, 'active'),
(1, 'EMP-0016', 'Isabella', 'Gonzales', 'isabella.gonzales@demo.com', '09311234567', 7, 8, '2022-08-01', 37000, 'active'),
(1, 'EMP-0017', 'Francisco', 'Pascual', 'francisco.pascual@demo.com', '09321234567', 5, 6, '2022-11-10', 31000, 'active'),
(1, 'EMP-0018', 'Teresa', 'Rivera', 'teresa.rivera@demo.com', '09331234567', 6, 7, '2023-01-25', 34000, 'active'),
(1, 'EMP-0019', 'Manuel', 'Castro', 'manuel.castro@demo.com', '09341234567', 1, 1, '2021-05-15', 26000, 'active'),
(1, 'EMP-0020', 'Patricia', 'Diaz', 'patricia.diaz@demo.com', '09351234567', 2, 2, '2021-09-01', 44000, 'active'),
(1, 'EMP-0021', 'Ricardo', 'Flores', 'ricardo.flores@demo.com', '09361234567', 4, 4, '2024-02-01', 60000, 'active');

-- Create user accounts for some employees (password: password123)
INSERT INTO users(company_id, employee_id, email, password_hash, role, is_active) VALUES
(1, 2, 'maria.santos@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR Officer', 1),
(1, 3, 'juan.delacruz@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager', 1),
(1, 8, 'rosa.fernandez@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager', 1),
(1, 21, 'ricardo.flores@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager', 1),
(1, 4, 'ana.garcia@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee', 1),
(1, 5, 'pedro.reyes@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee', 1);

-- Attendance records for last 30 days
INSERT INTO attendance(company_id, employee_id, date, time_in, time_out) VALUES
-- Today's attendance
(1, 1, CURDATE(), '08:02:00', '17:05:00'),
(1, 2, CURDATE(), '07:55:00', '17:10:00'),
(1, 3, CURDATE(), '08:15:00', '17:30:00'),
(1, 4, CURDATE(), '08:00:00', '17:00:00'),
(1, 5, CURDATE(), '08:30:00', NULL),
(1, 6, CURDATE(), '07:45:00', NULL),
(1, 7, CURDATE(), '08:10:00', '17:15:00'),
(1, 8, CURDATE(), '08:05:00', '18:00:00'),
(1, 9, CURDATE(), '08:20:00', NULL),
(1, 10, CURDATE(), '07:50:00', '17:05:00'),

-- Yesterday
(1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', '17:00:00'),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:05:00', '17:15:00'),
(1, 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:10:00', '17:30:00'),
(1, 4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:55:00', '17:00:00'),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', '17:10:00'),
(1, 6, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:15:00', '17:00:00'),
(1, 7, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', '17:05:00'),
(1, 8, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:50:00', '18:30:00'),
(1, 9, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:30:00', '17:00:00'),
(1, 10, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', '17:00:00'),
(1, 11, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:05:00', '17:10:00'),
(1, 12, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', '17:00:00'),

-- 2 days ago
(1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00', '17:00:00'),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:10:00', '17:20:00'),
(1, 3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00', '17:00:00'),
(1, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:05:00', '17:05:00'),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00', '17:00:00'),
(1, 6, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '07:55:00', '17:00:00'),
(1, 7, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:15:00', '17:15:00'),
(1, 8, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00', '17:30:00'),
(1, 13, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00', '17:00:00'),
(1, 14, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:20:00', '17:00:00'),

-- 3 days ago
(1, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:00:00', '17:00:00'),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:00:00', '17:10:00'),
(1, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:05:00', '17:00:00'),
(1, 4, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:00:00', '17:00:00'),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:10:00', '17:05:00'),
(1, 15, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:00:00', '17:00:00'),
(1, 16, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:00:00', '17:00:00'),
(1, 17, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:15:00', '17:30:00'),

-- More historical attendance (week ago)
(1, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', '17:00:00'),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:05:00', '17:10:00'),
(1, 3, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', '17:00:00'),
(1, 4, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', '17:00:00'),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:10:00', '17:05:00'),
(1, 6, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', '17:00:00'),
(1, 7, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', '17:00:00'),
(1, 8, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '07:55:00', '18:00:00'),
(1, 9, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', '17:00:00'),
(1, 10, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:05:00', '17:00:00'),
(1, 11, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', '17:00:00'),
(1, 12, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', '17:00:00'),
(1, 13, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:15:00', '17:15:00'),
(1, 14, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', '17:00:00'),
(1, 15, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', '17:00:00');

-- Leave Requests
INSERT INTO leave_requests(company_id, employee_id, leave_type_id, start_date, end_date, reason, status, approved_by, approved_at, created_at) VALUES
-- Pending requests
(1, 4, 1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Family vacation trip to Boracay', 'pending', NULL, NULL, NOW()),
(1, 6, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Medical checkup appointment', 'pending', NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 9, 1, DATE_ADD(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(), INTERVAL 18 DAY), 'Attending a wedding in the province', 'pending', NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- Approved requests
(1, 2, 1, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 17 DAY), 'Annual leave - family reunion', 'approved', 1, DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY)),
(1, 5, 2, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'Flu and fever', 'approved', 1, DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY)),
(1, 7, 5, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Family emergency', 'approved', 1, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 10, 1, DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'Vacation to Japan', 'approved', 1, DATE_SUB(NOW(), INTERVAL 50 DAY), DATE_SUB(NOW(), INTERVAL 55 DAY)),
(1, 12, 2, DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Dental surgery recovery', 'approved', 1, DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),

-- Rejected requests  
(1, 8, 1, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'Extended vacation', 'rejected', 1, DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(1, 3, 1, DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_SUB(CURDATE(), INTERVAL 50 DAY), 'Personal travel', 'rejected', 1, DATE_SUB(NOW(), INTERVAL 65 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY));

-- Notifications
INSERT INTO notifications(company_id, user_id, type, title, message, link, is_read, created_at) VALUES
(1, 1, 'leave', 'New Leave Request', 'Ana Garcia requested vacation leave', '/hrms/modules/leaves/index.php', 0, NOW()),
(1, 1, 'leave', 'New Leave Request', 'Sofia Martinez requested sick leave', '/hrms/modules/leaves/index.php', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 1, 'system', 'Welcome to HRMS', 'Your account has been set up successfully', '/hrms/modules/dashboard.php', 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(1, 1, 'attendance', 'Late Arrival Alert', '3 employees arrived late today', '/hrms/modules/attendance/index.php', 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, NULL, 'announcement', 'System Maintenance', 'Scheduled maintenance this weekend', NULL, 0, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Announcements
INSERT INTO announcements(company_id, title, content, priority, is_pinned, published_at, created_by) VALUES
(1, 'Welcome to HRMS SaaS!', 'We are excited to launch our new HR Management System. This platform will help streamline all HR operations including attendance tracking, leave management, and payroll processing.', 'high', 1, NOW(), 1),
(1, 'Office Holiday Schedule', 'Please note that the office will be closed on the following dates for the upcoming holidays. Plan your leaves accordingly.', 'normal', 0, DATE_SUB(NOW(), INTERVAL 5 DAY), 1),
(1, 'New QR Code Attendance System', 'We have implemented a new QR code based attendance system. Please collect your QR code from the HR department.', 'high', 0, DATE_SUB(NOW(), INTERVAL 10 DAY), 1),
(1, 'Team Building Activity', 'Annual team building activity scheduled for next month. More details to follow.', 'low', 0, DATE_SUB(NOW(), INTERVAL 15 DAY), 1);

-- Activity Logs
INSERT INTO activity_logs(company_id, user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES
(1, 1, 'login', 'user', 1, '{"browser":"Chrome"}', '192.168.1.100', NOW()),
(1, 1, 'create', 'employee', 21, '{"name":"Ricardo Flores"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 1, 'approve', 'leave_request', 5, '{"employee":"Pedro Reyes"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 1, 'update', 'company', 1, '{"field":"name"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 2, 'login', 'user', 2, '{"browser":"Firefox"}', '192.168.1.101', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- User preferences for other users
INSERT INTO user_preferences(user_id, theme) VALUES
(2, 'dark'),
(3, 'light'),
(4, 'light'),
(5, 'dark'),
(6, 'light');

-- =====================================================
-- NEW TABLES FOR COMPLETE HRMS FEATURES
-- =====================================================

-- SHIFTS - Define work schedules
CREATE TABLE IF NOT EXISTS shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    lunch_start TIME NULL,
    lunch_end TIME NULL,
    total_break_minutes INT DEFAULT 60,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_shift(company_id, name),
    INDEX idx_company(company_id)
);

-- SHIFT_ASSIGNMENTS - Assign shifts to employees
CREATE TABLE IF NOT EXISTS shift_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    employee_id INT NOT NULL,
    shift_id INT NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES shifts(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_emp_date(employee_id, effective_from),
    INDEX idx_company(company_id)
);

-- HOLIDAYS - Company holidays and rest days
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    holiday_date DATE NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_public TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_holiday(company_id, holiday_date),
    INDEX idx_company_date(company_id, holiday_date)
);

-- BREAK_RECORDS - Track individual breaks
CREATE TABLE IF NOT EXISTS break_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    employee_id INT NOT NULL,
    attendance_id INT NOT NULL,
    break_start DATETIME NOT NULL,
    break_end DATETIME NULL,
    duration_minutes INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attendance_id) REFERENCES attendance(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_attendance(attendance_id),
    INDEX idx_company(company_id)
);

-- EMPLOYEE_LEAVE_BALANCE - Track leave usage
CREATE TABLE IF NOT EXISTS employee_leave_balance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    year INT NOT NULL,
    opening_balance INT NOT NULL,
    used INT DEFAULT 0,
    carried_over INT DEFAULT 0,
    remaining INT GENERATED ALWAYS AS (opening_balance + carried_over - used) STORED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_balance(company_id, employee_id, leave_type_id, year),
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_emp_year(employee_id, year),
    INDEX idx_company(company_id)
);

-- SALARY_STRUCTURES - Flexible salary components framework
CREATE TABLE IF NOT EXISTS salary_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_struct(company_id, name),
    INDEX idx_company(company_id)
);

-- SALARY_COMPONENTS - Individual salary components (earnings & deductions)
CREATE TABLE IF NOT EXISTS salary_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    salary_structure_id INT NOT NULL,
    component_type ENUM('earning','deduction') NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('fixed','percentage') DEFAULT 'fixed',
    value DECIMAL(10,2) DEFAULT 0,
    order_seq INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (salary_structure_id) REFERENCES salary_structures(id) ON DELETE CASCADE,
    INDEX idx_struct(salary_structure_id),
    INDEX idx_company(company_id)
);

-- EMPLOYEE_SALARY_COMPONENTS - Individual employee allowances/deductions
CREATE TABLE IF NOT EXISTS employee_salary_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    employee_id INT NOT NULL,
    component_type ENUM('earning','deduction') NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('fixed','percentage') DEFAULT 'fixed',
    value DECIMAL(10,2) DEFAULT 0,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_emp_date(employee_id, effective_from),
    INDEX idx_company(company_id)
);

-- PAYROLL_RECORDS - Store calculated payslips (audit trail, not real-time)
CREATE TABLE IF NOT EXISTS payroll_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    employee_id INT NOT NULL,
    payroll_period_start DATE NOT NULL,
    payroll_period_end DATE NOT NULL,
    total_earnings DECIMAL(12,2) DEFAULT 0,
    total_deductions DECIMAL(12,2) DEFAULT 0,
    net_pay DECIMAL(12,2) DEFAULT 0,
    status ENUM('draft','processed','paid','cancelled') DEFAULT 'draft',
    processed_by INT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_payroll(company_id, employee_id, payroll_period_start),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_emp_period(employee_id, payroll_period_start),
    INDEX idx_company(company_id)
);

-- PAYROLL_ITEMS - Line items for payroll (deductions & allowances per payslip)
CREATE TABLE IF NOT EXISTS payroll_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_record_id INT NOT NULL,
    component_name VARCHAR(100) NOT NULL,
    component_type ENUM('earning','deduction') NOT NULL,
    amount DECIMAL(12,2) DEFAULT 0,
    calculation_details JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_record_id) REFERENCES payroll_records(id) ON DELETE CASCADE,
    INDEX idx_payroll(payroll_record_id)
);

-- THIRTEENTH_MONTH_RECORDS - 13th month pay computation (Philippines requirement)
CREATE TABLE IF NOT EXISTS thirteenth_month_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    employee_id INT NOT NULL,
    year INT NOT NULL,
    total_basic_earned DECIMAL(12,2) DEFAULT 0,
    thirteenth_month_amount DECIMAL(12,2) DEFAULT 0,
    less_absences DECIMAL(12,2) DEFAULT 0,
    less_unpaid_leave DECIMAL(12,2) DEFAULT 0,
    final_amount DECIMAL(12,2) DEFAULT 0,
    computation_date DATE NOT NULL,
    status ENUM('draft','finalized','paid') DEFAULT 'draft',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_13th(company_id, employee_id, year),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_emp_year(employee_id, year),
    INDEX idx_company(company_id)
);

-- ATTENDANCE_EXCEPTIONS - Mark absences, half-days, late arrivals, etc.
CREATE TABLE IF NOT EXISTS attendance_exceptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    employee_id INT NOT NULL,
    exception_date DATE NOT NULL,
    exception_type ENUM('absence','half_day','late_arrival','early_departure','undertime','lwop') DEFAULT 'absence',
    remarks TEXT,
    is_approved TINYINT(1) DEFAULT 0,
    approved_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_exception(company_id, employee_id, exception_date, exception_type),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_emp_date(employee_id, exception_date),
    INDEX idx_company(company_id)
);

-- ATTENDANCE_SETTINGS - Grace period, undertime rules, work hours per company
CREATE TABLE IF NOT EXISTS attendance_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL UNIQUE,
    grace_period_minutes INT DEFAULT 10,
    undertime_threshold_minutes INT DEFAULT 480,
    weekend_days VARCHAR(20) DEFAULT '0,6',
    daily_work_hours DECIMAL(5,2) DEFAULT 8,
    allow_overtime TINYINT(1) DEFAULT 1,
    overtime_start_hour DECIMAL(5,2) DEFAULT 8.5,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- Insert default shift (8am-5pm with 1 hour lunch)
INSERT INTO shifts (company_id, name, start_time, end_time, lunch_start, lunch_end, total_break_minutes)
VALUES (1, 'Standard (8AM-5PM)', '08:00:00', '17:00:00', '12:00:00', '13:00:00', 60);

-- Insert default salary structure
INSERT INTO salary_structures (company_id, name, description)
VALUES (1, 'Standard', 'Default salary structure for all employees');

-- Insert common salary components for the default structure (last ID will be used)
INSERT INTO salary_components (company_id, salary_structure_id, component_type, name, type, value, order_seq)
VALUES
(1, 1, 'earning', 'Basic Salary', 'fixed', 0, 1),
(1, 1, 'deduction', 'SSS', 'percentage', 4.5, 2),
(1, 1, 'deduction', 'PhilHealth', 'percentage', 3.5, 3),
(1, 1, 'deduction', 'Pag-IBIG', 'fixed', 100, 4),
(1, 1, 'deduction', 'Withholding Tax', 'percentage', 2, 5);

-- Create attendance settings for default company
INSERT INTO attendance_settings (company_id, grace_period_minutes, daily_work_hours)
VALUES (1, 10, 8);

-- Initialize leave balances for existing employees (2026)
INSERT INTO employee_leave_balance (company_id, employee_id, leave_type_id, year, opening_balance, carried_over)
SELECT c.id, e.id, lt.id, YEAR(CURDATE()), lt.days_allowed, 0
FROM employees e
CROSS JOIN (SELECT DISTINCT company_id as id FROM companies) c
CROSS JOIN leave_types lt
WHERE e.company_id = c.id AND lt.company_id = c.id
ON DUPLICATE KEY UPDATE opening_balance = VALUES(opening_balance);
