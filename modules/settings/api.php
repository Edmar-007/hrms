<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/csrf.php";
require_login();
require_role(['Admin']);

$cid = company_id() ?? 1;

// Load API keys
$stmt = $pdo->prepare("SELECT api_keys FROM companies WHERE id = ?");
$stmt->execute([$cid]);
$company = $stmt->fetch();
$apiKeys = json_decode($company['api_keys'] ?? '{}', true);
if (!is_array($apiKeys)) $apiKeys = [];

if($_SERVER["REQUEST_METHOD"] === "POST" && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'regen_api_key') {
        $keyName = $_POST['key_name'] ?? '';
        $newKey = bin2hex(random_bytes(32));
        $apiKeys[$keyName] = $newKey;
        
        $pdo->prepare("UPDATE companies SET api_keys = ? WHERE id = ?")
            ->execute([json_encode($apiKeys), $cid]);
            
        $_SESSION['success'] = "API key regenerated!";
    }

    if ($action === 'save_webhooks') {
        $webhooks = $_POST['webhooks'] ?? [];
        $pdo->prepare("UPDATE companies SET webhooks = ? WHERE id = ?")
            ->execute([json_encode($webhooks), $cid]);
        $_SESSION['success'] = "Webhooks updated!";
    }
}

// Available integrations
$integrations = [
    'slack' => ['name' => 'Slack', 'icon' => 'bi-slack'],
    'discord' => ['name' => 'Discord', 'icon' => 'bi-discord'],
    'teams' => ['name' => 'Microsoft Teams', 'icon' => 'bi-microsoft-teams'],
    'google_chat' => ['name' => 'Google Chat', 'icon' => 'bi-google'],
    'zoom' => ['name' => 'Zoom', 'icon' => 'bi-zoom-in'],
];

?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-key me-2"></i>API Keys
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Key Name</th>
                                <th>Key Value</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>payroll</td>
                                <td class="text-monospace small"><code><?= e($apiKeys['payroll'] ?? 'Not set') ?></code></td>
                                <td><?= date('M Y') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-warning" onclick="regenKey('payroll')">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>reports</td>
                                <td class="text-monospace small"><code><?= e($apiKeys['reports'] ?? 'Not set') ?></code></td>
                                <td><?= date('M Y') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-warning" onclick="regenKey('reports')">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>attendance</td>
                                <td class="text-monospace small"><code><?= e($apiKeys['attendance'] ?? 'Not set') ?></code></td>
                                <td><?= date('M Y') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-warning" onclick="regenKey('attendance')">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-2"></i>API keys are used for external integrations. Keep them secure!
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-link-45deg me-2"></i>Webhooks
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save_webhooks">
                    <div class="mb-3">
                        <label class="form-label">Employee Created</label>
                        <input type="url" name="webhooks[employee_created]" class="form-control" placeholder="https://yourapp.com/webhooks/employee">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attendance Event</label>
                        <input type="url" name="webhooks[attendance_event]" class="form-control" placeholder="https://yourapp.com/webhooks/attendance">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Leave Request</label>
                        <input type="url" name="webhooks[leave_request]" class="form-control" placeholder="https://yourapp.com/webhooks/leave">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Save Webhooks</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function regenKey(keyName) {
    if (!confirm('Regenerate this API key? All integrations using it will need update.')) return;
    
    const formData = new FormData();
    formData.append('action', 'regen_api_key');
    formData.append('key_name', keyName);
formData.append('<?= $_SESSION["csrf_token_name"] ?? "csrf_token" ?>', '<?= csrf_token() ?>');
    
    fetch('', {
        method: 'POST',
        body: formData
    }).then(r => r.text()).then(() => {
        location.reload();
    }).catch(() => alert('Error regenerating key'));
}
</script>

<?php require_once __DIR__."/../../includes/footer.php"; ?> 

