<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/security.php';
require_once __DIR__.'/../../includes/validator.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;
$summary = null;
$errors = [];

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Invalid request.';
    } else {
        [$ok, $mimeOrErr] = upload_is_allowed($_FILES['csv_file'] ?? [], ['text/csv', 'text/plain', 'application/vnd.ms-excel'], 5 * 1024 * 1024);
        if (!$ok) {
            $errors[] = $mimeOrErr;
        } else {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if (!$handle) {
                $errors[] = 'Unable to read CSV.';
            } else {
                $header = fgetcsv($handle);
                $expected = ['employee_code','first_name','last_name','email','phone','basic_salary','department_id','position_id'];
                if ($header !== $expected) {
                    $errors[] = 'Invalid CSV header.';
                } else {
                    $inserted = 0;
                    $failed = 0;
                    $line = 1;
                    while (($row = fgetcsv($handle)) !== false) {
                        $line++;
                        if (count($row) !== count($expected)) { $failed++; $errors[] = "Line $line: Expected ".count($expected)." columns, got ".count($row); continue; }
                        $data = array_combine($expected, $row);
                        $data = array_map('trim', $data);
                        if (!v_required($data['employee_code']) || !v_required($data['first_name']) || !v_required($data['last_name']) || !v_email($data['email']) || !v_phone($data['phone']) || !v_non_negative_number($data['basic_salary'])) {
                            $failed++; $errors[] = "Line $line: Validation failed"; continue;
                        }
                        try {
                            $st = $pdo->prepare("INSERT INTO employees (company_id, employee_code, first_name, last_name, email, phone, basic_salary, department_id, position_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                            $st->execute([$cid, $data['employee_code'], $data['first_name'], $data['last_name'], $data['email'], $data['phone'], (float)$data['basic_salary'], ($data['department_id'] !== '' ? (int)$data['department_id'] : null), ($data['position_id'] !== '' ? (int)$data['position_id'] : null)]);
                            $inserted++;
                        } catch (Exception $e) {
                            $failed++;
                            error_log("Employee CSV import error on line $line: ".$e->getMessage());
                            $errors[] = "Line $line: Database error occurred";
                        }
                    }
                    $summary = ['inserted' => $inserted, 'failed' => $failed];
                    log_activity('import', 'employees', null, $summary);
                }
                fclose($handle);
            }
        }
    }
}
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-upload me-2"></i>Import Employees (CSV)</h4>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>

<?php if($summary): ?><div class="alert alert-success">Imported: <?= (int)$summary['inserted'] ?> | Failed: <?= (int)$summary['failed'] ?></div><?php endif; ?>
<?php if($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <div class="mb-3">
                <label class="form-label">CSV File</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
            </div>
            <div class="mb-3 small text-muted">
                Required header: <code>employee_code,first_name,last_name,email,phone,basic_salary,department_id,position_id</code>
            </div>
            <button class="btn btn-primary"><i class="bi bi-upload me-2"></i>Import</button>
        </form>
    </div>
</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
