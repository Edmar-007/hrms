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

$emp = $pdo->prepare("SELECT e.*, d.name as dept_name, p.name as pos_name 
    FROM employees e 
    LEFT JOIN departments d ON d.id=e.department_id 
    LEFT JOIN positions p ON p.id=e.position_id 
    WHERE e.id=?");
$emp->execute([$id]);
$emp = $emp->fetch();

if(!$emp) { header("Location: index.php"); exit; }

// QR Code data is the employee_code
$qrData = $emp['employee_code'];
$company = $_SESSION['company'] ?? [];
$initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));

// Predefined colour palettes for the card header
$palettes = [
    'indigo'  => ['from' => '#6366f1', 'to' => '#8b5cf6'],
    'teal'    => ['from' => '#0d9488', 'to' => '#06b6d4'],
    'rose'    => ['from' => '#e11d48', 'to' => '#f97316'],
    'amber'   => ['from' => '#d97706', 'to' => '#f59e0b'],
    'emerald' => ['from' => '#059669', 'to' => '#10b981'],
    'slate'   => ['from' => '#334155', 'to' => '#475569'],
];
$selectedPalette = $_GET['color'] ?? 'indigo';
if (!array_key_exists($selectedPalette, $palettes)) $selectedPalette = 'indigo';
$p = $palettes[$selectedPalette];
$gradient = "linear-gradient(135deg, {$p['from']}, {$p['to']})";
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-qr-code me-2"></i>Employee QR Code</h4>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back to Employees</a>
</div>

