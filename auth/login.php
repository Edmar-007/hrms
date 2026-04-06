<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/security.php';
if(!empty($_SESSION['user'])) { header("Location: ".BASE_URL."/modules/dashboard.php"); exit; }

$err = '';

if(is_post()){
  $ip = client_ip();
  if(!rate_limit_check('login:'.$ip, 10, 900)) { $err = "Too many attempts. Try again later."; }
  else if(!verify_csrf()) { $err = "Invalid request. Please try again."; }
  else {
  $email=trim($_POST['email']??''); $password=$_POST['password']??'';
  
  try {
    // Check if company_id column exists (new SaaS schema)
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'company_id'")->fetch();
    
    if($cols) {
      // New SaaS schema
      $st=$pdo->prepare("SELECT u.*, e.first_name, e.last_name, c.id as comp_id, c.name as comp_name, c.slug as comp_slug, 
          c.currency, c.timezone, c.features
          FROM users u 
          LEFT JOIN employees e ON e.id=u.employee_id 
          LEFT JOIN companies c ON c.id=u.company_id
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
    error_log("Login database error: ".$e->getMessage());
    $err = "Unable to process login right now. Please try again.";
  }
  }
}
?>
<!doctype html>
<html data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login &mdash; <?= APP_NAME ?></title>
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
                        <i class="bi bi-building-check"></i>
                    </div>
                    <h4 class="mt-3 mb-1">Welcome Back</h4>
                    <p class="subtitle">Sign in to your HRMS account</p>
                </div>
                
                <?php if(!empty($err)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                    <div><?= e($err) ?></div>
                </div>
                <?php endif; ?>
                
                <form method="post" novalidate>
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                            <input class="form-control border-start-0 ps-0" type="email" name="email" placeholder="you@company.com" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold mb-0">Password</label>
                            <a href="forgot-password.php" class="small text-primary text-decoration-none">Forgot password?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                            <input class="form-control border-start-0 border-end-0 ps-0" type="password" name="password" id="passwordInput" placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePassword()" tabindex="-1">
                                <i class="bi bi-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 py-2 fw-semibold" type="submit">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </button>
                </form>
                
                <hr class="my-4">
                <div class="text-center">
                    <p class="mb-0 text-muted small">Don't have an account? <a href="register.php" class="text-primary fw-semibold text-decoration-none">Register your company</a></p>
                </div>
            </div>
        </div>
        <p class="auth-footnote text-center mt-3 small">
            <i class="bi bi-shield-check me-1"></i>Secure &bull; Multi-tenant &bull; Enterprise HRMS
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
    const inp = document.getElementById('passwordInput');
    const ico = document.getElementById('togglePasswordIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
