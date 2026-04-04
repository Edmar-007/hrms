<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/csrf.php';
if(!empty($_SESSION['user'])) { header("Location: ".BASE_URL."/modules/dashboard.php"); exit; }

$err = '';

if(is_post()){
  if(!verify_csrf()) die("Invalid CSRF");
  $email=trim($_POST['email']??''); $password=$_POST['password']??'';
  
  try {
    // Check if company_id column exists (new SaaS schema)
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'company_id'")->fetch();
    
    if($cols) {
      // New SaaS schema
      $st=$pdo->prepare("SELECT u.*, e.first_name, e.last_name, c.id as comp_id, c.name as comp_name, c.slug as comp_slug, 
          c.plan, c.max_employees, c.currency, c.timezone, p.features
          FROM users u 
          LEFT JOIN employees e ON e.id=u.employee_id 
          LEFT JOIN companies c ON c.id=u.company_id
          LEFT JOIN subscription_plans p ON p.slug=c.plan
          WHERE u.email=? AND u.is_active=1 LIMIT 1");
      $st->execute([$email]); 
      $u=$st->fetch();
    } else {
      // Old basic schema
      $st=$pdo->prepare("SELECT u.*, e.first_name, e.last_name 
          FROM users u 
          LEFT JOIN employees e ON e.id=u.employee_id 
          WHERE u.email=? AND u.is_active=1 LIMIT 1");
      $st->execute([$email]); 
      $u=$st->fetch();
    }
  
    if($u && password_verify($password,$u['password_hash'])){
      // Get user preferences if table exists
      $theme = 'light';
      try {
        $prefs = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $prefs->execute([$u['id']]);
        $prefs = $prefs->fetch();
        if($prefs) $theme = $prefs['theme'] ?? 'light';
      } catch(Exception $e) {}
      
      $_SESSION['user'] = [
          'id' => $u['id'],
          'company_id' => $u['company_id'] ?? 1,
          'employee_id' => $u['employee_id'] ?? null,
          'role' => $u['role'],
          'email' => $u['email'],
          'name' => trim(($u['first_name']??'').' '.($u['last_name']??'')),
          'theme' => $theme
      ];
      
      $_SESSION['company'] = [
          'id' => $u['comp_id'] ?? 1,
          'name' => $u['comp_name'] ?? 'My Company',
          'slug' => $u['comp_slug'] ?? 'default',
          'plan' => $u['plan'] ?? 'professional',
          'max_employees' => $u['max_employees'] ?? 100,
          'currency' => $u['currency'] ?? 'PHP',
          'timezone' => $u['timezone'] ?? 'Asia/Manila',
          'features' => $u['features'] ?? '{"attendance":true,"leaves":true,"payroll":true,"reports":true,"qr_scanner":true}'
      ];
      
      // Update last login (if column exists)
      try {
          $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$u['id']]);
      } catch(Exception $e) { /* Column may not exist in old schema */ }
      
      header("Location: ".BASE_URL."/modules/dashboard.php"); exit;
    }
    $err="Invalid email or password";
  } catch(PDOException $e) {
    $err = "Database error: " . $e->getMessage();
  }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/public/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <div style="width:70px;height:70px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                        <i class="bi bi-building-check text-white" style="font-size:2rem;"></i>
                    </div>
                    <h4>Welcome Back</h4>
                    <p class="subtitle">Sign in to your HRMS account</p>
                </div>
                
                <?php if(!empty($err)): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($err) ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input class="form-control" type="email" name="email" placeholder="you@example.com" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input class="form-control" type="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button>
                </form>
                
                <div class="text-center mt-4">
                    <a href="register.php" class="text-decoration-none">Don't have an account? <strong>Register your company</strong></a>
                </div>
            </div>
        </div>
        <p class="text-center mt-3 text-white-50 small">
            <i class="bi bi-shield-check me-1"></i>Secure multi-tenant HRMS platform
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
