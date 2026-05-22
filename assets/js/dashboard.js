/* ========================================
   Free Would - Dashboard JavaScript
   ======================================== */

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initUserMenu();
    loadUserData();
    loadDashboardStats();
    loadRecentActivity();
});

function initSidebar() {
    const toggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 1024 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    const currentPath = window.location.pathname.split('/').pop() || 'dashboard.html';
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPath) {
            link.classList.add('active');
        }
    });
}

function initUserMenu() {
    const userBtn = document.querySelector('.navbar-user');
    const dropdown = document.querySelector('.navbar-dropdown');

    if (userBtn && dropdown) {
        userBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });

        document.addEventListener('click', () => {
            dropdown.classList.remove('show');
        });
    }
}

async function loadUserData() {
    const token = localStorage.getItem('fw_token');
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    try {
        const res = await fetch('backend/auth.php?action=get-user', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await res.json();

        if (data.success) {
            const user = data.user;
            document.querySelectorAll('.user-name').forEach(el => el.textContent = user.name);
            document.querySelectorAll('.user-email').forEach(el => el.textContent = user.email);
            document.querySelectorAll('.user-avatar').forEach(el => {
                el.src = user.avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name) + '&background=7C3AED&color=fff';
            });
            document.querySelectorAll('.user-credits').forEach(el => el.textContent = user.credits);
        } else {
            localStorage.removeItem('fw_token');
            window.location.href = 'login.html';
        }
    } catch (err) {
        console.error('Failed to load user:', err);
    }
}

async function loadDashboardStats() {
    const token = localStorage.getItem('fw_token');
    try {
        const res = await fetch('backend/api-handler.php?action=dashboard-stats', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await res.json();

        if (data.success) {
            const stats = data.stats;
            const el = (id) => document.getElementById(id);
            if (el('stat-credits')) el('stat-credits').textContent = formatNumber(stats.credits || 0);
            if (el('stat-images')) el('stat-images').textContent = formatNumber(stats.images || 0);
            if (el('stat-videos')) el('stat-videos').textContent = formatNumber(stats.videos || 0);
            if (el('stat-chats')) el('stat-chats').textContent = formatNumber(stats.chats || 0);
        }
    } catch (err) {
        console.error('Failed to load stats:', err);
    }
}

async function loadRecentActivity() {
    const token = localStorage.getItem('fw_token');
    const tbody = document.getElementById('recent-activity-body');
    if (!tbody) return;

    try {
        const res = await fetch('backend/api-handler.php?action=recent-activity', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await res.json();

        if (data.success && data.activities.length > 0) {
            tbody.innerHTML = data.activities.map(a => `
                <tr>
                    <td><span class="status ${a.type}">${a.type}</span></td>
                    <td>${escapeHtml(a.prompt || a.message || '-')}</td>
                    <td>${a.provider}</td>
                    <td>${a.credits_used}</td>
                    <td>${formatDate(a.created_at)}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-secondary);">No recent activity</td></tr>';
        }
    } catch (err) {
        console.error('Failed to load activity:', err);
    }
}

function formatNumber(num) {
    return Number(num).toLocaleString();
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function logout() {
    localStorage.removeItem('fw_token');
    localStorage.removeItem('fw_user');
    window.location.href = 'login.html';
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i> ${message}`;
    toast.style.cssText = `
        position: fixed; bottom: 24px; right: 24px; z-index: 9999;
        padding: 14px 24px; border-radius: 12px;
        background: ${type === 'success' ? 'rgba(16,185,129,0.15)' : type === 'error' ? 'rgba(239,68,68,0.15)' : 'rgba(6,182,212,0.15)'};
        border: 1px solid ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#06B6D4'};
        color: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#06B6D4'};
        font-family: inherit; font-size: 0.95rem;
        animation: fadeInUp 0.3s ease;
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
