<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$id = intval($_GET['id'] ?? 0);

// If no ID, use current user's employee
if(!$id && $_SESSION['user']['employee_id']) {
    $id = $_SESSION['user']['employee_id'];
}

// Check SaaS mode for company isolation
$hasSaas = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
if($hasSaas) {
    $cid = company_id() ?? 1;
    $empStmt = $pdo->prepare("SELECT e.*, d.name as dept_name, p.name as pos_name 
        FROM employees e 
        LEFT JOIN departments d ON d.id=e.department_id 
        LEFT JOIN positions p ON p.id=e.position_id 
        WHERE e.id=? AND e.company_id=?");
    $empStmt->execute([$id, $cid]);
} else {
    $empStmt = $pdo->prepare("SELECT e.*, d.name as dept_name, p.name as pos_name 
        FROM employees e 
        LEFT JOIN departments d ON d.id=e.department_id 
        LEFT JOIN positions p ON p.id=e.position_id 
        WHERE e.id=?");
    $empStmt->execute([$id]);
}
$emp = $empStmt->fetch();

if(!$emp) { header("Location: index.php"); exit; }

// QR Code data is the employee_code
$qrData = $emp['employee_code'];
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-qr-code me-2"></i>Employee QR Code</h4>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-body text-center py-4">
                <div class="mb-3">
                    <div style="width:80px;height:80px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:16px;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1.8rem;margin:0 auto;">
                        <?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?>
                    </div>
                </div>
                
                <h4 class="mb-1"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></h4>
                <p class="text-muted mb-1"><?= e($emp['employee_code']) ?></p>
                <p class="text-muted mb-4">
                    <?= e($emp['pos_name'] ?? 'N/A') ?> • <?= e($emp['dept_name'] ?? 'N/A') ?>
                </p>
                
                <div class="qr-code-container mb-4" id="qr-code"></div>
                
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Scan this QR code at the attendance scanner to record your time in/out
                </p>
                
                <div class="d-flex justify-content-center gap-2">
                    <button onclick="downloadQR()" class="btn btn-primary">
                        <i class="bi bi-download me-2"></i>Download QR
                    </button>
                    <button onclick="window.print()" class="btn btn-outline-secondary">
                        <i class="bi bi-printer me-2"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
const qrData = <?= json_encode($qrData) ?>;
const empName = <?= json_encode($emp['first_name'] . '_' . $emp['last_name']) ?>;

// Generate QR Code
QRCode.toCanvas(document.createElement('canvas'), qrData, {
    width: 200,
    margin: 2,
    color: { dark: '#1e293b', light: '#ffffff' }
}, function(error, canvas) {
    if (error) {
        console.error(error);
        return;
    }
    document.getElementById('qr-code').appendChild(canvas);
});

function downloadQR() {
    const canvas = document.querySelector('#qr-code canvas');
    if (!canvas) return;
    
    const link = document.createElement('a');
    link.download = 'QR_' + empName + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}
</script>

<style>
@media print {
    .sidebar, .mobile-header, .page-header .btn, .card .btn, .main-content { 
        margin-left: 0 !important; 
    }
    .sidebar, .mobile-header, .page-header .btn, .d-flex.gap-2 { 
        display: none !important; 
    }
}
</style>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
