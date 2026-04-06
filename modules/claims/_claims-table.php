<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <?php if($isApprover): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($claims)): ?>
                <tr><td colspan="<?= $isApprover ? 7 : 6 ?>" class="text-center text-muted py-4">No expense claims found.</td></tr>
            <?php else: ?>
                <?php foreach($claims as $c): ?>
                <tr>
                    <td><?= date('M j, Y', strtotime($c['claim_date'])) ?></td>
                    <td><?= e($c['first_name'].' '.$c['last_name']) ?> <small class="text-muted">(<?= e($c['employee_code']) ?>)</small></td>
                    <td><?= e($c['category']) ?></td>
                    <td><?= e($c['description'] ?: '-') ?></td>
                    <td class="fw-semibold">₱<?= number_format($c['amount'], 2) ?></td>
                    <td>
                        <?php if($c['status'] === 'approved'): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php elseif($c['status'] === 'rejected'): ?>
                            <span class="badge bg-danger">Rejected</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php endif; ?>
                    </td>
                    <?php if($isApprover): ?>
                    <td>
                        <?php if($c['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $c['id'] ?>" title="Approve"><i class="bi bi-check-lg"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $c['id'] ?>" title="Reject"><i class="bi bi-x-lg"></i></button>
                        <?php else: ?>
                        <span class="text-muted small"><?= $c['remarks'] ? e($c['remarks']) : '-' ?></span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php if($isApprover): ?>
<?php foreach($claims as $c): if($c['status'] === 'pending'): ?>
<div class="modal fade" id="approveModal<?= $c['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <div class="modal-header bg-success text-white"><h5 class="modal-title">Approve Claim</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p>Approve expense of <strong>₱<?= number_format($c['amount'], 2) ?></strong> for <strong><?= e($c['first_name'].' '.$c['last_name']) ?></strong>?</p>
                <div class="mb-3"><label class="form-label">Remarks (optional)</label><textarea class="form-control" name="remarks" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Approve</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="rejectModal<?= $c['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <div class="modal-header bg-danger text-white"><h5 class="modal-title">Reject Claim</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p>Reject expense of <strong>₱<?= number_format($c['amount'], 2) ?></strong> for <strong><?= e($c['first_name'].' '.$c['last_name']) ?></strong>?</p>
                <div class="mb-3"><label class="form-label">Reason</label><textarea class="form-control" name="remarks" rows="2" required></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Reject</button></div>
        </form>
    </div></div>
</div>
<?php endif; endforeach; ?>
<?php endif; ?>
