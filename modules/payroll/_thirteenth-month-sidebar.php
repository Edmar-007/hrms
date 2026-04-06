<?php
/**
 * 13th Month Sidebar View
 * Variables required: $pendingEmployees, $year, $allRecords, $totalFinalAmount
 */
?>
<!-- Compute for Employee -->
<?php if (!empty($pendingEmployees)): ?>
<div class="card">
    <div class="card-header"><h6 class="mb-0">Compute 13th Month</h6></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="compute">
            <div class="mb-3">
                <label class="form-label">Select Employee <span class="text-danger">*</span></label>
                <select class="form-select" name="employee_id" required>
                    <option value="">-- Select Employee --</option>
                    <?php foreach ($pendingEmployees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= e($emp['first_name'].' '.$emp['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-calculator me-2"></i>Compute
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Year Selector -->
<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Select Year</h6></div>
    <div class="card-body">
        <div class="row g-2">
            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
            <div class="col-6">
                <a href="?year=<?= $y ?>" class="btn btn-outline-secondary w-100 <?= $year === $y ? 'active' : '' ?>">
                    <?= $y ?>
                </a>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Summary</h6></div>
    <div class="card-body">
        <div class="stat-item mb-3">
            <div class="stat-label">Computed Employees</div>
            <div class="stat-value text-primary"><?= count($allRecords) ?></div>
        </div>
        <div class="stat-item mb-3">
            <div class="stat-label">Pending Employees</div>
            <div class="stat-value text-warning"><?= count($pendingEmployees) ?></div>
        </div>
        <div class="stat-item border-top pt-3">
            <div class="stat-label">Total Final Amount</div>
            <div class="stat-value text-success fw-bold">₱<?= number_format($totalFinalAmount, 2) ?></div>
        </div>
    </div>
</div>
