<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/validator.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;
$msg = '';
$err = '';

if (is_post()) {
    if (!verify_csrf()) {
        $err = 'Invalid request.';
    } else {
        $id = (int)($_POST['attendance_id'] ?? 0);
        $timeIn = trim($_POST['time_in'] ?? '');
        $timeOut = trim($_POST['time_out'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if ($id <= 0 || $reason === '') {
            $err = 'Attendance record and reason are required.';
        } else {
            $st = $pdo->prepare("SELECT * FROM attendance WHERE id = ? AND company_id = ?");
            $st->execute([$id, $cid]);
            $old = $st->fetch();
            if (!$old) {
                $err = 'Attendance record not found.';
            } else {
                $d = strtotime($old['date']);
                if ($d < strtotime('-30 days')) {
                    $err = 'Corrections are only allowed within 30 days.';
                } else {
                    $pdo->prepare("UPDATE attendance SET time_in = ?, time_out = ?, notes = ? WHERE id = ? AND company_id = ?")
                        ->execute([$timeIn ?: null, $timeOut ?: null, $reason, $id, $cid]);
                    log_activity('attendance_correction', 'attendance', $id, [
                        'old_time_in' => $old['time_in'],
                        'old_time_out' => $old['time_out'],
                        'new_time_in' => $timeIn,
                        'new_time_out' => $timeOut,
                        'reason' => $reason
                    ]);
                    $msg = 'Attendance corrected successfully.';
                }
            }
        }
    }
}

$rows = $pdo->prepare("SELECT a.*, e.first_name, e.last_name, e.employee_code
    FROM attendance a
    JOIN employees e ON e.id = a.employee_id
    WHERE a.company_id = ?
    ORDER BY a.date DESC, a.id DESC
    LIMIT 100");
$rows->execute([$cid]);
$rows = $rows->fetchAll();
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-pencil-square me-2"></i>Attendance Corrections</h4>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>
<?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Employee</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach($rows as $r): ?>
                <tr>
                    <td><?= e($r['first_name'].' '.$r['last_name']) ?> <small class="text-muted">(<?= e($r['employee_code']) ?>)</small></td>
                    <td><?= e($r['date']) ?></td>
                    <td><?= e($r['time_in'] ?? '-') ?></td>
                    <td><?= e($r['time_out'] ?? '-') ?></td>
                    <td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></button></td>
                </tr>
                <div class="modal fade" id="edit<?= (int)$r['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="attendance_id" value="<?= (int)$r['id'] ?>">
                                <div class="modal-header"><h5 class="modal-title">Correct Attendance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <div class="row g-2">
                                        <div class="col-6"><label class="form-label">Time In</label><input type="time" class="form-control" name="time_in" value="<?= e($r['time_in']) ?>"></div>
                                        <div class="col-6"><label class="form-label">Time Out</label><input type="time" class="form-control" name="time_out" value="<?= e($r['time_out']) ?>"></div>
                                    </div>
                                    <div class="mt-2"><label class="form-label">Reason</label><textarea class="form-control" name="reason" required></textarea></div>
                                </div>
                                <div class="modal-footer"><button class="btn btn-primary">Save Correction</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>

