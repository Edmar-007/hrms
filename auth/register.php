<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/csrf.php';
if(!empty($_SESSION['user'])) { header("Location: ".BASE_URL."/modules/dashboard.php"); exit; }

$plans = $pdo->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly")->fetchAll();

if(is_post()){
    if(!verify_csrf()) die("Invalid CSRF");
    
    $companyName = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $plan = $_POST['plan'] ?? 'free';
    
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
            
            // Get plan details
            $planInfo = $pdo->prepare("SELECT * FROM subscription_plans WHERE slug = ?");
            $planInfo->execute([$plan]);
            $planInfo = $planInfo->fetch();
            
            try {
                $pdo->beginTransaction();
                
                // Create company
                $st = $pdo->prepare("INSERT INTO companies (name, slug, email, plan, max_employees) VALUES (?, ?, ?, ?, ?)");
                $st->execute([$companyName, $slug, $email, $plan, $planInfo['max_employees'] ?? 5]);
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
                $pdo->prepare("INSERT INTO user_preferences (user_id, theme) VALUES (?, 'light')")->execute([$userId]);
                
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
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/public/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="auth-card" style="max-width: 500px;">
        <div class="card">
            <div class="card-body">
                <?php if(!empty($success)): ?>
                <div class="text-center py-4">
                    <div style="width:80px;height:80px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                        <i class="bi bi-check-lg text-white" style="font-size:2.5rem;"></i>
                    </div>
                    <h4 class="text-success">Registration Successful!</h4>
                    <p class="text-muted mb-4">Your company account has been created.</p>
                    <a href="login.php" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Login Now</a>
                </div>
                <?php else: ?>
                <div class="text-center mb-4">
                    <div style="width:70px;height:70px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                        <i class="bi bi-building-add text-white" style="font-size:2rem;"></i>
                    </div>
                    <h4>Register Your Company</h4>
                    <p class="subtitle">Start your free trial today</p>
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
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input class="form-control" type="password" name="password" placeholder="Min 6 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input class="form-control" type="password" name="confirm_password" placeholder="Repeat password" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Select Plan</label>
                        <div class="row g-2">
                            <?php foreach($plans as $p): ?>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="plan" id="plan-<?= e($p['slug']) ?>" value="<?= e($p['slug']) ?>" <?= $p['slug']==='free'?'checked':'' ?>>
                                <label class="btn btn-outline-primary w-100 py-3" for="plan-<?= e($p['slug']) ?>">
                                    <strong class="d-block"><?= e($p['name']) ?></strong>
                                    <small class="text-muted"><?= $p['price_monthly'] > 0 ? '₱'.number_format($p['price_monthly']).'/mo' : 'Free' ?></small>
                                </label>
                            </div>
                            <?php endforeach; ?>
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
        <p class="text-center mt-3 text-white-50 small">
            <i class="bi bi-shield-check me-1"></i>14-day free trial • No credit card required
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
