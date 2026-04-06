'use strict';

const DESKTOP_BREAKPOINT = 992;
const SIDEBAR_STATE_KEY = 'hrms.sidebarState';

function onReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
        return;
    }
    callback();
}

function printPage() {
    window.print();
}

async function copyToClipboard(text, successMsg) {
    const message = successMsg || 'Copied to clipboard.';

    try {
        await navigator.clipboard.writeText(text);
        if (typeof showToast === 'function') {
            showToast(message, 'success');
        }
        return;
    } catch (error) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        if (typeof showToast === 'function') {
            showToast(message, 'success');
        }
    }
}

function formatDate(dateStr) {
    if (!dateStr) {
        return '-';
    }

    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) {
        return dateStr;
    }

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function animateCounter(element, target, duration) {
    if (!element || Number.isNaN(target)) {
        return;
    }

    const step = target / (duration / 16);
    let current = 0;

    const timer = window.setInterval(() => {
        current += step;
        if (current >= target) {
            current = target;
            window.clearInterval(timer);
        }
        element.textContent = Math.floor(current).toLocaleString();
    }, 16);
}

function getStoredSidebarState() {
    try {
        const stored = window.localStorage.getItem(SIDEBAR_STATE_KEY);
        if (stored === 'collapsed' || stored === 'expanded') {
            return stored;
        }
    } catch (error) {
        return null;
    }
    return null;
}

function setStoredSidebarState(state) {
    try {
        window.localStorage.setItem(SIDEBAR_STATE_KEY, state);
    } catch (error) {
        return;
    }
}

function getDefaultSidebarState() {
    return document.body?.dataset.sidebarDefault === 'collapsed' ? 'collapsed' : 'expanded';
}

function applyDesktopSidebarState(state) {
    if (!document.body) {
        return;
    }

    const isCollapsed = state === 'collapsed';
    document.body.classList.toggle('sidebar-collapsed', isCollapsed);
    document.body.dataset.sidebarState = isCollapsed ? 'collapsed' : 'expanded';

    document.querySelectorAll('[data-sidebar-toggle="desktop"]').forEach((button) => {
        const label = isCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.setAttribute('aria-pressed', isCollapsed ? 'true' : 'false');

        const icon = button.querySelector('i');
        if (icon) {
            icon.className = isCollapsed ? 'bi bi-layout-sidebar-inset-reverse' : 'bi bi-layout-sidebar-inset';
        }
    });
}

function initResponsiveTables() {
    document.querySelectorAll('table.table').forEach((table) => {
        if (table.closest('.table-responsive-wrapper, .table-responsive')) {
            return;
        }

        const parent = table.parentNode;
        if (!parent) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive-wrapper auto-table-wrap';
        parent.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
}

function initResponsiveTableWheelScroll() {
    document.querySelectorAll('.table-responsive-wrapper, .table-responsive').forEach((wrapper) => {
        if (wrapper.dataset.wheelScrollReady === '1') {
            return;
        }

        wrapper.dataset.wheelScrollReady = '1';
        wrapper.addEventListener('wheel', (event) => {
            const maxScrollLeft = wrapper.scrollWidth - wrapper.clientWidth;
            if (maxScrollLeft <= 0) {
                return;
            }

            const isHorizontalIntent = event.shiftKey || Math.abs(event.deltaX) > Math.abs(event.deltaY);
            if (!isHorizontalIntent) {
                return;
            }

            const primaryDelta = event.deltaX !== 0 ? event.deltaX : event.deltaY;
            if (primaryDelta === 0) {
                return;
            }

            const nextScrollLeft = wrapper.scrollLeft + primaryDelta;
            if (nextScrollLeft < 0 || nextScrollLeft > maxScrollLeft) {
                return;
            }

            event.preventDefault();
            wrapper.scrollLeft = nextScrollLeft;
        }, { passive: false });
    });
}

function normalizeModalPlacement(root = document) {
    root.querySelectorAll('.modal').forEach((modal) => {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });
}

function initSidebar() {
    const mobileToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const desktopToggles = document.querySelectorAll('[data-sidebar-toggle="desktop"]');

    if (!sidebar) {
        return;
    }

    const clearBodyScrollLock = () => {
        document.body.classList.remove('sidebar-mobile-open');
        if (!document.body.classList.contains('modal-open')) {
            document.body.style.overflow = '';
        }
    };

    const closeSidebar = () => {
        sidebar.classList.remove('sidebar-open');
        backdrop?.classList.remove('show');
        clearBodyScrollLock();
    };

    const openSidebar = () => {
        if (window.innerWidth >= DESKTOP_BREAKPOINT) {
            return;
        }
        sidebar.classList.add('sidebar-open');
        backdrop?.classList.add('show');
        document.body.classList.add('sidebar-mobile-open');
        document.body.style.overflow = 'hidden';
    };

    clearBodyScrollLock();
    backdrop?.classList.remove('show');

    if (mobileToggle && backdrop) {
        mobileToggle.addEventListener('click', () => {
            if (sidebar.classList.contains('sidebar-open')) {
                closeSidebar();
                return;
            }
            openSidebar();
        });

        backdrop.addEventListener('click', closeSidebar);

        sidebar.querySelectorAll('.nav-item').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth < DESKTOP_BREAKPOINT) {
                    closeSidebar();
                }
            });
        });
    }

    if (desktopToggles.length) {
        const initialState = getStoredSidebarState() || getDefaultSidebarState();
        if (window.innerWidth >= DESKTOP_BREAKPOINT) {
            applyDesktopSidebarState(initialState);
        }

        desktopToggles.forEach((button) => {
            button.addEventListener('click', () => {
                const nextState = document.body.classList.contains('sidebar-collapsed') ? 'expanded' : 'collapsed';
                applyDesktopSidebarState(nextState);
                setStoredSidebarState(nextState);
            });
        });
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth >= DESKTOP_BREAKPOINT) {
            closeSidebar();
            applyDesktopSidebarState(getStoredSidebarState() || getDefaultSidebarState());
            return;
        }

        document.body.classList.remove('sidebar-collapsed');
        document.body.dataset.sidebarState = 'expanded';
    });

    window.addEventListener('pageshow', () => {
        if (!sidebar.classList.contains('sidebar-open')) {
            clearBodyScrollLock();
        }
    });

    // Avoid overlay stacking (sidebar + modal) that can make UI appear frozen.
    document.addEventListener('show.bs.modal', () => {
        closeSidebar();
    });
}

