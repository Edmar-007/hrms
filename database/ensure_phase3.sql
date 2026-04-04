CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_exp(user_id, expires_at),
    INDEX idx_company(company_id)
);

ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL;

ALTER TABLE attendance
    ADD COLUMN IF NOT EXISTS scan_ip VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS user_agent VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS last_action ENUM('time_in','break_in','break_out','time_out') NULL,
    ADD COLUMN IF NOT EXISTS last_scan_at DATETIME NULL;

ALTER TABLE attendance_settings
    ADD COLUMN IF NOT EXISTS duplicate_scan_seconds INT DEFAULT 3,
    ADD COLUMN IF NOT EXISTS require_action_sequence TINYINT(1) DEFAULT 1,
    ADD COLUMN IF NOT EXISTS gps_capture_enabled TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS out_of_shift_grace_before_minutes INT DEFAULT 60,
    ADD COLUMN IF NOT EXISTS out_of_shift_grace_after_minutes INT DEFAULT 60;

ALTER TABLE leave_requests
    ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS cancelled_by INT NULL;

