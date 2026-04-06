<div class="modal fade" id="createPackageModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header"><h5 class="modal-title">Create Compensation Package</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Package Name</label><input type="text" class="form-control" name="name" required placeholder="e.g., Entry Level, Senior"></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Base Salary (₱)</label><input type="number" class="form-control" name="base_salary" step="0.01" min="0" value="0"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Allowances (₱)</label><input type="number" class="form-control" name="allowances" step="0.01" min="0" value="0"></div>
                </div>
                <div class="mb-3"><label class="form-label">Benefits</label><textarea class="form-control" name="benefits" rows="2" placeholder="List benefits included"></textarea></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" placeholder="Brief description"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
        </form>
    </div></div>
</div>
