<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    exit(json_encode(["error" => "Unauthorized"]));
}

require_role(['Admin', 'HR Officer']);

$action = $_GET['action'] ?? 'calculate';
$cid = company_id() ?? 1;

try {
    if ($action === 'process') {
        // Process payroll for a given month
        $month = $_POST['month'] ?? date('Y-m');
        $processedCount = 0;
        $errors = [];

        // Parse month
        $parts = explode('-', $month);
        if (count($parts) !== 2) {
            exit(json_encode(["error" => "Invalid month format. Use YYYY-MM"]));
        }

        $year = (int)$parts[0];
        $monthNum = (int)$parts[1];
        $periodStart = "$year-" . str_pad($monthNum, 2, '0', STR_PAD_LEFT) . "-01";
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        // Get all active employees
        $empStmt = $pdo->prepare("
            SELECT e.id, e.first_name, e.last_name, e.basic_salary
            FROM employees e
            WHERE e.company_id = ? AND e.status = 'active'
        ");
        $empStmt->execute([$cid]);
        $employees = $empStmt->fetchAll();

        foreach ($employees as $emp) {
            try {
                $empId = $emp['id'];

                // Check if payroll already processed for this period
                $check = $pdo->prepare("
                    SELECT id FROM payroll_records
                    WHERE company_id = ? AND employee_id = ?
                    AND payroll_period_start = ? AND payroll_period_end = ?
                ");
                $check->execute([$cid, $empId, $periodStart, $periodEnd]);
                if ($check->fetch()) {
                    continue; // Already processed
                }

                // Use helper function to calculate payroll
                $payrollData = calculate_payroll($empId, $periodStart, $periodEnd);

                if (!$payrollData) {
                    $errors[] = "Could not calculate payroll for {$emp['first_name']} {$emp['last_name']}";
                    continue;
                }

                // Insert payroll record
                $pInsert = $pdo->prepare("
                    INSERT INTO payroll_records
                    (company_id, employee_id, payroll_period_start, payroll_period_end,
                     total_earnings, total_deductions, net_pay, status, processed_by, processed_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'processed', ?, NOW())
                ");
                $pInsert->execute([
                    $cid, $empId, $periodStart, $periodEnd,
                    $payrollData['total_earnings'],
                    $payrollData['total_deductions'],
                    $payrollData['net_pay'],
                    $_SESSION['user']['id']
                ]);

                $payrollId = $pdo->lastInsertId();

                // Insert payroll items (earnings and deductions)
                $itemInsert = $pdo->prepare("
                    INSERT INTO payroll_items
                    (payroll_record_id, component_name, component_type, amount)
                    VALUES (?, ?, ?, ?)
                ");

                foreach ($payrollData['components'] as $comp) {
                    $itemInsert->execute([
                        $payrollId,
                        $comp['name'],
                        $comp['type'],
                        $comp['amount']
                    ]);
                }

                // Deduct leave from balances (if applicable)
                // Get approved leaves for this period
                $leaves = $pdo->prepare("
                    SELECT lr.leave_type_id,
                           COUNT(DISTINCT DATE(lr.start_date)) as days_used
                    FROM leave_requests lr
                    WHERE lr.company_id = ? AND lr.employee_id = ?
                    AND lr.status = 'approved'
                    AND lr.start_date <= ? AND lr.end_date >= ?
                    GROUP BY lr.leave_type_id
                ");
                $leaves->execute([$cid, $empId, $periodEnd, $periodStart]);
                $leaveData = $leaves->fetchAll();

                foreach ($leaveData as $leave) {
                    $updBalance = $pdo->prepare("
                        UPDATE employee_leave_balance
                        SET used = used + ?
                        WHERE company_id = ? AND employee_id = ?
                        AND leave_type_id = ? AND year = ?
                    ");
                    $updBalance->execute([
                        $leave['days_used'],
                        $cid, $empId, $leave['leave_type_id'], $year
                    ]);
                }

                $processedCount++;
                log_activity('process_payroll', 'payroll_record', $payrollId, [
                    'month' => $month,
                    'earnings' => $payrollData['total_earnings'],
                    'deductions' => $payrollData['total_deductions']
                ]);

            } catch (Exception $e) {
                $errors[] = "Error processing {$emp['first_name']} {$emp['last_name']}: " . $e->getMessage();
            }
        }

        exit(json_encode([
            "success" => true,
            "message" => "Payroll processed successfully",
            "processed_count" => $processedCount,
            "errors" => $errors,
            "period" => $month
        ]));
    }

    if ($action === 'get_payroll_data') {
        // Get payroll summary for a month
        $month = $_GET['month'] ?? date('Y-m');

        $parts = explode('-', $month);
        $year = (int)$parts[0];
        $monthNum = (int)$parts[1];
        $periodStart = "$year-" . str_pad($monthNum, 2, '0', STR_PAD_LEFT) . "-01";
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        $stmt = $pdo->prepare("
            SELECT pr.*,
                   e.first_name, e.last_name, e.employee_code,
                   d.name as dept_name
            FROM payroll_records pr
            JOIN employees e ON e.id = pr.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE pr.company_id = ? AND pr.payroll_period_start = ?
            ORDER BY e.last_name, e.first_name
        ");
        $stmt->execute([$cid, $periodStart]);
        $payrolls = $stmt->fetchAll();

        // Summary stats
        $totalEarnings = array_sum(array_column($payrolls, 'total_earnings'));
        $totalDeductions = array_sum(array_column($payrolls, 'total_deductions'));
        $totalNetPay = array_sum(array_column($payrolls, 'net_pay'));

        exit(json_encode([
            "success" => true,
            "period" => $month,
            "payrolls" => $payrolls,
            "stats" => [
                "total_earnings" => $totalEarnings,
                "total_deductions" => $totalDeductions,
                "total_net_pay" => $totalNetPay,
                "employee_count" => count($payrolls)
            ]
        ]));
    }

} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode([
        "error" => $e->getMessage()
    ]));
}
?>
