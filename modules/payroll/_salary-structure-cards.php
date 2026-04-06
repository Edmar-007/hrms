<?php
/**
 * Salary Structure Cards View
 * Variables required: $allStructures
 */
?>
<div class="row">
    <?php if (empty($allStructures)): ?>
    <div class="col-md-8">
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <p>No salary structures defined yet</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                Create First Structure
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="col-12">
        <div class="row g-3">
            <?php foreach ($allStructures as $s): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0"><?= e($s['name']) ?></h6>
                            <span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <p class="text-muted small mb-3"><?= e($s['description']) ?></p>
                        <div class="stats-row mb-3">
                            <div class="stat-item">
                                <div class="stat-value"><?= $s['component_count'] ?></div>
                                <div class="stat-label">Components</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $s['employee_count'] ?></div>
                                <div class="stat-label">Employees</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= url('/modules/payroll/salary-components.php?struct_id=' . $s['id']) ?>" class="btn btn-sm btn-info flex-grow-1">
                                <i class="bi bi-gear me-1"></i>Configure
                            </a>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-<?= $s['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
