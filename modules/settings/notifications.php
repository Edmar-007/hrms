<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/csrf.php";
require_login();
require_role(['Admin']);

$cid = company_id() ?? 1;

// Load current notification settings
$stmt = $pdo->prepare("SELECT notification_prefs FROM companies WHERE id = ?");
$stmt->execute([$cid]);
$company = $stmt->fetch();
$notificationPrefs = json_decode($company['notification_prefs'] ?? '{}', true);
if (!is_array($notificationPrefs)) $notificationPrefs = [];

$categories = [
    'daily_summary' => 'Daily Attendance Summary',
    'payroll_ready' => 'Payroll Processing Complete', 
    'leave_approved' => 'Leave Request Approved/Rejected',
    'low_balance' => 'Leave Balance Low (<10 days)',
    'shift_reminder' => 'Upcoming Shift Reminder',
    'audit_alert' => 'Security Audit Events',
    'new_employee' => 'New Employee Added'
];

if($_SERVER["REQUEST_METHOD"] === "POST" && verify_csrf()) {
    $selected = $_POST['notifications'] ?? [];
    $prefs = ['enabled' => [], 'email' => [], 'sms' => []];
    
    foreach($categories as $key => $label) {
        if(isset($selected[$key . '_email'])) $prefs['email'][] = $key;
        if(isset($selected[$key . '_sms'])) $prefs['sms'][] = $key;
        $prefs['enabled'][] = $key;
    }
    
    $pdo->prepare("UPDATE companies SET notification_prefs = ? WHERE id = ?")
        ->execute([json_encode($prefs), $cid]);
    
    $_SESSION['success'] = "Notification preferences saved!";
}

?>
<div class="card">
    <div class="card-header">
        <i class="bi bi-bell me-2"></i>Notification Preferences
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_input() ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Notification Type</th>
                                    <th>Email</th>
                                    <th>SMS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($categories as $key => $label): ?>
                                <tr>
                                    <td><strong><?= $label ?></strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="notifications[<?= $key ?>_email]" 
                                                   id="email_<?= $key ?>" <?= in_array($key, $notificationPrefs['email'] ?? []) ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="notifications[<?= $key ?>_sms]" 
                                                   id="sms_<?= $key ?>" <?= in_array($key, $notificationPrefs['sms'] ?? []) ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-start border-primary h-100">
                        <div class="card-body">
                            <h6>Delivery Methods</h6>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="email_global">
                                    <label class="form-check-label" for="email_global">
                                        Send Email
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sms_global">
                                    <label class="form-check-label" for="sms_global">
                                        Send SMS
                                    </label>
                                </div>
                            </div>
                            <h6 class="mt-4 mb-2">Recipients</h6>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notify_managers">
                                    <label class="form-check-label" for="notify_managers">
                                        Managers
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notify_hr">
                                    <label class="form-check-label" for="notify_hr">
                                        HR Team
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notify_employee">
                                    <label class="form-check-label" for="notify_employee">
                                        Employees (self)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">
                <i class="bi bi-check-lg me-2"></i>Save Preferences
            </button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', function() {
        const name = this.name;
        const globalEmail = document.getElementById('email_global');
        const globalSms = document.getElementById('sms_global');
        
        if (this.checked && globalEmail && !globalEmail.checked) {
            globalEmail.checked = true;
        }
        if (this.checked && globalSms && !globalSms.checked) {
            globalSms.checked = true;
        }
    });
});
</script>
