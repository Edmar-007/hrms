ALTER TABLE companies
    ADD COLUMN IF NOT EXISTS nav_settings JSON NULL,
    ADD COLUMN IF NOT EXISTS work_day_start TIME NULL,
    ADD COLUMN IF NOT EXISTS work_day_end TIME NULL,
    ADD COLUMN IF NOT EXISTS date_format VARCHAR(50) NULL;

ALTER TABLE departments
    ADD COLUMN IF NOT EXISTS company_id INT NOT NULL DEFAULT 1;

UPDATE departments
SET company_id = 1
WHERE company_id IS NULL OR company_id = 0;

ALTER TABLE departments
    ADD UNIQUE KEY uniq_dept (company_id, name);

ALTER TABLE positions
    ADD COLUMN IF NOT EXISTS company_id INT NOT NULL DEFAULT 1;

UPDATE positions
SET company_id = 1
WHERE company_id IS NULL OR company_id = 0;

ALTER TABLE positions
    ADD UNIQUE KEY uniq_pos (company_id, name);

ALTER TABLE leave_types
    ADD COLUMN IF NOT EXISTS company_id INT NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS is_paid TINYINT(1) NOT NULL DEFAULT 1;

UPDATE leave_types
SET company_id = 1
WHERE company_id IS NULL OR company_id = 0;

ALTER TABLE leave_types
    ADD UNIQUE KEY uniq_leave_type (company_id, name);

ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS company_id INT NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS hire_date DATE NULL,
    ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

UPDATE employees
SET company_id = 1
WHERE company_id IS NULL OR company_id = 0;

ALTER TABLE employees
    ADD UNIQUE KEY uniq_emp_code (company_id, employee_code);

ALTER TABLE employees
    ADD UNIQUE KEY uniq_emp_email (company_id, email);

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS company_id INT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS last_login DATETIME NULL,
    ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

UPDATE users
SET company_id = 1
WHERE company_id IS NULL OR company_id = 0;

ALTER TABLE users
    MODIFY COLUMN role ENUM('Super Admin', 'Admin', 'HR Officer', 'Manager', 'Employee') NOT NULL;

ALTER TABLE attendance
    ADD COLUMN IF NOT EXISTS company_id INT NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS notes VARCHAR(255) NULL;

UPDATE attendance
SET company_id = 1
WHERE company_id IS NULL OR company_id = 0;

ALTER TABLE attendance
    ADD UNIQUE KEY uniq_attendance (company_id, employee_id, date);

ALTER TABLE leave_requests
    ADD COLUMN IF NOT EXISTS company_id INT NOT NULL DEFAULT 1;

UPDATE leave_requests
SET company_id = 1
WHERE company_id IS NULL OR company_id = 0;

ALTER TABLE attendance_settings
    ADD UNIQUE KEY uniq_attendance_settings_company (company_id);