<div class="row justify-content-center g-4">
    <!-- ID Card Preview -->
    <div class="col-md-5 col-lg-4">
        <div class="id-card" id="id-card-preview">
            <!-- Card Header -->
            <div class="id-card-header" style="background: <?= $gradient ?>;">
                <div class="id-card-company-bar" style="font-size:0.75rem;color:rgba(255,255,255,0.7);letter-spacing:1px;text-transform:uppercase;margin-bottom:1.2rem;">
                    <i class="bi bi-building-check me-1"></i><?= e($company['name'] ?? 'HRMS') ?>
                </div>
                <div class="id-card-avatar" id="avatar-circle">
                    <?= $initials ?>
                </div>
                <div class="id-card-name"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                <div class="id-card-role"><?= e($emp['pos_name'] ?? 'Employee') ?></div>
            </div>

            <!-- QR Code Area -->
            <div class="id-card-body">
                <div class="id-card-qr">
                    <div id="qr-code"></div>
                </div>

                <!-- Employee Info -->
                <div class="id-card-info">
                    <div class="id-card-info-item">
                        <div class="label">Employee ID</div>
                        <div class="value"><code><?= e($emp['employee_code']) ?></code></div>
                    </div>
                    <div class="id-card-info-item">
                        <div class="label">Department</div>
                        <div class="value"><?= e($emp['dept_name'] ?? '—') ?></div>
                    </div>
                    <?php if($emp['status'] ?? ''): ?>
                    <div class="id-card-info-item">
                        <div class="label">Status</div>
                        <div class="value">
                            <?php if($emp['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary"><?= e(ucfirst($emp['status'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Scan this QR code at the attendance scanner to record time in/out.
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-3 d-flex gap-2 justify-content-center flex-wrap">
            <button onclick="downloadCard()" class="btn btn-primary">
                <i class="bi bi-download me-2"></i>Download Card
            </button>
            <button onclick="downloadQROnly()" class="btn btn-outline-primary">
                <i class="bi bi-qr-code me-2"></i>Download QR Only
            </button>
            <button onclick="window.print()" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-2"></i>Print
            </button>
        </div>
    </div>

    <!-- Customizer Panel -->
    <div class="col-md-5 col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-palette me-2"></i>Customize Card
            </div>
            <div class="card-body">
                <!-- Colour picker -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Card Colour</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach($palettes as $key => $pal): ?>
                        <a href="?id=<?= $id ?>&color=<?= $key ?>"
                           class="color-swatch <?= $selectedPalette === $key ? 'selected' : '' ?>"
                           title="<?= ucfirst($key) ?>"
                           style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,<?= $pal['from'] ?>,<?= $pal['to'] ?>);display:inline-block;border:3px solid <?= $selectedPalette === $key ? '#fff' : 'transparent' ?>;box-shadow:<?= $selectedPalette === $key ? '0 0 0 2px ' . $pal['from'] : 'none' ?>;">
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- QR Colour -->
                <div class="mb-4">
                    <label class="form-label fw-bold">QR Code Colour</label>
                    <div class="d-flex gap-3 align-items-center flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="qrTheme" id="qrDark" value="dark" checked onchange="regenerateQR()">
                            <label class="form-check-label" for="qrDark">Dark on White</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="qrTheme" id="qrLight" value="light" onchange="regenerateQR()">
                            <label class="form-check-label" for="qrLight">White on Dark</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="qrTheme" id="qrColor" value="color" onchange="regenerateQR()">
                            <label class="form-check-label" for="qrColor">Coloured</label>
                        </div>
                    </div>
                </div>

                <!-- QR Size -->
                <div class="mb-4">
                    <label class="form-label fw-bold">QR Size: <span id="qrSizeLabel">200px</span></label>
                    <input type="range" class="form-range" id="qrSize" min="150" max="280" step="10" value="200" oninput="document.getElementById('qrSizeLabel').textContent=this.value+'px'; regenerateQR()">
                </div>

                <!-- Include company name -->
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="showCompany" checked onchange="toggleCompanyBar()">
                        <label class="form-check-label fw-semibold" for="showCompany">Show Company Name</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Info Summary -->
        <div class="card mt-3">
            <div class="card-header">
                <i class="bi bi-person-badge me-2"></i>Employee Details
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted fw-semibold" style="width:40%">Name</td>
                        <td><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Employee ID</td>
                        <td><code><?= e($emp['employee_code']) ?></code></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Department</td>
                        <td><?= e($emp['dept_name'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Position</td>
                        <td><?= e($emp['pos_name'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Email</td>
                        <td><?= e($emp['email'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Status</td>
                        <td>
                            <?php if(($emp['status'] ?? '') === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary"><?= e(ucfirst($emp['status'] ?? 'Unknown')) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/qrcode@1.5.3/build/qrcode.min.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
<script>
const qrData   = <?= json_encode($qrData) ?>;
const empName  = <?= json_encode($emp['first_name'] . '_' . $emp['last_name']) ?>;
const cardColor = <?= json_encode($p['from']) ?>;

function getQROptions() {
    const theme = document.querySelector('input[name="qrTheme"]:checked')?.value ?? 'dark';
    const size  = parseInt(document.getElementById('qrSize')?.value ?? 200);
    let dark = '#1e293b', light = '#ffffff';
    if (theme === 'light')  { dark = '#ffffff'; light = '#1e293b'; }
    if (theme === 'color')  { dark = cardColor; light = '#ffffff'; }
    return { width: size, margin: 1, color: { dark, light } };
}

function regenerateQR() {
    const container = document.getElementById('qr-code');
    container.innerHTML = '';
    QRCode.toCanvas(document.createElement('canvas'), qrData, getQROptions(), function(err, canvas) {
        if (!err) container.appendChild(canvas);
    });
}

// Initial render
regenerateQR();

function toggleCompanyBar() {
    const el = document.querySelector('.id-card-company-bar');
    if (el) el.style.display = document.getElementById('showCompany').checked ? '' : 'none';
}

function downloadQROnly() {
    const canvas = document.querySelector('#qr-code canvas');
    if (!canvas) return;
    const link = document.createElement('a');
    link.download = 'QR_' + empName + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

function downloadCard() {
    const card = document.getElementById('id-card-preview');
    html2canvas(card, { scale: 2, useCORS: true }).then(canvas => {
        const link = document.createElement('a');
        link.download = 'ID_Card_' + empName + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    });
}
</script>

<style>
.color-swatch { cursor: pointer; transition: transform 0.15s; }
.color-swatch:hover { transform: scale(1.15); }
.color-swatch.selected { transform: scale(1.1); }

/* ID Card table override: no striped bg */
.id-card .table tbody tr:nth-child(even) { background: transparent; }
.id-card .table tbody tr:hover { background: transparent !important; transform: none; box-shadow: none; }
.id-card .table thead th { background: transparent; color: var(--text-secondary); }

@media print {
    .sidebar, .mobile-header, .top-bar, .page-header, .card + .card,
    .col-md-5.col-lg-4:last-child { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .col-md-5 { width: 100% !important; max-width: 100% !important; }
    .id-card { box-shadow: none; }
}
</style>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
