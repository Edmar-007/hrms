<?php
/**
 * 13th Month Records Table View
 * Variables required: $allRecords, $year, $totalFinalAmount
 */
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Computed 13th Month Pay (<?= count($allRecords) ?> employees)</h5>
        <span class="badge bg-primary"><?= $year ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($allRecords)): ?>
        <div class="empty-state p-4 text-center">
            <i class="bi bi-inbox"></i>
            <p>No 13th month pay computed yet for <?= $year ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Months Worked</th>
                        <th class="text-end">Basic Earned</th>
                        <th class="text-end">13th Month</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Final Amount</th>
                        <th>Status</th>
                        <th style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allRecords as $rec):
                        $totalDed = $rec['less_absences'] + $rec['less_unpaid_leave'];
                    ?>
                    <tr>
                        <td>
                            <div><?= e($rec['first_name'].' '.$rec['last_name']) ?></div>
                            <small class="text-muted"><?= e($rec['employee_code']) ?></small>
                        </td>
                        <td class="text-center"><span class="badge bg-info">-</span></td>
                        <td class="text-end">₱<?= number_format($rec['total_basic_earned'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($rec['thirteenth_month_amount'], 2) ?></td>
                        <td class="text-end text-danger">-₱<?= number_format($totalDed, 2) ?></td>
                        <td class="text-end fw-bold text-success">₱<?= number_format($rec['final_amount'], 2) ?></td>
                        <td>
                            <span class="badge <?= $rec['status'] === 'finalized' ? 'bg-success' : 'bg-warning' ?>">
                                <?= ucfirst($rec['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#detailModal<?= $rec['id'] ?>">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($rec['status'] === 'draft'): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="finalize">
                                <input type="hidden" name="record_id" value="<?= $rec['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-link p-0" title="Finalize">
                                    <i class="bi bi-lock"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php $totalDeds = array_sum(array_map(fn($r) => $r['less_absences'] + $r['less_unpaid_leave'], $allRecords)); ?>
                    <tr class="table-dark">
                        <td colspan="4" class="text-end fw-bold">Total:</td>
                        <td class="text-end fw-bold text-warning">-₱<?= number_format($totalDeds, 2) ?></td>
                        <td class="text-end fw-bold text-success">₱<?= number_format($totalFinalAmount, 2) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
