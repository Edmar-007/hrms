<?php
require_once __DIR__ . '/../config/config.php';

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . BASE_URL . $path);
        exit;
    }
}

if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        return is_array($user) ? $user : null;
    }
}

if (!function_exists('company')) {
    function company(): ?array
    {
        $company = $_SESSION['company'] ?? null;
        return is_array($company) ? $company : null;
    }
}

if (!function_exists('company_id')) {
    function company_id(): ?int
    {
        $company = company();
        if (is_array($company) && isset($company['id'])) {
            return (int)$company['id'];
        }

        $user = current_user();
        if (is_array($user) && isset($user['company_id'])) {
            return (int)$user['company_id'];
        }

        return null;
    }
}

if (!function_exists('json_response')) {
    function json_response(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string
    {
        if (!$datetime) {
            return 'Just now';
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return 'Just now';
        }

        $delta = max(0, time() - $timestamp);
        $units = [
            'year' => 31536000,
            'month' => 2592000,
            'week' => 604800,
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
        ];

        foreach ($units as $label => $seconds) {
            if ($delta >= $seconds) {
                $value = (int)floor($delta / $seconds);
                return $value . ' ' . $label . ($value === 1 ? '' : 's') . ' ago';
            }
        }

        return 'Just now';
    }
}

if (!function_exists('format_currency')) {
    function format_currency(int|float|string|null $amount): string
    {
        $currency = (string)((company()['currency'] ?? 'PHP'));
        $symbols = [
            'PHP' => 'PHP ',
            'USD' => '$',
            'EUR' => 'EUR ',
        ];

        return ($symbols[$currency] ?? ($currency . ' ')) . number_format((float)$amount, 2);
    }
}

if (!function_exists('hrms_table_exists')) {
    function hrms_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        $key = spl_object_id($pdo) . ':' . strtolower($table);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            $cache[$key] = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}

if (!function_exists('log_activity')) {
    function log_activity(string $action, string $entityType, int|string|null $entityId = null, ?array $meta = null): void
    {
        global $pdo;

        if (!($pdo instanceof PDO) || !hrms_table_exists($pdo, 'activity_logs')) {
            return;
        }

        try {
            $user = current_user();
            $stmt = $pdo->prepare(
                'INSERT INTO activity_logs (company_id, user_id, action, entity_type, entity_id, ip_address, details, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                company_id(),
                $user['id'] ?? null,
                $action,
                $entityType,
                $entityId !== null ? (int)$entityId : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]);
        } catch (Throwable $e) {
            error_log('Activity log skipped: ' . $e->getMessage());
        }
    }
}

if (!function_exists('generate_employee_code')) {
    function generate_employee_code(int $companyId): string
    {
        global $pdo;

        $prefix = 'EMP' . str_pad((string)$companyId, 2, '0', STR_PAD_LEFT);
        if (!($pdo instanceof PDO) || !hrms_table_exists($pdo, 'employees')) {
            return $prefix . '-' . date('ymdHis');
        }

        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE company_id = ?');
            $stmt->execute([$companyId]);
            $next = ((int)$stmt->fetchColumn()) + 1;
            return $prefix . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        } catch (Throwable $e) {
            return $prefix . '-' . date('ymdHis');
        }
    }
}