function cleanupModalArtifacts() {
    const openModal = document.querySelector('.modal.show');
    if (openModal) {
        return;
    }

    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';

    if (!document.body.classList.contains('sidebar-mobile-open')) {
        document.body.style.overflow = '';
    }
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    const submitButton = form.querySelector('[type="submit"]');
    if (!submitButton || form.dataset.noDoubleProtect) {
        return;
    }

    const originalText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Please wait...';

    window.setTimeout(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    }, 6000);
}, true);

document.addEventListener('input', (event) => {
    const input = event.target;
    const tableId = input.dataset.tableSearch;
    if (!tableId) {
        return;
    }

    const table = document.getElementById(tableId);
    if (!table) {
        return;
    }

    const query = input.value.toLowerCase().trim();
    table.querySelectorAll('tbody tr').forEach((row) => {
        const haystack = row.textContent.toLowerCase();
        row.style.display = query === '' || haystack.includes(query) ? '' : 'none';
    });
});

document.addEventListener('click', (event) => {
    const confirmButton = event.target.closest('[data-confirm]');
    if (confirmButton) {
        const message = confirmButton.dataset.confirm || 'Are you sure?';
        if (!window.confirm(message)) {
            event.preventDefault();
            event.stopPropagation();
            return;
        }

        const formId = confirmButton.dataset.form;
        if (formId) {
            document.getElementById(formId)?.submit();
        }
        return;
    }

    const passwordToggle = event.target.closest('[data-password-toggle]');
    if (!passwordToggle) {
        return;
    }

    const inputId = passwordToggle.dataset.passwordToggle;
    const input = document.getElementById(inputId);
    if (!input) {
        return;
    }

    const icon = passwordToggle.querySelector('i') || passwordToggle.querySelector('.bi');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.className = 'bi bi-eye-slash';
        }
        return;
    }

    input.type = 'password';
    if (icon) {
        icon.className = 'bi bi-eye';
    }
});

onReady(() => {
    document.querySelectorAll('.alert.alert-success, .alert.alert-info').forEach((element) => {
        window.setTimeout(() => {
            element.classList.add('fade');
            element.classList.remove('show');
            window.setTimeout(() => element.remove(), 300);
        }, 4500);
    });

    if (typeof bootstrap !== 'undefined') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
            new bootstrap.Tooltip(element, { trigger: 'hover focus' });
        });
    }

    const activeLink = document.querySelector('.nav-item.active');
    if (activeLink) {
        activeLink.scrollIntoView({ block: 'nearest', behavior: 'auto' });
    }

    document.querySelectorAll('[data-counter]').forEach((element) => {
        const target = Number.parseInt(element.dataset.counter, 10);
        if (!Number.isNaN(target)) {
            animateCounter(element, target, 800);
        }
    });

    initResponsiveTables();
    initResponsiveTableWheelScroll();
    normalizeModalPlacement();
    initSidebar();

    document.addEventListener('show.bs.modal', (event) => {
        const modal = event.target;
        if (modal?.classList?.contains('modal') && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    document.addEventListener('hidden.bs.modal', () => {
        window.setTimeout(cleanupModalArtifacts, 0);
    });
});

console.info('HRMS app JS loaded');
