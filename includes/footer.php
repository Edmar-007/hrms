<?php $u=$_SESSION['user']??null; ?>
<?php if($u): ?></main><?php endif; ?>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/app.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
const USER_ID = <?= $u['id'] ?? 'null' ?>;

// Toast notification function
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toast-container');
    const icons = { success: 'check-circle', error: 'x-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    const colors = { success: 'success', error: 'danger', warning: 'warning', info: 'primary' };
    
    const toast = document.createElement('div');
    toast.className = `toast show border-0`;
    toast.innerHTML = `
        <div class="toast-body d-flex align-items-center gap-2 bg-${colors[type]} text-white rounded">
            <i class="bi bi-${icons[type]}"></i>
            <span>${message}</span>
            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Theme toggle
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-bs-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-bs-theme', newTheme);
    document.body.className = 'theme-' + newTheme;
    
    // Update icons
    document.querySelectorAll('.bi-sun, .bi-moon').forEach(el => {
        el.classList.toggle('bi-sun');
        el.classList.toggle('bi-moon');
    });
    
    // Save preference
    fetch(BASE_URL + '/api/preferences.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: newTheme })
    });
    
    showToast(newTheme === 'dark' ? 'Dark mode enabled' : 'Light mode enabled', 'info');
}

// Load notifications
async function loadNotifications() {
    try {
        const res = await fetch(BASE_URL + '/api/notifications.php');
        const data = await res.json();
        
        const badge = document.getElementById('notif-count');
        const list = document.getElementById('notification-list');
        
        if(badge && data.unread > 0) {
            badge.textContent = data.unread;
            badge.style.display = 'block';
        }
        
        if(list && data.notifications.length > 0) {
            list.innerHTML = data.notifications.map(n => `
                <a href="${n.link || '#'}" class="dropdown-item notification-item ${n.is_read ? '' : 'unread'}">
                    <i class="bi bi-${n.type === 'leave' ? 'calendar' : 'bell'} me-2"></i>
                    <div>
                        <div class="fw-semibold">${n.title}</div>
                        <small class="text-muted">${n.time_ago}</small>
                    </div>
                </a>
            `).join('');
        }
    } catch(e) {}
}

// Mark all notifications read
function markAllRead() {
    fetch(BASE_URL + '/api/notifications.php?action=read_all', { method: 'POST' })
        .then(() => {
            document.getElementById('notif-count').style.display = 'none';
            document.querySelectorAll('.notification-item').forEach(el => el.classList.remove('unread'));
            showToast('All notifications marked as read', 'success');
        });
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();
    setInterval(loadNotifications, 60000);
});
</script>
</body></html>