if (!function_exists('get_employee_salary_components')) {
    function get_employee_salary_components(int $employeeId, ?string $date = null): array
    {
        global $pdo;

        if (!($pdo instanceof PDO) || !hrms_table_exists($pdo, 'employee_salary_components')) {
            return [];
        }

        $date = $date ?: date('Y-m-d');
        $cid = company_id() ?? 1;

        try {
            $st = $pdo->prepare(
                'SELECT *
                 FROM employee_salary_components
                 WHERE company_id = ? AND employee_id = ?
                   AND effective_from <= ?
                   AND (effective_to IS NULL OR effective_to >= ?)
                   AND is_active = 1
                 ORDER BY component_type DESC, name ASC'
            );
            $st->execute([$cid, $employeeId, $date, $date]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('calculate_payroll')) {
    function calculate_payroll(int $employeeId, string $periodStart, string $periodEnd): ?array
    {
        global $pdo;

        if (!($pdo instanceof PDO) || !hrms_table_exists($pdo, 'employees')) {
            return null;
        }

        try {
            $employeeStmt = $pdo->prepare(
                'SELECT id, company_id, basic_salary
                 FROM employees
                 WHERE id = ? LIMIT 1'
            );
            $employeeStmt->execute([$employeeId]);
            $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$employee) {
                return null;
            }

            $basicSalary = (float)($employee['basic_salary'] ?? 0);
            $companyId = (int)($employee['company_id'] ?? 0);
            $workingDays = 22;
            $dailyRate = $workingDays > 0 ? $basicSalary / $workingDays : 0.0;
            $workedDays = 0;

            $components = [];
            $grossPay = max(0, $basicSalary);
            $totalEarnings = $grossPay;
            $totalDeductions = 0.0;

            $components[] = [
                'name' => 'Basic Salary',
                'type' => 'earning',
                'amount' => $grossPay,
            ];

            if (hrms_table_exists($pdo, 'employee_salary_components')) {
                $componentStmt = $pdo->prepare(
                    'SELECT esc.amount, sc.name, sc.component_type, sc.type, sc.value
                     FROM employee_salary_components esc
                     JOIN salary_components sc ON sc.id = esc.salary_component_id
                     WHERE esc.employee_id = ? AND sc.company_id = ?'
                );
                $componentStmt->execute([$employeeId, $companyId]);
                foreach ($componentStmt->fetchAll(PDO::FETCH_ASSOC) as $component) {
                    $amount = (float)($component['amount'] ?? 0);
                    $type = (string)($component['component_type'] ?? 'earning');
                    $components[] = [
                        'name' => (string)($component['name'] ?? 'Salary Component'),
                        'type' => $type,
                        'amount' => $amount,
                    ];

                    if ($type === 'deduction') {
                        $totalDeductions += $amount;
                    } else {
                        $totalEarnings += $amount;
                    }
                }
            }

            if (hrms_table_exists($pdo, 'attendance')) {
                $attendanceStmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM attendance
                     WHERE company_id = ? AND employee_id = ? AND date BETWEEN ? AND ?'
                );
                $attendanceStmt->execute([$companyId, $employeeId, $periodStart, $periodEnd]);
                $workedDays = (int)$attendanceStmt->fetchColumn();
                $components[] = [
                    'name' => 'Recorded Attendance Days',
                    'type' => 'meta',
                    'amount' => $workedDays,
                ];
            }

            return [
                'components' => $components,
                'gross_pay' => round($grossPay, 2),
                'days_worked' => $workedDays,
                'daily_rate' => round($dailyRate, 2),
                'total_earnings' => round($totalEarnings, 2),
                'total_deductions' => round($totalDeductions, 2),
                'net_pay' => round($totalEarnings - $totalDeductions, 2),
            ];
        } catch (Throwable $e) {
            error_log('Payroll calculation failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('calculate_13th_month')) {
    function calculate_13th_month(int $employeeId, int $year): ?array
    {
        global $pdo;

        if (!($pdo instanceof PDO) || !hrms_table_exists($pdo, 'employees')) {
            return null;
        }

        $cid = company_id() ?? 1;

        try {
            $emp = $pdo->prepare('SELECT basic_salary FROM employees WHERE id = ? AND company_id = ? LIMIT 1');
            $emp->execute([$employeeId, $cid]);
            $employee = $emp->fetch(PDO::FETCH_ASSOC);
            if (!$employee) {
                return null;
            }

            $basicSalary = (float)($employee['basic_salary'] ?? 0);
            $monthsWorked = 12;

            if (hrms_table_exists($pdo, 'attendance')) {
                $sal = $pdo->prepare(
                    "SELECT COUNT(DISTINCT MONTH(date)) AS months_worked
                     FROM attendance
                     WHERE company_id = ? AND employee_id = ? AND YEAR(date) = ?"
                );
                $sal->execute([$cid, $employeeId, $year]);
                $monthsWorked = max(1, (int)($sal->fetchColumn() ?: 0));
            }

            $totalBasicEarned = $basicSalary * ($monthsWorked / 12) * 12;
            $baseThirteenth = $totalBasicEarned / 12;
            $dailyRate = $basicSalary / 22;
            $absenceDeduction = 0.0;
            $unpaidDeduction = 0.0;

            if (hrms_table_exists($pdo, 'attendance_exceptions')) {
                $absence = $pdo->prepare(
                    "SELECT COUNT(*) FROM attendance_exceptions
                     WHERE company_id = ? AND employee_id = ? AND exception_type = 'absence'
                       AND YEAR(exception_date) = ?"
                );
                $absence->execute([$cid, $employeeId, $year]);
                $absenceDeduction = ((int)$absence->fetchColumn()) * $dailyRate;
            }

            if (hrms_table_exists($pdo, 'leave_requests') && hrms_table_exists($pdo, 'leave_types')) {
                $unpaidLeave = $pdo->prepare(
                    "SELECT COALESCE(SUM(DATEDIFF(lr.end_date, lr.start_date) + 1), 0)
                     FROM leave_requests lr
                     LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                     WHERE lr.company_id = ? AND lr.employee_id = ? AND lr.status = 'approved'
                       AND YEAR(lr.start_date) = ?
                       AND (lt.is_paid = 0 OR lt.is_paid IS NULL)"
                );
                $unpaidLeave->execute([$cid, $employeeId, $year]);
                $unpaidDeduction = ((float)$unpaidLeave->fetchColumn()) * $dailyRate;
            }

            $finalAmount = max(0, $baseThirteenth - $absenceDeduction - $unpaidDeduction);

            return [
                'total_basic_earned' => round($totalBasicEarned, 2),
                'thirteenth_month_amount' => round($baseThirteenth, 2),
                'less_absences' => round($absenceDeduction, 2),
                'less_unpaid_leave' => round($unpaidDeduction, 2),
                'final_amount' => round($finalAmount, 2),
                'months_worked' => $monthsWorked,
            ];
        } catch (Throwable $e) {
            error_log('13th month calculation failed: ' . $e->getMessage());
            return null;
        }
    }
}
