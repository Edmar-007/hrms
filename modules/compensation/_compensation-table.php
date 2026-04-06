<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Package Name</th><th>Base Salary</th><th>Allowances</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($packages)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No compensation packages defined.</td></tr>
            <?php else: ?>
                <?php foreach($packages as $p): $total = $p['base_salary'] + $p['allowances']; ?>
                <tr>
                    <td><strong><?= e($p['name']) ?></strong><?php if($p['description']): ?><br><small class="text-muted"><?= e($p['description']) ?></small><?php endif; ?></td>
                    <td>₱<?= number_format($p['base_salary'], 2) ?></td>
                    <td>₱<?= number_format($p['allowances'], 2) ?></td>
                    <td class="fw-bold">₱<?= number_format($total, 2) ?></td>
                    <td><?= $p['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPackageModal<?= $p['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this package?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach($packages as $p): ?>
<div class="modal fade" id="editPackageModal<?= $p['id'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <div class="modal-header"><h5 class="modal-title">Edit: <?= e($p['name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Package Name</label><input type="text" class="form-control" name="name" value="<?= e($p['name']) ?>" required></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Base Salary (₱)</label><input type="number" class="form-control" name="base_salary" value="<?= $p['base_salary'] ?>" step="0.01" min="0"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Allowances (₱)</label><input type="number" class="form-control" name="allowances" value="<?= $p['allowances'] ?>" step="0.01" min="0"></div>
                </div>
                <div class="mb-3"><label class="form-label">Benefits</label><textarea class="form-control" name="benefits" rows="2"><?= e($p['benefits'] ?? '') ?></textarea></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?= e($p['description'] ?? '') ?></textarea></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="active<?= $p['id'] ?>" <?= $p['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="active<?= $p['id'] ?>">Active</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>
<?php endforeach; ?>
