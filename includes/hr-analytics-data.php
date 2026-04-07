<?php
require_once __DIR__ . '/functions.php';

if (!function_exists('hr_analytics_parse_range')) {
    function hr_analytics_parse_range(string $range): string
    {
        $allowed = ['week', 'month', 'quarter', 'year'];
        return in_array($range, $allowed, true) ? $range : 'month';
    }
}

if (!function_exists('hr_analytics_date_window')) {
    function hr_analytics_date_window(string $range): array
    {
        $today = new DateTimeImmutable('today');

        return match ($range) {
            'week' => [
                $today->modify('monday this week')->format('Y-m-d'),
                $today->modify('sunday this week')->format('Y-m-d'),
            ],
            'quarter' => (function () use ($today): array {
                $month = (int)$today->format('n');
                $quarterStartMonth = ((int)floor(($month - 1) / 3) * 3) + 1;
                $start = $today->setDate((int)$today->format('Y'), $quarterStartMonth, 1);
                $end = $start->modify('+2 months')->modify('last day of this month');
                return [$start->format('Y-m-d'), $end->format('Y-m-d')];
            })(),
            'year' => [
                $today->setDate((int)$today->format('Y'), 1, 1)->format('Y-m-d'),
                $today->setDate((int)$today->format('Y'), 12, 31)->format('Y-m-d'),
            ],
            default => [
                $today->modify('first day of this month')->format('Y-m-d'),
                $today->modify('last day of this month')->format('Y-m-d'),
            ],
        };
    }
}

if (!function_exists('hr_analytics_safe_count')) {
    function hr_analytics_safe_count(PDO $pdo, string $sql, array $params = []): int
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('hr_analytics_build_dashboard')) {
    function hr_analytics_build_dashboard(PDO $pdo, int $companyId, string $range): array
    {
        [$startDate, $endDate] = hr_analytics_date_window($range);

        $activeEmployees = hr_analytics_safe_count(
            $pdo,
            "SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'",
            [$companyId]
        );

        $activeUsers = hr_analytics_safe_count(
            $pdo,
            "SELECT COUNT(*) FROM users WHERE company_id = ? AND is_active = 1",
            [$companyId]
        );

        $pendingLeaves = hr_analytics_safe_count(
            $pdo,
            "SELECT COUNT(*) FROM leave_requests WHERE company_id = ? AND status = 'pending'",
            [$companyId]
        );

        $pendingClaims = hr_analytics_safe_count(
            $pdo,
            "SELECT COUNT(*) FROM expense_claims WHERE company_id = ? AND status = 'pending'",
            [$companyId]
        );

        $attendanceRows = 0;
        try {
            $attendanceStmt = $pdo->prepare(
                "SELECT COUNT(DISTINCT employee_id) FROM attendance
                 WHERE company_id = ? AND date BETWEEN ? AND ?"
            );
            $attendanceStmt->execute([$companyId, $startDate, $endDate]);
            $attendanceRows = (int)$attendanceStmt->fetchColumn();
        } catch (Throwable $e) {
            $attendanceRows = 0;
        }

        $attendanceRate = $activeEmployees > 0
            ? round(($attendanceRows / $activeEmployees) * 100, 1)
            : 0.0;

        $headcountTrend = [];
        try {
            $trendStmt = $pdo->prepare(
                "SELECT DATE(created_at) AS bucket, COUNT(*) AS total
                 FROM employees
                 WHERE company_id = ? AND created_at BETWEEN ? AND ?
                 GROUP BY DATE(created_at)
                 ORDER BY DATE(created_at) ASC"
            );
            $trendStmt->execute([$companyId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            foreach ($trendStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $headcountTrend[] = [
                    'date' => (string)$row['bucket'],
                    'value' => (int)$row['total'],
                ];
            }
        } catch (Throwable $e) {
            $headcountTrend = [];
        }

        $departmentBreakdown = [];
        try {
            $departmentStmt = $pdo->prepare(
                "SELECT COALESCE(d.name, 'Unassigned') AS department, COUNT(*) AS total
                 FROM employees e
                 LEFT JOIN departments d ON d.id = e.department_id
                 WHERE e.company_id = ?
                 GROUP BY COALESCE(d.name, 'Unassigned')
                 ORDER BY total DESC, department ASC"
            );
            $departmentStmt->execute([$companyId]);
            foreach ($departmentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $departmentBreakdown[] = [
                    'department' => (string)$row['department'],
                    'value' => (int)$row['total'],
                ];
            }
        } catch (Throwable $e) {
            $departmentBreakdown = [];
        }

        return [
            'summary' => [
                'activeEmployees' => $activeEmployees,
                'activeUsers' => $activeUsers,
                'pendingLeaves' => $pendingLeaves,
                'pendingClaims' => $pendingClaims,
                'attendanceRate' => $attendanceRate,
            ],
            'trends' => [
                'headcount' => $headcountTrend,
            ],
            'breakdowns' => [
                'departments' => $departmentBreakdown,
            ],
            'window' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ],
        ];
    }
}
