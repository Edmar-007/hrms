<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/security.php';
require_once __DIR__.'/../includes/validator.php';

if(!empty($_SESSION['user'])) { header("Location: ".BASE_URL."/modules/dashboard.php"); exit; }

$msg = '';
$err = '';

if (is_post()) {
    if (!verify_csrf()) {
        $err = 'Invalid request.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $ip = client_ip();
        if (!rate_limit_check('forgot_password:'.$ip, 5, 900)) {
            $err = 'Too many reset requests. Please try again later.';
        } elseif (!v_email($email)) {
            $err = 'Please enter a valid email address.';
        } else {
            try {
                $st = $pdo->prepare("SELECT id, company_id, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
                $st->execute([$email]);
                $u = $st->fetch();

                if ($u) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
                    $expires = date('Y-m-d H:i:s', time() + 3600);
                    $companyId = (int)($u['company_id'] ?? 1);

                    $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
                        ->execute([$u['id']]);
                    $pdo->prepare("INSERT INTO password_reset_tokens (company_id, user_id, token_hash, expires_at) VALUES (?, ?, ?, ?)")
                        ->execute([$companyId, $u['id'], $tokenHash, $expires]);

                    log_activity('password_reset_requested', 'user', (int)$u['id'], ['email' => $u['email']]);
                    $_SESSION['password_reset_link'] = BASE_URL . '/auth/reset-password.php?token=' . urlencode($token) . '&uid=' . (int)$u['id'];
                }
                $msg = 'If the account exists, a password reset link has been generated.';
            } catch (Exception $e) {
                $err = 'Unable to process request.';
            }
        }
    }
}
?>
<!doctype html>
<html data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password &mdash; <?= APP_NAME ?></title>
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
                        <i class="bi bi-key"></i>
                    </div>
                    <h4 class="mt-3">Forgot Password</h4>
                    <p class="subtitle">Enter your account email to reset your password.</p>
                </div>

                <?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
                <?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

                <form method="post">
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input class="form-control" type="email" name="email" required>
                    </div>
                    <button class="btn btn-primary w-100">Send Reset Link</button>
                </form>

                <?php if(!empty($_SESSION['password_reset_link'])): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <strong>Dev/Test Link:</strong><br>
                        <a href="<?= e($_SESSION['password_reset_link']) ?>"><?= e($_SESSION['password_reset_link']) ?></a>
                    </div>
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
