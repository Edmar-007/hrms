<?php
/**
 * Payroll Helper Functions
 * Contains helper functions for payroll-related calculations and operations
 */

/**
 * Render a 13th month detail modal
 */
function render_13th_month_modal($rec) {
    $totalDed = $rec['less_absences'] + $rec['less_unpaid_leave'];
    ?>
    <div class="modal fade" id="detailModal<?= $rec['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= e($rec['first_name'].' '.$rec['last_name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="computation-breakdown">
                        <table class="table table-sm">
                            <tr>
                                <td>Employee ID:</td>
                                <td><?= e($rec['employee_code']) ?></td>
                            </tr>
                            <tr>
                                <td>Year:</td>
                                <td><?= $rec['year'] ?></td>
                            </tr>
                            <tr class="table-light">
                                <td><strong>Total Basic Earned:</strong></td>
                                <td class="text-end"><strong>₱<?= number_format($rec['total_basic_earned'], 2) ?></strong></td>
                            </tr>
                            <tr>
                                <td>÷ 12 months =</td>
                                <td class="text-end">₱<?= number_format($rec['thirteenth_month_amount'], 2) ?></td>
                            </tr>
                            <tr class="table-warning">
                                <td>Less: Absences</td>
                                <td class="text-end text-danger">-₱<?= number_format($rec['less_absences'], 2) ?></td>
                            </tr>
                            <tr class="table-warning">
                                <td>Less: Unpaid Leave</td>
                                <td class="text-end text-danger">-₱<?= number_format($rec['less_unpaid_leave'], 2) ?></td>
                            </tr>
                            <tr class="table-success">
                                <td><strong>Final Payable Amount:</strong></td>
                                <td class="text-end"><strong class="text-success">₱<?= number_format($rec['final_amount'], 2) ?></strong></td>
                            </tr>
                        </table>
                    </div>
                    <div class="mt-3 pt-3 border-top small text-muted">
                        <strong>Computed on:</strong> <?= date('M d, Y', strtotime($rec['computation_date'])) ?>
                        <br>
                        <strong>Status:</strong> <span class="badge <?= $rec['status'] === 'finalized' ? 'bg-success' : 'bg-warning' ?>">
                            <?= ucfirst($rec['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render a component edit modal
 */
function render_component_edit_modal($comp) {
    ?>
    <div class="modal fade" id="editModal<?= $comp['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Component</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="update_component">
                        <input type="hidden" name="comp_id" value="<?= $comp['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Component Name</label>
                            <input type="text" class="form-control" name="name" value="<?= e($comp['name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Value Type</label>
                            <select class="form-select" name="type" required>
                                <option value="fixed" <?= $comp['type'] === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                                <option value="percentage" <?= $comp['type'] === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Value</label>
                            <input type="number" class="form-control" name="value" value="<?= $comp['value'] ?>" step="0.01" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Order (Priority)</label>
                            <input type="number" class="form-control" name="order_seq" value="<?= $comp['order_seq'] ?>">
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

/**
 * Render components table
 */
function render_components_table($components, $type) {
    $bgClass = $type === 'earning' ? 'bg-success' : 'bg-danger';
    $icon = $type === 'earning' ? 'plus-circle' : 'dash-circle';
    $title = $type === 'earning' ? 'Earnings' : 'Deductions';
    ?>
    <div class="card mb-4">
        <div class="card-header <?= $bgClass ?> bg-opacity-10">
            <h5 class="mb-0"><i class="bi bi-<?= $icon ?> text-<?= $type === 'earning' ? 'success' : 'danger' ?> me-2"></i><?= $title ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($components)): ?>
            <p class="text-muted mb-0">No <?= strtolower($title) ?> configured</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th style="width:120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($components as $comp): ?>
                        <tr>
                            <td><?= e($comp['name']) ?></td>
                            <td><span class="badge bg-info"><?= ucfirst($comp['type']) ?></span></td>
                            <td>
                                <?= $comp['type'] === 'percentage' ? $comp['value'].'%' : '₱'.number_format($comp['value'], 2) ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#editModal<?= $comp['id'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this component?')">
                                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete_component">
                                    <input type="hidden" name="comp_id" value="<?= $comp['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0" style="margin-left:8px">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
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

/**
 * Render common payroll styles
 */
function render_payroll_styles() {
    ?>
    <style>
    .stat-item {
        text-align: center;
    }
    .stat-label {
        font-size: 0.85rem;
        color: #6c757d;
        text-transform: uppercase;
    }
    .stat-value {
        font-size: 2rem;
        font-weight: bold;
        color: #495057;
    }
    .formula-box {
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        border-left: 3px solid #0d6efd;
    }
    .computation-breakdown table tr {
        font-size: 0.9rem;
    }
    .stats-row {
        display: flex;
        gap: 1rem;
    }
    .stats-row .stat-item {
        flex: 1;
    }
    .stats-row .stat-value {
        font-size: 1.5rem;
    }
    .stats-row .stat-label {
        font-size: 0.75rem;
    }
    </style>
    <?php
}

/**
 * Get URL with BASE_URL prefix
 */
function url($path) {
    return BASE_URL . $path;
}

// ====================================
// DATABASE HELPER FUNCTIONS
// ====================================

/**
 * Save 13th month record for an employee
 */
function save_13th_month_record($pdo, $cid, $employeeId, $year) {
    $calc = calculate_13th_month($employeeId, $year);
    if (!$calc) return 'Could not calculate 13th month pay';

    try {
        $st = $pdo->prepare("
            INSERT INTO thirteenth_month_records
            (company_id, employee_id, year, total_basic_earned, thirteenth_month_amount,
             less_absences, less_unpaid_leave, final_amount, computation_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
            ON DUPLICATE KEY UPDATE
            total_basic_earned = VALUES(total_basic_earned),
            thirteenth_month_amount = VALUES(thirteenth_month_amount),
            less_absences = VALUES(less_absences),
            less_unpaid_leave = VALUES(less_unpaid_leave),
            final_amount = VALUES(final_amount),
            computation_date = VALUES(computation_date)
        ");
        $st->execute([
            $cid, $employeeId, $year,
            $calc['total_basic_earned'], $calc['thirteenth_month_amount'],
            $calc['less_absences'], $calc['less_unpaid_leave'],
            $calc['final_amount'], date('Y-m-d')
        ]);

        log_activity('compute_13th_month', 'thirteenth_month_records', $employeeId,
            ['year' => $year, 'amount' => $calc['final_amount']]);
        return true;
    } catch (Exception $e) {
        return 'Database error: ' . $e->getMessage();
    }
}

/**
 * Finalize a 13th month record
 */
function finalize_13th_month_record($pdo, $recordId, $cid) {
    $st = $pdo->prepare("UPDATE thirteenth_month_records SET status = 'finalized' WHERE id = ? AND company_id = ?");
    $st->execute([$recordId, $cid]);
}

/**
 * Get all 13th month records for a year
 */
function get_13th_month_records($pdo, $cid, $year) {
    $st = $pdo->prepare("
        SELECT tm.*, e.first_name, e.last_name, e.employee_code
        FROM thirteenth_month_records tm
        JOIN employees e ON e.id = tm.employee_id
        WHERE tm.company_id = ? AND tm.year = ?
        ORDER BY e.last_name, e.first_name
    ");
    $st->execute([$cid, $year]);
    return $st->fetchAll();
}

/**
 * Get employees not yet computed for 13th month
 */
function get_pending_employees_for_13th($pdo, $cid, $year) {
    $st = $pdo->prepare("
        SELECT e.id, e.first_name, e.last_name, e.employee_code
        FROM employees e
        WHERE e.company_id = ? AND e.status = 'active'
        AND e.id NOT IN (SELECT DISTINCT employee_id FROM thirteenth_month_records WHERE company_id = ? AND year = ?)
        ORDER BY e.last_name, e.first_name
    ");
    $st->execute([$cid, $cid, $year]);
    return $st->fetchAll();
}

/**
 * Get all salary structures
 */
function get_salary_structures($pdo, $cid) {
    $st = $pdo->prepare("
        SELECT ss.*, COUNT(sc.id) as component_count
        FROM salary_structures ss
        LEFT JOIN salary_components sc ON sc.salary_structure_id = ss.id
        WHERE ss.company_id = ?
        GROUP BY ss.id ORDER BY ss.created_at DESC
    ");
    $st->execute([$cid]);
    return $st->fetchAll();
}

/**
 * Get salary components for a structure
 */
function get_salary_components($pdo, $cid, $structId) {
    $st = $pdo->prepare("
        SELECT * FROM salary_components
        WHERE company_id = ? AND salary_structure_id = ?
        ORDER BY component_type DESC, order_seq ASC, name ASC
    ");
    $st->execute([$cid, $structId]);
    return $st->fetchAll();
}
