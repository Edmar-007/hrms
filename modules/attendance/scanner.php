<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer', 'Manager']);
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-qr-code-scan me-2"></i>QR Code Scanner</h4>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="scanner-container">
            <h5 class="mb-1"><i class="bi bi-camera-video me-2"></i>Scan Employee QR Code</h5>
            <p class="text-white-50 mb-0">Point the camera at employee's QR code to record attendance</p>
            
            <div class="scanner-frame" id="scanner-frame">
                <video id="scanner-video" playsinline></video>
                <div class="scanner-overlay"></div>
            </div>
            
            <div id="scanner-status" class="scanner-status ready">
                <i class="bi bi-camera me-2"></i>Initializing camera...
            </div>
            
            <div class="mt-3">
                <button id="btn-start" class="btn btn-success me-2"><i class="bi bi-play-fill me-1"></i>Start Scanner</button>
                <button id="btn-stop" class="btn btn-danger" disabled><i class="bi bi-stop-fill me-1"></i>Stop</button>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i>Recent Scans Today
            </div>
            <div class="card-body p-0" id="recent-scans">
                <?php
                $today = $pdo->query("SELECT a.*, e.first_name, e.last_name, e.employee_code 
                    FROM attendance a 
                    JOIN employees e ON e.id=a.employee_id 
                    WHERE a.date=CURDATE() 
                    ORDER BY COALESCE(a.time_out, a.time_in) DESC 
                    LIMIT 10")->fetchAll();
                if(empty($today)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>No attendance records today</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach($today as $t): ?>
                    <div class="list-group-item d-flex align-items-center">
                        <div class="me-3" style="width:42px;height:42px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-weight:600;">
                            <?= strtoupper(substr($t['first_name'], 0, 1) . substr($t['last_name'], 0, 1)) ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= e($t['first_name'].' '.$t['last_name']) ?></div>
                            <small class="text-muted"><?= e($t['employee_code']) ?></small>
                        </div>
                        <div class="text-end">
                            <div class="small">
                                <span class="text-success"><i class="bi bi-box-arrow-in-right"></i> <?= date('h:i A', strtotime($t['time_in'])) ?></span>
                            </div>
                            <?php if($t['time_out']): ?>
                            <div class="small">
                                <span class="text-warning"><i class="bi bi-box-arrow-right"></i> <?= date('h:i A', strtotime($t['time_out'])) ?></span>
                            </div>
                            <?php else: ?>
                            <span class="badge bg-success">Working</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div id="modal-icon" style="font-size:4rem;margin-bottom:1rem;"></div>
                <h4 id="modal-title"></h4>
                <p id="modal-message" class="text-muted mb-0"></p>
                <div id="modal-shift-warning" class="alert alert-warning mt-3 mb-0 d-none">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span id="modal-shift-warning-text"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
let html5QrCode = null;
let isScanning = false;
let lastScannedCode = '';
let lastScanTime = 0;
let lastFeedId = 0;

const statusEl = document.getElementById('scanner-status');
const btnStart = document.getElementById('btn-start');
const btnStop = document.getElementById('btn-stop');
const successModal = new bootstrap.Modal(document.getElementById('successModal'));

function setStatus(text, type) {
    statusEl.className = 'scanner-status ' + type;
    statusEl.innerHTML = text;
}

async function onScanSuccess(decodedText) {
    // Prevent duplicate scans within 3 seconds
    const now = Date.now();
    if (decodedText === lastScannedCode && (now - lastScanTime) < 3000) {
        return;
    }
    lastScannedCode = decodedText;
    lastScanTime = now;
    
    setStatus('<i class="bi bi-hourglass-split me-2"></i>Processing...', 'ready');
    
    try {
        let gps = null;
        try {
            if (navigator.geolocation) {
                gps = await new Promise((resolve) => navigator.geolocation.getCurrentPosition(
                    (pos) => resolve({lat: pos.coords.latitude, lng: pos.coords.longitude}),
                    () => resolve(null),
                    { enableHighAccuracy: false, timeout: 1500, maximumAge: 60000 }
                ));
            }
        } catch (e) {}

        const response = await fetch(BASE_URL + '/modules/attendance/scan_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: decodedText, gps: gps, action: 'auto' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            setStatus('<i class="bi bi-check-circle me-2"></i>' + data.message, 'success');
            showModal('success', data.employee, data.action, data.time, data.shift_warning);
            await refreshLiveFeed();
        } else {
            setStatus('<i class="bi bi-x-circle me-2"></i>' + data.message, 'error');
            showModal('error', null, data.message);
        }
    } catch (err) {
        setStatus('<i class="bi bi-x-circle me-2"></i>Error processing scan', 'error');
    }
}

async function refreshLiveFeed() {
    try {
        const res = await fetch(BASE_URL + '/modules/attendance/live_feed.php?since_id=' + lastFeedId);
        const data = await res.json();
        if (!data.success || !Array.isArray(data.rows) || data.rows.length === 0) return;
        const list = document.querySelector('#recent-scans .list-group');
        if (!list) return;
        data.rows.slice().reverse().forEach(r => {
            if (r.id > lastFeedId) lastFeedId = r.id;
            const initials = ((r.first_name?.[0] || '') + (r.last_name?.[0] || '')).toUpperCase();
            const action = r.last_action ? r.last_action.replace('_',' ') : (r.time_out ? 'time out' : 'time in');
            const t = r.last_scan_at || (r.time_out ? r.time_out : r.time_in);
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex align-items-center';
            item.innerHTML = `
                <div class="me-3" style="width:42px;height:42px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-weight:600;">${initials}</div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">${r.first_name} ${r.last_name}</div>
                    <small class="text-muted">${r.employee_code}</small>
                </div>
                <div class="text-end">
                    <div class="small text-primary text-capitalize">${action}</div>
                    <small class="text-muted">${t ? new Date('1970-01-01T' + t).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : ''}</small>
                </div>`;
            list.prepend(item);
            while (list.children.length > 10) list.removeChild(list.lastChild);
        });
    } catch (e) {}
}

function showModal(type, employee, action, time, shiftWarning) {
    const iconEl = document.getElementById('modal-icon');
    const titleEl = document.getElementById('modal-title');
    const msgEl = document.getElementById('modal-message');
    const warnEl = document.getElementById('modal-shift-warning');
    const warnTextEl = document.getElementById('modal-shift-warning-text');
    
    if (type === 'success') {
        iconEl.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
        titleEl.textContent = employee;
        msgEl.innerHTML = action + ' at <strong>' + time + '</strong>';
    } else {
        iconEl.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
        titleEl.textContent = 'Scan Failed';
        msgEl.textContent = action;
    }

    if (shiftWarning) {
        warnTextEl.textContent = shiftWarning;
        warnEl.classList.remove('d-none');
    } else {
        warnEl.classList.add('d-none');
    }
    
    successModal.show();
    setTimeout(() => successModal.hide(), 2500);
}

async function startScanner() {
    try {
        html5QrCode = new Html5Qrcode("scanner-video", { formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE] });
        
        await html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            onScanSuccess,
            () => {}
        );
        
        isScanning = true;
        btnStart.disabled = true;
        btnStop.disabled = false;
        setStatus('<i class="bi bi-camera-video me-2"></i>Scanner active - Show QR code', 'ready');
    } catch (err) {
        setStatus('<i class="bi bi-exclamation-triangle me-2"></i>Camera error: ' + err.message, 'error');
    }
}

async function stopScanner() {
    if (html5QrCode && isScanning) {
        await html5QrCode.stop();
        isScanning = false;
        btnStart.disabled = false;
        btnStop.disabled = true;
        setStatus('<i class="bi bi-camera-video-off me-2"></i>Scanner stopped', 'ready');
    }
}

btnStart.addEventListener('click', startScanner);
btnStop.addEventListener('click', stopScanner);

// Auto-start on load
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(startScanner, 500);
    setInterval(refreshLiveFeed, 5000);
});
</script>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
