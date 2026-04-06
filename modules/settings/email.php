<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/csrf.php";
require_login();
require_role(['Admin']);

if($_SERVER["REQUEST_METHOD"] === "POST" && verify_csrf()) {
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = (int)($_POST['smtp_port'] ?? 587);
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_secure = in_array($_POST['smtp_secure'] ?? '', ['ssl', 'tls']) ? $_POST['smtp_secure'] : 'tls';
    $smtp_from = trim($_POST['smtp_from'] ?? '');

    $pdo->prepare("INSERT INTO company_settings (company_id, smtp_host, smtp_port, smtp_user, smtp_secure, smtp_from) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE smtp_host=VALUES(smtp_host), smtp_port=VALUES(smtp_port), smtp_user=VALUES(smtp_user), smtp_secure=VALUES(smtp_secure), smtp_from=VALUES(smtp_from)")
        ->execute([company_id(), $smtp_host, $smtp_port, $smtp_user, $smtp_secure, $smtp_from]);
        
    $_SESSION['success'] = "Email settings saved!";
}

$stmt = $pdo->prepare("SELECT * FROM company_settings WHERE company_id = ? AND smtp_host IS NOT NULL");
$stmt->execute([company_id()]);
$emailSettings = $stmt->fetch() ?: [];
?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-envelope me-2"></i>Email Configuration (SMTP)
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_input() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" class="form-control" name="smtp_host" value="<?= e($emailSettings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Port</label>
                    <input type="number" class="form-control" name="smtp_port" value="<?= (int)($emailSettings['smtp_port'] ?? 587) ?>" min="25" max="465">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Security</label>
                    <select name="smtp_secure" class="form-select">
                        <option value="tls" <?= ($emailSettings['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
                        <option value="ssl" <?= ($emailSettings['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                        <option value="">None (25)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="email" class="form-control" name="smtp_user" value="<?= e($emailSettings['smtp_user'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">From Email</label>
                    <input type="email" class="form-control" name="smtp_from" value="<?= e($emailSettings['smtp_from'] ?? '') ?>" placeholder="noreply@company.com">
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Save Email Settings</button>
        </form>
        <div class="mt-3 p-3 bg-light rounded">
            <h6>Test Email</h6>
            <p>Send test email to verify configuration works.</p>
            <div class="input-group">
                <input type="email" id="testEmail" class="form-control" placeholder="your@email.com">
                <button class="btn btn-outline-success" onclick="sendTestEmail()">Send Test</button>
            </div>
        </div>
    </div>
</div>

<script>
function sendTestEmail() {
    const email = document.getElementById('testEmail').value;
    if (!email) return alert('Enter test email address');
    
    fetch('<?= BASE_URL ?>/api/preferences.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            test_email: email,
            csrf_token: '<?= csrf_token() ?>'
        })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            alert('Test email sent!');
        } else {
            alert('Error: ' + data.error);
        }
    });
}
</script>
