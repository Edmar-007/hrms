/**
 * HRMS SaaS - Global Application JavaScript
 */
'use strict';

/* ───────────────────────────────────────────
   1. Auto-dismiss Bootstrap alerts
─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert.alert-success, .alert.alert-info').forEach(el => {
        setTimeout(() => {
            el.classList.add('fade');
            el.classList.remove('show');
            setTimeout(() => el.remove(), 300);
        }, 4500);
    });
});

/* ───────────────────────────────────────────
   2. Prevent double-form-submission
─────────────────────────────────────────── */
document.addEventListener('submit', function (e) {
    const form = e.target;
    const submitBtn = form.querySelector('[type="submit"]');
    if (!submitBtn || form.dataset.noDoubleProtect) return;

    submitBtn.disabled = true;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Please wait…';

    setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }, 6000);
}, true);

/* ───────────────────────────────────────────
   3. Live table search helper
   Usage: <input data-table-search="myTableId">
─────────────────────────────────────────── */
document.addEventListener('input', function (e) {
    const input = e.target;
    const tableId = input.dataset.tableSearch;
    if (!tableId) return;

    const table = document.getElementById(tableId);
    if (!table) return;

    const q = input.value.toLowerCase().trim();
    table.querySelectorAll('tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = q === '' || text.includes(q) ? '' : 'none';
    });
});

/* ───────────────────────────────────────────
   4. Confirm-delete data-attribute pattern
   Usage: <button data-confirm="Are you sure?" data-form="formId">
─────────────────────────────────────────── */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;

    const msg = btn.dataset.confirm || 'Are you sure?';
    if (!confirm(msg)) {
        e.preventDefault();
        e.stopPropagation();
        return;
    }

    const formId = btn.dataset.form;
    if (formId) {
        document.getElementById(formId)?.submit();
    }
});

/* ───────────────────────────────────────────
   5. Initialize Bootstrap tooltips globally
─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof bootstrap !== 'undefined') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el, { trigger: 'hover focus' });
        });
    }
});

/* ───────────────────────────────────────────
   6. Print page helper
─────────────────────────────────────────── */
function printPage() {
    window.print();
}

/* ───────────────────────────────────────────
   7. Copy to clipboard helper (for employee codes, QR links, etc.)
─────────────────────────────────────────── */
function copyToClipboard(text, successMsg) {
    navigator.clipboard.writeText(text).then(() => {
        if (typeof showToast === 'function') {
            showToast(successMsg || 'Copied to clipboard!', 'success');
        }
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        if (typeof showToast === 'function') {
            showToast(successMsg || 'Copied!', 'success');
        }
    });
}

/* ───────────────────────────────────────────
   8. Format date / time helpers
─────────────────────────────────────────── */
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

/* ───────────────────────────────────────────
   9. Sidebar active-link scroll into view
─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const activeLink = document.querySelector('.nav-item.active');
    if (activeLink) {
        activeLink.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
});

/* ───────────────────────────────────────────
   9b. Mobile sidebar toggle (custom, no Bootstrap offcanvas)
─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const toggle   = document.getElementById('sidebarToggle');
    const sidebar  = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    if (!toggle || !sidebar || !backdrop) return;

    function openSidebar() {
        sidebar.classList.add('sidebar-open');
        backdrop.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('sidebar-open');
        backdrop.classList.remove('show');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', () => {
        sidebar.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
    });

    backdrop.addEventListener('click', closeSidebar);

    // Close on nav-item click (mobile)
    sidebar.querySelectorAll('.nav-item').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) closeSidebar();
        });
    });

    // Close on resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) closeSidebar();
    });
});

/* ───────────────────────────────────────────
   10. Password show/hide toggle for any input
   Usage: add data-password-toggle="inputId" to a button
─────────────────────────────────────────── */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-password-toggle]');
    if (!btn) return;

    const inputId = btn.dataset.passwordToggle;
    const input = document.getElementById(inputId);
    if (!input) return;

    const icon = btn.querySelector('i') || btn.querySelector('.bi');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) { icon.className = 'bi bi-eye-slash'; }
    } else {
        input.type = 'password';
        if (icon) { icon.className = 'bi bi-eye'; }
    }
});

/* ───────────────────────────────────────────
   11. Number formatter for dashboard counters
─────────────────────────────────────────── */
function animateCounter(el, target, duration) {
    const start = 0;
    const step = target / (duration / 16);
    let current = start;
    const timer = setInterval(() => {
        current += step;
        if (current >= target) {
            clearInterval(timer);
            current = target;
        }
        el.textContent = Math.floor(current).toLocaleString();
    }, 16);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-counter]').forEach(el => {
        const target = parseInt(el.dataset.counter, 10);
        if (!isNaN(target)) animateCounter(el, target, 800);
    });
});

console.info('HRMS SaaS app.js loaded ✓');
