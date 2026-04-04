<?php
function e($s) {
    return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8");
}

function redirect($p) {
    header("Location: " . BASE_URL . $p);
    exit;
}

function is_post() {
    return $_SERVER["REQUEST_METHOD"] === "POST";
}

function is_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function format_currency($amount) {
    $currency = $_SESSION['company']['currency'] ?? 'PHP';
    $symbols = ['PHP' => '₱', 'USD' => '$', 'EUR' => '€'];
    return ($symbols[$currency] ?? $currency . ' ') . number_format($amount, 2);
}

function format_date($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

function format_time($time) {
    return date('h:i A', strtotime($time));
}

function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}

function generate_employee_code($companyId) {
    global $pdo;
    $st = $pdo->prepare("SELECT COUNT(*) + 1 as next FROM employees WHERE company_id = ?");
    $st->execute([$companyId]);
    $next = $st->fetch()['next'];
    return 'EMP-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

function avatar_initials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return $initials ?: '?';
}

// ====================================
// PAYROLL & SALARY FUNCTIONS
// ====================================

function get_employee_salary_components($employeeId, $date = null) {
    global $pdo;
    $cid = company_id() ?? 1;
    $date = $date ?? date('Y-m-d');

    // Get custom components for the employee (effective on this date)
    $st = $pdo->prepare("
        SELECT * FROM employee_salary_components
        WHERE company_id = ? AND employee_id = ?
        AND effective_from <= ?
        AND (effective_to IS NULL OR effective_to >= ?)
        AND is_active = 1
        ORDER BY component_type DESC, name ASC
    ");
    $st->execute([$cid, $employeeId, $date, $date]);
    return $st->fetchAll();
}

function calculate_payroll($employeeId, $periodStart, $periodEnd) {
    global $pdo;
    $cid = company_id() ?? 1;

    try {
        $result = [];

        // Get employee with basic salary
        $emp = $pdo->prepare("SELECT basic_salary FROM employees WHERE id = ? AND company_id = ?");
        $emp->execute([$employeeId, $cid]);
        $employee = $emp->fetch();

        if (!$employee) return null;

        // Count working days (attendance records)
        $attn = $pdo->prepare("
            SELECT COUNT(*) as days_worked
            FROM attendance
            WHERE company_id = ? AND employee_id = ?
            AND date BETWEEN ? AND ?
        ");
        $attn->execute([$cid, $employeeId, $periodStart, $periodEnd]);
        $daysWorked = $attn->fetch()['days_worked'];

        $basicSalary = $employee['basic_salary'];
        $dailyRate = $basicSalary / 22; // Standard 22 working days/month
        $grossPay = $dailyRate * $daysWorked;

        $result['gross_pay'] = $grossPay;
        $result['days_worked'] = $daysWorked;
        $result['daily_rate'] = $dailyRate;
        $result['components'] = [];

        // Get employee custom components
        $components = get_employee_salary_components($employeeId, $periodStart);

        $totalEarnings = 0;
        $totalDeductions = 0;

        foreach ($components as $comp) {
            $amount = 0;

            if ($comp['type'] === 'percentage') {
                $amount = ($grossPay * $comp['value']) / 100;
            } else {
                $amount = $comp['value'];
            }

            $result['components'][] = [
                'name' => $comp['name'],
                'type' => $comp['component_type'],
                'amount' => $amount
            ];

            if ($comp['component_type'] === 'earning') {
                $totalEarnings += $amount;
            } else {
                $totalDeductions += $amount;
            }
        }

        $result['total_earnings'] = $totalEarnings;
        $result['total_deductions'] = $totalDeductions;
        $result['net_pay'] = $totalEarnings - $totalDeductions;

        return $result;
    } catch (Exception $e) {
        return null;
    }
}

function calculate_13th_month($employeeId, $year) {
    global $pdo;
    $cid = company_id() ?? 1;

    try {
        // Sum of all basic salaries earned in the year
        $sal = $pdo->prepare("
            SELECT
                SUM(e.basic_salary) / 12 as monthly_avg,
                COUNT(DISTINCT MONTH(a.date)) as months_worked
            FROM employees e
            LEFT JOIN attendance a ON a.employee_id = e.id
                AND YEAR(a.date) = ? AND a.company_id = ?
            WHERE e.id = ? AND e.company_id = ?
        ");
        $sal->execute([$year, $cid, $employeeId, $cid]);
        $data = $sal->fetch();

        // Basic 13th month = (basic salary) / 12, prorated by months worked
        $basicEarned = ($data['monthly_avg'] * 12 * $data['months_worked']) / 12;

        // Deductions: absences
        $absence = $pdo->prepare("
            SELECT COUNT(*) as absence_days FROM attendance_exceptions
            WHERE company_id = ? AND employee_id = ?
            AND exception_type = 'absence' YEAR(exception_date) = ? AND is_approved = 1
        ");
        $absence->execute([$cid, $employeeId, $year]);
        $absenceDays = $absence->fetch()['absence_days'];

        // Get daily rate for deduction calculation
        $emp = $pdo->prepare("SELECT basic_salary FROM employees WHERE id = ?");
        $emp->execute([$employeeId]);
        $basicSalary = $emp->fetch()['basic_salary'];
        $dailyRate = $basicSalary / 22;

        $absenceDeduction = $absenceDays * $dailyRate;

        // Unpaid leave deduction (if applicable)
        $unpaidLeave = $pdo->prepare("
            SELECT SUM(DATEDIFF(end_date, start_date) + 1) as unpaid_days
            FROM leave_requests lr
            JOIN leave_types lt ON lt.id = lr.leave_type_id
            WHERE lr.company_id = ? AND lr.employee_id = ?
            AND lr.status = 'approved' AND lt.is_paid = 0
            AND YEAR(lr.start_date) = ?
        ");
        $unpaidLeave->execute([$cid, $employeeId, $year]);
        $unpaidDays = $unpaidLeave->fetch()['unpaid_days'] ?? 0;
        $unpaidDeduction = $unpaidDays * $dailyRate;

        $finalAmount = ($basicEarned / 12) - $absenceDeduction - $unpaidDeduction;
        $finalAmount = max(0, $finalAmount); // No negative 13th month

        return [
            'total_basic_earned' => $basicEarned,
            'thirteenth_month_amount' => $basicEarned / 12,
            'less_absences' => $absenceDeduction,
            'less_unpaid_leave' => $unpaidDeduction,
            'final_amount' => $finalAmount,
            'months_worked' => $data['months_worked']
        ];
    } catch (Exception $e) {
        return null;
    }
}

function get_leave_balance($employeeId, $leaveTypeId, $year) {
    global $pdo;
    $cid = company_id() ?? 1;

    $st = $pdo->prepare("
        SELECT * FROM employee_leave_balance
        WHERE company_id = ? AND employee_id = ?
        AND leave_type_id = ? AND year = ?
    ");
    $st->execute([$cid, $employeeId, $leaveTypeId, $year]);
    $balance = $st->fetch();

    return $balance ?: null;
}

// ====================================
// SHIFT & ATTENDANCE FUNCTIONS
// ====================================

function get_employee_shift($employeeId, $date = null) {
    global $pdo;
    $cid = company_id() ?? 1;
    $date = $date ?? date('Y-m-d');

    $st = $pdo->prepare("
        SELECT s.* FROM shifts s
        JOIN shift_assignments sa ON sa.shift_id = s.id
        WHERE sa.company_id = ? AND sa.employee_id = ?
        AND sa.effective_from <= ?
        AND (sa.effective_to IS NULL OR sa.effective_to >= ?)
        AND s.is_active = 1
        LIMIT 1
    ");
    $st->execute([$cid, $employeeId, $date, $date]);
    return $st->fetch();
}

function calculate_working_days($startDate, $endDate, $excludeHolidays = true) {
    global $pdo;
    $cid = company_id() ?? 1;

    $start = strtotime($startDate);
    $end = strtotime($endDate);
    $count = 0;

    while ($start <= $end) {
        $dow = date('w', $start); // 0 = Sunday, 6 = Saturday

        // Skip weekends
        if ($dow != 0 && $dow != 6) {
            // Check if holiday
            $isHoliday = false;
            if ($excludeHolidays) {
                $h = $pdo->prepare("SELECT id FROM holidays WHERE company_id = ? AND holiday_date = ?");
                $h->execute([$cid, date('Y-m-d', $start)]);
                $isHoliday = (bool)$h->fetch();
            }

            if (!$isHoliday) {
                $count++;
            }
        }

        $start = strtotime('+1 day', $start);
    }

    return $count;
}

function is_holiday($date) {
    global $pdo;
    $cid = company_id() ?? 1;

    $st = $pdo->prepare("SELECT id FROM holidays WHERE company_id = ? AND holiday_date = ?");
    $st->execute([$cid, $date]);
    return (bool)$st->fetch();
}

function get_attendance_exception($employeeId, $date, $type = null) {
    global $pdo;
    $cid = company_id() ?? 1;

    $sql = "SELECT * FROM attendance_exceptions WHERE company_id = ? AND employee_id = ? AND exception_date = ?";
    $params = [$cid, $employeeId, $date];

    if ($type) {
        $sql .= " AND exception_type = ?";
        $params[] = $type;
    }

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetch();
}

