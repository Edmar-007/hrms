<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer']);

// Get month filter
$month = $_GET['month'] ?? date('Y-m');

// Check SaaS mode
$hasSaas = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

// Get employees with salary info (use prepared statement for safety)
if($hasSaas && $cid) {
    $st = $pdo->prepare("SELECT e.*, d.name as dept_name, p.name as pos_name,
        (SELECT COUNT(*) FROM attendance a WHERE a.employee_id=e.id AND DATE_FORMAT(a.date,'%Y-%m')=?) as days_worked
        FROM employees e 
        LEFT JOIN departments d ON d.id=e.department_id 
        LEFT JOIN positions p ON p.id=e.position_id 
        WHERE e.company_id=? AND e.status='active'
        ORDER BY e.last_name, e.first_name");
    $st->execute([$month, $cid]);
    $rows = $st->fetchAll();
} else {
    $st = $pdo->prepare("SELECT e.*, d.name as dept_name, p.name as pos_name,
        (SELECT COUNT(*) FROM attendance a WHERE a.employee_id=e.id AND DATE_FORMAT(a.date,'%Y-%m')=?) as days_worked
        FROM employees e 
        LEFT JOIN departments d ON d.id=e.department_id 
        LEFT JOIN positions p ON p.id=e.position_id 
        WHERE e.status='active'
        ORDER BY e.last_name, e.first_name");
    $st->execute([$month]);
    $rows = $st->fetchAll();
}

$totalPayroll = 0;
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-cash-stack me-2"></i>Payroll</h4>
    <form class="d-flex gap-2" method="get">
        <input type="month" class="form-control" name="month" value="<?= e($month) ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i></button>
    </form>
</div>

<div class="card">
    <!-- Toolbar -->
    <div class="table-toolbar">
        <div class="input-group" style="max-width:280px;">
            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="paySearch" class="form-control border-start-0 ps-0" placeholder="Search employee…">
        </div>
        <span class="badge bg-primary table-count-badge" id="payCount"><?= count($rows) ?> employees</span>
    </div>

    <div class="table-responsive-wrapper">
        <table class="table table-hover mb-0" id="payTable">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Basic Salary</th>
                    <th>Days Worked</th>
                    <th>Deductions</th>
                    <th>Net Pay</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-cash-stack fs-2 d-block mb-2 opacity-25"></i>No employees found
                </td></tr>
            <?php else: foreach($rows as $idx => $r): 
                $workingDays = 22;
                $dailyRate = $r['basic_salary'] / $workingDays;
                $grossPay = $dailyRate * $r['days_worked'];
                $deductions = $grossPay * 0.1;
                $netPay = $grossPay - $deductions;
                $totalPayroll += $netPay;
                $colors = ['#6366f1','#10b981','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#ec4899'];
                $color = $colors[$idx % count($colors)];
            ?>
                <tr data-search="<?= strtolower(e($r['first_name'].' '.$r['last_name'].' '.($r['dept_name']??''))) ?>">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-sm" style="background:<?= $color ?>;">
                                <?= strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold"><?= e($r['first_name'].' '.$r['last_name']) ?></div>
                                <small class="text-muted"><?= e($r['pos_name'] ?? '—') ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?= e($r['dept_name'] ?? '—') ?></td>
                    <td class="fw-semibold">₱<?= number_format($r['basic_salary'], 2) ?></td>
                    <td><span class="badge bg-info bg-opacity-75 text-dark"><i class="bi bi-calendar-check me-1"></i><?= $r['days_worked'] ?> days</span></td>
                    <td class="text-danger fw-semibold">−₱<?= number_format($deductions, 2) ?></td>
                    <td class="fw-bold text-success">₱<?= number_format($netPay, 2) ?></td>
                    <td class="action-btns text-center">
                        <a href="payslip.php?id=<?= $r['id'] ?>&month=<?= e($month) ?>" class="btn btn-sm btn-primary" title="View Payslip" data-bs-toggle="tooltip">
                            <i class="bi bi-file-earmark-text me-1"></i>Payslip
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--dark);color:white;">
                    <td colspan="5" class="text-end fw-bold py-3">Total Payroll:</td>
                    <td colspan="2" class="fw-bold text-success py-3">₱<?= number_format($totalPayroll, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
const paySearch = document.getElementById('paySearch');
const payCount  = document.getElementById('payCount');
if (paySearch) {
    paySearch.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        let v = 0;
        document.querySelectorAll('#payTable tbody tr[data-search]').forEach(row => {
            const m = !q || row.dataset.search.includes(q);
            row.style.display = m ? '' : 'none';
            if (m) v++;
        });
        payCount.textContent = v + ' employees';
    });
}
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>

<div class="row mt-4">
    <div class="col-md-4">
        <div class="stat-card primary">
            <h6><i class="bi bi-people me-2"></i>Active Employees</h6>
            <h2><?= count($rows) ?></h2>
            <i class="bi bi-people-fill icon"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card success">
            <h6><i class="bi bi-cash me-2"></i>Total Payroll</h6>
            <h2>₱<?= number_format($totalPayroll, 0) ?></h2>
            <i class="bi bi-cash-stack icon"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card info">
            <h6><i class="bi bi-calendar me-2"></i>Period</h6>
            <h2><?= date('M Y', strtotime($month.'-01')) ?></h2>
            <i class="bi bi-calendar3 icon"></i>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
