<?php
$isCreateAttempt = ($_POST['action'] ?? '') === 'create';
$claimDateValue = $isCreateAttempt ? (string)($_POST['claim_date'] ?? date('Y-m-d')) : date('Y-m-d');
$selectedCategory = $isCreateAttempt ? trim((string)($_POST['category'] ?? '')) : '';
$selectedEmployeeId = $isCreateAttempt ? (int)($_POST['employee_id'] ?? 0) : (int)($linkedEmployeeId ?? 0);
$amountValue = $isCreateAttempt ? (string)($_POST['amount'] ?? '') : '';
$descriptionValue = $isCreateAttempt ? trim((string)($_POST['description'] ?? '')) : '';
?>
<div class="modal fade" id="createClaimModal" tabindex="-1" aria-labelledby="createClaimModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header"><h5 class="modal-title" id="createClaimModalLabel">Submit Expense Claim</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <?php if($isApprover): ?>
                <div class="mb-3">
                    <label class="form-label">Employee</label>
                    <select class="form-select" name="employee_id" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $selectedEmployeeId === (int)$emp['id'] ? 'selected' : '' ?>><?= e($emp['last_name'].', '.$emp['first_name'].' ('.$emp['employee_code'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <?php
                $empStmt = $pdo->prepare("SELECT employee_id FROM users WHERE id = ?");
                $empStmt->execute([$uid]);
                $myEmpId = $empStmt->fetchColumn();
                ?>
                <input type="hidden" name="employee_id" value="<?= $selectedEmployeeId ?: (int)$myEmpId ?>">
                <?php endif; ?>
                
                <div class="mb-3"><label class="form-label">Date of Expense</label><input type="date" class="form-control" name="claim_date" value="<?= e($claimDateValue) ?>" required></div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach($categories as $cat): ?><option value="<?= e($cat) ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Amount (₱)</label><input type="number" class="form-control" name="amount" step="0.01" min="0.01" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" placeholder="Brief description"><?= e($descriptionValue) ?></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Submit Claim</button></div>
        </form>
    </div></div>
</div>
<?php if ($isCreateAttempt): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('createClaimModal');
    const form = modalEl?.querySelector('form');
    if (!modalEl || !form) {
        return;
    }

    const amountInput = form.querySelector('[name="amount"]');
    if (amountInput) {
        amountInput.value = <?= json_encode($amountValue) ?>;
    }

    <?php if ($err): ?>
    if (typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
    <?php endif; ?>
});
</script>
<?php endif; ?>
