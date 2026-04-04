# Microfinance HRMS

## Setup
1. Import `hrms.sql` into MySQL
2. (Upgrade existing DB) run `/database/migrate.php` to apply `database/ensure_phase3.sql`
2. Update `config/db.php`
3. Open `http://localhost/hrms`
4. Login: `admin@hrms.local` / `admin123`

## Key Modules Added (High-Impact Phase)
- Auth recovery: `/auth/forgot-password.php`, `/auth/reset-password.php`
- Admin user accounts: `/modules/users/index.php`
- Employee CSV import: `/modules/employees/import.php`
- Employee photo upload: `/modules/employees/edit.php`
- Attendance live feed: `/modules/attendance/live_feed.php`
- Attendance correction: `/modules/attendance/correct.php`
- Leave calendar: `/modules/leaves/calendar.php`
- Profile self-service: `/modules/profile/index.php`
- Employee payroll summary: `/modules/payroll/my-summary.php`
- Audit logs: `/modules/audit/logs.php`

## Report Export API
- CSV export: `/api/export-report.php?type=attendance|leaves|audit&format=csv&month=YYYY-MM`
- Printable export: `/api/export-report.php?type=attendance|leaves|audit&format=print&month=YYYY-MM`
