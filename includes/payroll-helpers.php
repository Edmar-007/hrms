<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

if (!function_exists('render_13th_month_modal')) {
    function render_13th_month_modal(array $rec): void
    {
        $totalDed = (float)($rec['less_absences'] ?? 0) + (float)($rec['less_unpaid_leave'] ?? 0);
        ?>
        <div class="modal fade" id="detailModal<?= (int)($rec['id'] ?? 0) ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= e(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? '')) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-sm">
                            <tr><td>Employee ID:</td><td><?= e($rec['employee_code'] ?? '') ?></td></tr>
                            <tr><td>Year:</td><td><?= e($rec['year'] ?? '') ?></td></tr>
                            <tr class="table-light"><td><strong>Total Basic Earned:</strong></td><td class="text-end"><strong><?= format_currency($rec['total_basic_earned'] ?? 0) ?></strong></td></tr>
                            <tr><td>Base 13th Month:</td><td class="text-end"><?= format_currency($rec['thirteenth_month_amount'] ?? 0) ?></td></tr>
                            <tr class="table-warning"><td>Less: Absences</td><td class="text-end text-danger">-<?= format_currency($rec['less_absences'] ?? 0) ?></td></tr>
                            <tr class="table-warning"><td>Less: Unpaid Leave</td><td class="text-end text-danger">-<?= format_currency($rec['less_unpaid_leave'] ?? 0) ?></td></tr>
                            <tr class="table-success"><td><strong>Total Deductions:</strong></td><td class="text-end"><strong>-<?= format_currency($totalDed) ?></strong></td></tr>
                            <tr class="table-success"><td><strong>Final Amount:</strong></td><td class="text-end"><strong><?= format_currency($rec['final_amount'] ?? 0) ?></strong></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('render_component_edit_modal')) {
    function render_component_edit_modal(array $comp): void
    {
        ?>
        <div class="modal fade" id="editModal<?= (int)($comp['id'] ?? 0) ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Component</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_component">
                            <input type="hidden" name="comp_id" value="<?= (int)($comp['id'] ?? 0) ?>">
                            <div class="mb-3">
                                <label class="form-label">Component Name</label>
                                <input type="text" class="form-control" name="name" value="<?= e($comp['name'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Value Type</label>
                                <select class="form-select" name="type" required>
                                    <option value="fixed" <?= (($comp['type'] ?? '') === 'fixed') ? 'selected' : '' ?>>Fixed Amount</option>
                                    <option value="percentage" <?= (($comp['type'] ?? '') === 'percentage') ? 'selected' : '' ?>>Percentage</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Value</label>
                                <input type="number" class="form-control" name="value" value="<?= e($comp['value'] ?? 0) ?>" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Order</label>
                                <input type="number" class="form-control" name="order_seq" value="<?= e($comp['order_seq'] ?? 0) ?>">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('render_components_table')) {
    function render_components_table(array $components, string $type): void
    {
        $title = $type === 'earning' ? 'Earnings' : 'Deductions';
        ?>
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><?= e($title) ?></h5></div>
            <div class="card-body">
                <?php if (!$components): ?>
                    <p class="text-muted mb-0">No <?= strtolower($title) ?> configured.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Name</th><th>Type</th><th>Value</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($components as $comp): ?>
                                    <tr>
                                        <td><?= e($comp['name'] ?? '') ?></td>
                                        <td><?= e(ucfirst((string)($comp['type'] ?? 'fixed'))) ?></td>
                                        <td>
                                            <?php if (($comp['type'] ?? 'fixed') === 'percentage'): ?>
                                                <?= e($comp['value'] ?? 0) ?>%
                                            <?php else: ?>
                                                <?= format_currency($comp['value'] ?? 0) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)($comp['id'] ?? 0) ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('render_payroll_styles')) {
    function render_payroll_styles(): void
    {
        ?>
        <style>
        .stat-item { text-align: center; }
        .stat-label { color: #6c757d; font-size: 0.85rem; text-transform: uppercase; }
        .stat-value { color: #495057; font-size: 1.5rem; font-weight: 700; }
        .formula-box { border-left: 3px solid #0d6efd; font-family: Consolas, monospace; }
        .empty-state { color: #6c757d; padding: 2rem; text-align: center; }
        </style>
        <?php
    }
}

if (!function_exists('url')) {
    function url(string $path): string
    {
        return BASE_URL . $path;
    }
}

if (!function_exists('save_13th_month_record')) {
    function save_13th_month_record(PDO $pdo, int $cid, int $employeeId, int $year): string|bool
    {
        $calc = calculate_13th_month($employeeId, $year);
        if (!$calc) {
            return 'Could not calculate 13th month pay';
        }

        try {
            $st = $pdo->prepare(
                'INSERT INTO thirteenth_month_records
                (company_id, employee_id, year, total_basic_earned, thirteenth_month_amount,
                 less_absences, less_unpaid_leave, final_amount, computation_date, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "draft")
                 ON DUPLICATE KEY UPDATE
                    total_basic_earned = VALUES(total_basic_earned),
                    thirteenth_month_amount = VALUES(thirteenth_month_amount),
                    less_absences = VALUES(less_absences),
                    less_unpaid_leave = VALUES(less_unpaid_leave),
                    final_amount = VALUES(final_amount),
                    computation_date = VALUES(computation_date)'
            );
            $st->execute([
                $cid,
                $employeeId,
                $year,
                $calc['total_basic_earned'],
                $calc['thirteenth_month_amount'],
                $calc['less_absences'],
                $calc['less_unpaid_leave'],
                $calc['final_amount'],
                date('Y-m-d'),
            ]);
            return true;
        } catch (Throwable $e) {
            return 'Database error: ' . $e->getMessage();
        }
    }
}

if (!function_exists('finalize_13th_month_record')) {
    function finalize_13th_month_record(PDO $pdo, int $recordId, int $cid): void
    {
        $st = $pdo->prepare('UPDATE thirteenth_month_records SET status = "finalized" WHERE id = ? AND company_id = ?');
        $st->execute([$recordId, $cid]);
    }
}

if (!function_exists('get_13th_month_records')) {
    function get_13th_month_records(PDO $pdo, int $cid, int $year): array
    {
        $st = $pdo->prepare(
            'SELECT tm.*, e.first_name, e.last_name, e.employee_code
             FROM thirteenth_month_records tm
             JOIN employees e ON e.id = tm.employee_id
             WHERE tm.company_id = ? AND tm.year = ?
             ORDER BY e.last_name, e.first_name'
        );
        $st->execute([$cid, $year]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('get_pending_employees_for_13th')) {
    function get_pending_employees_for_13th(PDO $pdo, int $cid, int $year): array
    {
        $st = $pdo->prepare(
            'SELECT e.id, e.first_name, e.last_name, e.employee_code
             FROM employees e
             WHERE e.company_id = ? AND e.status = "active"
               AND e.id NOT IN (
                   SELECT DISTINCT employee_id
                   FROM thirteenth_month_records
                   WHERE company_id = ? AND year = ?
               )
             ORDER BY e.last_name, e.first_name'
        );
        $st->execute([$cid, $cid, $year]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('get_salary_structures')) {
    function get_salary_structures(PDO $pdo, int $cid, ?int $structId = null): array|null
    {
        if ($structId !== null) {
            $st = $pdo->prepare(
                'SELECT ss.*, 0 AS component_count, 0 AS employee_count
                 FROM salary_structures ss
                 WHERE ss.company_id = ? AND ss.id = ?
                 LIMIT 1'
            );
            $st->execute([$cid, $structId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        $st = $pdo->prepare(
            'SELECT ss.*, COUNT(sc.id) AS component_count, 0 AS employee_count
             FROM salary_structures ss
             LEFT JOIN salary_components sc ON sc.salary_structure_id = ss.id
             WHERE ss.company_id = ?
             GROUP BY ss.id
             ORDER BY ss.created_at DESC'
        );
        $st->execute([$cid]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('get_salary_components')) {
    function get_salary_components(PDO $pdo, int $cid, ?int $structId = null): array
    {
        if ($structId === null) {
            return [];
        }

        $st = $pdo->prepare(
            'SELECT *
             FROM salary_components
             WHERE company_id = ? AND salary_structure_id = ?
             ORDER BY component_type DESC, order_seq ASC, name ASC'
        );
        $st->execute([$cid, $structId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
