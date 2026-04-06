<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/csrf.php';
if(!empty($_SESSION['user'])) { header("Location: ".BASE_URL."/modules/dashboard.php"); exit; }

if(is_post()){
    if(!verify_csrf()) die("Invalid CSRF");
    
    $companyName = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    // Validations
    if(empty($companyName) || empty($email) || empty($password)) {
        $err = "All fields are required";
    } elseif($password !== $confirm) {
        $err = "Passwords do not match";
    } elseif(strlen($password) < 6) {
        $err = "Password must be at least 6 characters";
    } else {
        // Check if email exists
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        if($st->fetch()) {
            $err = "Email already registered";
        } else {
            // Generate slug
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $companyName));
            $slug = substr($slug, 0, 50) . '-' . rand(100, 999);
            
            try {
                $pdo->beginTransaction();
                
                // Create company
                $st = $pdo->prepare("INSERT INTO companies (name, slug, email) VALUES (?, ?, ?)");
                $st->execute([$companyName, $slug, $email]);
                $companyId = $pdo->lastInsertId();
                
                // Create default departments & positions
                $pdo->prepare("INSERT INTO departments (company_id, name) VALUES (?, 'General')")->execute([$companyId]);
                $pdo->prepare("INSERT INTO positions (company_id, name) VALUES (?, 'Staff')")->execute([$companyId]);
                $pdo->prepare("INSERT INTO leave_types (company_id, name, days_allowed) VALUES (?, 'Vacation Leave', 15), (?, 'Sick Leave', 15)")->execute([$companyId, $companyId]);
                
                // Create admin user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $st = $pdo->prepare("INSERT INTO users (company_id, email, password_hash, role, is_active) VALUES (?, ?, ?, 'Admin', 1)");
                $st->execute([$companyId, $email, $hash]);
                $userId = $pdo->lastInsertId();
                
                // Create user preferences
                try {
                    $pdo->prepare("INSERT INTO user_preferences (user_id, theme) VALUES (?, 'light')")->execute([$userId]);
                } catch(PDOException $e) {}
                
                $pdo->commit();
                $success = true;
            } catch(Exception $e) {
                $pdo->rollBack();
                $err = "Registration failed. Please try again.";
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
    <title>Register &mdash; <?= APP_NAME ?></title>
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
    <div class="auth-card" style="max-width: 450px;">
        <div class="card">
            <div class="card-body">
                <?php if(!empty($success)): ?>
                <div class="text-center py-4">
                    <div class="auth-success-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h4 class="text-success">Registration Successful!</h4>
                    <p class="text-muted mb-4">Your company account has been created.</p>
                    <a href="login.php" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Login Now</a>
                </div>
                <?php else: ?>
                <div class="text-center mb-4">
                    <div class="auth-logo-icon">
                        <i class="bi bi-building-add"></i>
                    </div>
                    <h4 class="mt-3">Register Your Company</h4>
                    <p class="subtitle">Create your HRMS account</p>
                </div>
                
                <?php if(!empty($err)): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($err) ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-building"></i></span>
                            <input class="form-control" type="text" name="company_name" placeholder="Your Company Inc." required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admin Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input class="form-control" type="email" name="email" placeholder="admin@yourcompany.com" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input class="form-control" type="password" name="password" placeholder="Min 6 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input class="form-control" type="password" name="confirm_password" placeholder="Repeat password" required>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 py-2"><i class="bi bi-rocket-takeoff me-2"></i>Create Account</button>
                </form>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="text-decoration-none">Already have an account? <strong>Sign In</strong></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <p class="auth-footnote text-center mt-3 small">
            <i class="bi bi-shield-check me-1"></i>Secure • Multi-tenant • Enterprise HRMS
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
