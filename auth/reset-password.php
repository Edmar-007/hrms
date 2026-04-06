<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/security.php';

if(!empty($_SESSION['user'])) { header("Location: ".BASE_URL."/modules/dashboard.php"); exit; }

$uid = (int)($_GET['uid'] ?? $_POST['uid'] ?? 0);
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$err = '';
$msg = '';

function find_valid_reset_token($pdo, $uid, $token) {
    $st = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 10");
    $st->execute([$uid]);
    $rows = $st->fetchAll();
    foreach ($rows as $row) {
        if (password_verify($token, $row['token_hash'])) return $row;
    }
    return null;
}

if (is_post()) {
    if (!verify_csrf()) {
        $err = 'Invalid request.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if (strlen($password) < 8) {
            $err = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $err = 'Passwords do not match.';
        } else {
            $row = find_valid_reset_token($pdo, $uid, $token);
            if (!$row) {
                $err = 'Reset link is invalid or expired.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $uid]);
                $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?")->execute([$row['id']]);
                log_activity('password_reset_completed', 'user', $uid, null);
                $msg = 'Password has been reset. You may now log in.';
            }
        }
    }
} else {
    if ($uid <= 0 || $token === '' || !find_valid_reset_token($pdo, $uid, $token)) {
        $err = 'Reset link is invalid or expired.';
    }
}
?>
<!doctype html>
<html data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password &mdash; <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/public/assets/css/style.css?v=20260406a" rel="stylesheet">
    <link href="<?= BASE_URL ?>/public/assets/css/modern-ui.css?v=20260406a" rel="stylesheet">
</head>
<body class="theme-light">
<div class="auth-page">
    <div class="auth-card">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="auth-logo-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h4 class="mt-3">Reset Password</h4>
                    <p class="subtitle">Choose a new password to secure your account.</p>
                </div>
                <?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
                <?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

                <?php if(!$msg && !$err): ?>
                <form method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="uid" value="<?= (int)$uid ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="password" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password" minlength="8" required>
                    </div>
                    <button class="btn btn-primary w-100">Reset Password</button>
                </form>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="login.php">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
