/* ============================================
   Free Would - Admin Panel JavaScript
   ============================================ */

// Admin Auth Check
function checkAdminAuth() {
  const token = localStorage.getItem('fw_token');
  const user = JSON.parse(localStorage.getItem('fw_user') || '{}');
  if (!token || user.role !== 'admin') {
    window.location.href = '../login.html';
    return false;
  }
  return true;
}

// API Base URL
const ADMIN_API = '../backend/admin.php';

// Make API Call
async function adminApi(action, data = {}, method = 'POST') {
  const token = localStorage.getItem('fw_token');
  try {
    const options = {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      }
    };
    if (method === 'POST') {
      options.body = JSON.stringify({ action, ...data });
    }
    const response = await fetch(`${ADMIN_API}?action=${action}`, options);
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Request failed');
    return result;
  } catch (error) {
    console.error('Admin API Error:', error);
    throw error;
  }
}

// Toast Notifications
function showToast(message, type = 'success') {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const icons = {
    success: 'fas fa-check-circle',
    error: 'fas fa-times-circle',
    warning: 'fas fa-exclamation-triangle',
    info: 'fas fa-info-circle'
  };

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <i class="${icons[type]} toast-icon"></i>
    <span class="toast-message">${message}</span>
    <button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
  `;

  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'toastSlideIn 0.3s ease reverse';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

// Confirm Dialog
function showConfirm(title, message, onConfirm) {
  let overlay = document.querySelector('.confirm-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    document.body.appendChild(overlay);
  }

  overlay.innerHTML = `
    <div class="confirm-dialog">
      <div class="confirm-icon"><i class="fas fa-exclamation-triangle" style="color: var(--accent);"></i></div>
      <div class="confirm-title">${title}</div>
      <div class="confirm-message">${message}</div>
      <div class="confirm-buttons">
        <button class="btn-cancel" onclick="closeConfirm()">Cancel</button>
        <button class="btn-danger" id="confirmBtn">Confirm</button>
      </div>
    </div>
  `;

  overlay.classList.add('active');
  document.getElementById('confirmBtn').addEventListener('click', () => {
    closeConfirm();
    onConfirm();
  });
}

function closeConfirm() {
  const overlay = document.querySelector('.confirm-overlay');
  if (overlay) overlay.classList.remove('active');
}

// Modal Functions
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.add('active');
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.remove('active');
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
  }
});

// Mobile Sidebar Toggle
function toggleSidebar() {
  const sidebar = document.querySelector('.admin-sidebar');
  if (sidebar) sidebar.classList.toggle('open');
}

// Format Number
function formatNumber(num) {
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
  if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
  return num.toString();
}

// Format Date
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric'
  });
}

// CSV Export
function exportCSV(data, filename) {
  if (!data.length) return;
  const headers = Object.keys(data[0]);
  const csv = [
    headers.join(','),
    ...data.map(row => headers.map(h => `"${(row[h] || '').toString().replace(/"/g, '""')}"`).join(','))
  ].join('\n');

  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

// Pagination Helper
function renderPagination(containerId, currentPage, totalPages, onPageChange) {
  const container = document.getElementById(containerId);
  if (!container) return;

  let html = `<span class="page-info">Page ${currentPage} of ${totalPages}</span>`;
  html += '<div class="page-buttons">';
  html += `<button class="page-btn" ${currentPage <= 1 ? 'disabled' : ''} onclick="${onPageChange}(${currentPage - 1})"><i class="fas fa-chevron-left"></i></button>`;

  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
      html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="${onPageChange}(${i})">${i}</button>`;
    } else if (i === currentPage - 2 || i === currentPage + 2) {
      html += '<span style="color: var(--text-secondary); padding: 0 5px;">...</span>';
    }
  }

  html += `<button class="page-btn" ${currentPage >= totalPages ? 'disabled' : ''} onclick="${onPageChange}(${currentPage + 1})"><i class="fas fa-chevron-right"></i></button>`;
  html += '</div>';

  container.innerHTML = html;
}

// Tab Switching
function initTabs(containerSelector) {
  const container = document.querySelector(containerSelector);
  if (!container) return;

  const buttons = container.querySelectorAll('.tab-btn');
  const contents = container.querySelectorAll('.tab-content');

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.tab;
      buttons.forEach(b => b.classList.remove('active'));
      contents.forEach(c => c.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById(target)?.classList.add('active');
    });
  });
}

// ============================================
// DASHBOARD FUNCTIONS
// ============================================

async function loadDashboardStats() {
  try {
    const result = await adminApi('get-stats');
    if (result.data) {
      const d = result.data;
      const els = {
        'total-users': formatNumber(d.total_users || 0),
        'total-revenue': '$' + (d.total_revenue || 0).toFixed(2),
        'api-calls': formatNumber(d.api_calls_today || 0),
        'active-subs': formatNumber(d.active_subscriptions || 0)
      };
      Object.entries(els).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
      });
    }
  } catch (err) {
    showToast('Failed to load dashboard stats', 'error');
  }
}

async function loadRecentUsers() {
  try {
    const result = await adminApi('get-users', { limit: 10, page: 1 });
    if (result.data) {
      const tbody = document.getElementById('recent-users-body');
      if (tbody) {
        tbody.innerHTML = result.data.map(u => `
          <tr>
            <td><div class="user-cell"><div class="user-avatar">${u.name.charAt(0).toUpperCase()}</div>${u.name}</div></td>
            <td>${u.email}</td>
            <td><span class="badge-status ${u.role}">${u.role}</span></td>
            <td>${formatDate(u.created_at)}</td>
          </tr>
        `).join('');
      }
    }
  } catch (err) {
    console.error('Failed to load recent users:', err);
  }
}

// ============================================
// USER MANAGEMENT
// ============================================

let usersPage = 1;
let usersSearch = '';
let usersFilter = { role: '', status: '' };

async function loadUsers(page = 1) {
  usersPage = page;
  try {
    const result = await adminApi('get-users', {
      page,
      search: usersSearch,
      role: usersFilter.role,
      status: usersFilter.status
    });
    if (result.data) {
      renderUsersTable(result.data);
      renderPagination('users-pagination', page, result.total_pages || 1, 'loadUsers');
    }
  } catch (err) {
    showToast('Failed to load users', 'error');
  }
}

function renderUsersTable(users) {
  const tbody = document.getElementById('users-table-body');
  if (!tbody) return;

  tbody.innerHTML = users.map(u => `
    <tr>
      <td class="checkbox-cell"><input type="checkbox" value="${u.id}" class="user-checkbox"></td>
      <td>${u.id}</td>
      <td><div class="user-cell"><div class="user-avatar">${u.name.charAt(0).toUpperCase()}</div>${u.name}</div></td>
      <td>${u.email}</td>
      <td><span class="badge-status ${u.role}">${u.role}</span></td>
      <td>${u.credits}</td>
      <td>${u.plan || 'free'}</td>
      <td><span class="badge-status ${u.status}">${u.status}</span></td>
      <td>${formatDate(u.created_at)}</td>
      <td>
        <button class="action-btn" onclick="editUser(${u.id})" title="Edit"><i class="fas fa-edit"></i></button>
        <button class="action-btn" onclick="addCreditsModal(${u.id}, '${u.name}')" title="Add Credits"><i class="fas fa-coins"></i></button>
        <button class="action-btn" onclick="toggleBanUser(${u.id}, '${u.status}')" title="${u.status === 'active' ? 'Ban' : 'Unban'}">
          <i class="fas fa-${u.status === 'active' ? 'ban' : 'check-circle'}"></i>
        </button>
        <button class="action-btn danger" onclick="deleteUser(${u.id})" title="Delete"><i class="fas fa-trash"></i></button>
      </td>
    </tr>
  `).join('');
}

async function editUser(userId) {
  try {
    const result = await adminApi('get-users', { user_id: userId });
    if (result.data && result.data[0]) {
      const u = result.data[0];
      document.getElementById('edit-user-id').value = u.id;
      document.getElementById('edit-user-name').value = u.name;
      document.getElementById('edit-user-email').value = u.email;
      document.getElementById('edit-user-role').value = u.role;
      document.getElementById('edit-user-credits').value = u.credits;
      document.getElementById('edit-user-plan').value = u.plan || 'free';
      document.getElementById('edit-user-status').value = u.status;
      openModal('editUserModal');
    }
  } catch (err) {
    showToast('Failed to load user details', 'error');
  }
}

async function saveUser() {
  const data = {
    user_id: document.getElementById('edit-user-id').value,
    name: document.getElementById('edit-user-name').value,
    email: document.getElementById('edit-user-email').value,
    role: document.getElementById('edit-user-role').value,
    credits: document.getElementById('edit-user-credits').value,
    plan: document.getElementById('edit-user-plan').value,
    status: document.getElementById('edit-user-status').value
  };
  try {
    await adminApi('update-user', data);
    showToast('User updated successfully');
    closeModal('editUserModal');
    loadUsers(usersPage);
  } catch (err) {
    showToast('Failed to update user', 'error');
  }
}

function addCreditsModal(userId, userName) {
  document.getElementById('credits-user-id').value = userId;
  document.getElementById('credits-user-name').textContent = userName;
  document.getElementById('credits-amount').value = '';
  openModal('addCreditsModal');
}

async function addCredits() {
  const data = {
    user_id: document.getElementById('credits-user-id').value,
    credits: document.getElementById('credits-amount').value
  };
  try {
    await adminApi('add-credits', data);
    showToast('Credits added successfully');
    closeModal('addCreditsModal');
    loadUsers(usersPage);
  } catch (err) {
    showToast('Failed to add credits', 'error');
  }
}

function toggleBanUser(userId, currentStatus) {
  const newStatus = currentStatus === 'active' ? 'banned' : 'active';
  const action = newStatus === 'banned' ? 'ban' : 'unban';
  showConfirm('Confirm Action', `Are you sure you want to ${action} this user?`, async () => {
    try {
      await adminApi('ban-user', { user_id: userId, status: newStatus });
      showToast(`User ${action}ned successfully`);
      loadUsers(usersPage);
    } catch (err) {
      showToast(`Failed to ${action} user`, 'error');
    }
  });
}

function deleteUser(userId) {
  showConfirm('Delete User', 'Are you sure you want to delete this user? This action cannot be undone.', async () => {
    try {
      await adminApi('delete-user', { user_id: userId });
      showToast('User deleted successfully');
      loadUsers(usersPage);
    } catch (err) {
      showToast('Failed to delete user', 'error');
    }
  });
}

function exportUsers() {
  const checkboxes = document.querySelectorAll('.user-checkbox:checked');
  const ids = Array.from(checkboxes).map(cb => cb.value);
  adminApi('export-users', { ids }).then(result => {
    if (result.data) {
      exportCSV(result.data, 'users_export.csv');
      showToast('Users exported successfully');
    }
  }).catch(() => showToast('Export failed', 'error'));
}

// ============================================
// API SETTINGS
// ============================================

async function loadApiKeys() {
  try {
    const result = await adminApi('get-api-keys');
    if (result.data) {
      result.data.forEach(key => {
        const prefix = key.provider_name.toLowerCase().replace(/\s+/g, '-');
        const keyInput = document.getElementById(`${prefix}-key`);
        const modelSelect = document.getElementById(`${prefix}-model`);
        const endpointInput = document.getElementById(`${prefix}-endpoint`);
        const priorityInput = document.getElementById(`${prefix}-priority`);
        const rateLimitInput = document.getElementById(`${prefix}-rate-limit`);
        const activeToggle = document.getElementById(`${prefix}-active`);

        if (keyInput) keyInput.value = maskKey(key.api_key);
        if (modelSelect && key.model) modelSelect.value = key.model;
        if (endpointInput && key.endpoint) endpointInput.value = key.endpoint;
        if (priorityInput) priorityInput.value = key.priority;
        if (rateLimitInput) rateLimitInput.value = key.rate_limit;
        if (activeToggle) activeToggle.checked = key.is_active == 1;
      });
    }
  } catch (err) {
    showToast('Failed to load API keys', 'error');
  }
}

function maskKey(key) {
  if (!key || key.length < 10) return key;
  return key.substring(0, 6) + '...' + key.substring(key.length - 4);
}

async function saveApiKey(provider) {
  const prefix = provider.toLowerCase().replace(/\s+/g, '-');
  const data = {
    provider_name: provider,
    api_key: document.getElementById(`${prefix}-key`)?.value || '',
    model: document.getElementById(`${prefix}-model`)?.value || '',
    endpoint: document.getElementById(`${prefix}-endpoint`)?.value || '',
    priority: document.getElementById(`${prefix}-priority`)?.value || 1,
    rate_limit: document.getElementById(`${prefix}-rate-limit`)?.value || 100,
    is_active: document.getElementById(`${prefix}-active`)?.checked ? 1 : 0
  };
  try {
    await adminApi('save-api-key', data);
    showToast(`${provider} settings saved`);
  } catch (err) {
    showToast(`Failed to save ${provider} settings`, 'error');
  }
}

async function testApiKey(provider) {
  try {
    showToast('Testing API connection...', 'info');
    const result = await adminApi('test-api', { provider_name: provider });
    if (result.success) {
      showToast(`${provider} API test successful!`, 'success');
    } else {
      showToast(`${provider} API test failed: ${result.message}`, 'error');
    }
  } catch (err) {
    showToast(`${provider} API test failed`, 'error');
  }
}

// ============================================
// ANALYTICS
// ============================================

async function loadAnalytics() {
  const startDate = document.getElementById('analytics-start')?.value;
  const endDate = document.getElementById('analytics-end')?.value;

  try {
    const result = await adminApi('get-analytics', { start_date: startDate, end_date: endDate });
    if (result.data) {
      const d = result.data;
      const el = (id, val) => {
        const e = document.getElementById(id);
        if (e) e.textContent = val;
      };
      el('total-api-calls', formatNumber(d.total_calls || 0));
      el('total-images', formatNumber(d.total_images || 0));
      el('total-videos', formatNumber(d.total_videos || 0));
      el('total-chats', formatNumber(d.total_chats || 0));
      el('error-rate', (d.error_rate || 0).toFixed(1) + '%');
      el('avg-response', (d.avg_response_time || 0) + 'ms');

      if (d.top_users) {
        const tbody = document.getElementById('top-users-body');
        if (tbody) {
          tbody.innerHTML = d.top_users.map((u, i) => `
            <tr>
              <td>${i + 1}</td>
              <td><div class="user-cell"><div class="user-avatar">${u.name.charAt(0).toUpperCase()}</div>${u.name}</div></td>
              <td>${formatNumber(u.total_calls || 0)}</td>
              <td>${u.total_credits || 0}</td>
            </tr>
          `).join('');
        }
      }
    }
  } catch (err) {
    showToast('Failed to load analytics', 'error');
  }
}

function exportReport() {
  showToast('Generating report...', 'info');
  adminApi('get-analytics', { export: true }).then(result => {
    if (result.data) {
      exportCSV(result.data, 'analytics_report.csv');
      showToast('Report exported successfully');
    }
  }).catch(() => showToast('Export failed', 'error'));
}

// ============================================
// PLAN MANAGEMENT
// ============================================

async function loadPlans() {
  try {
    const result = await adminApi('get-plans');
    if (result.data) {
      renderPlans(result.data);
    }
  } catch (err) {
    showToast('Failed to load plans', 'error');
  }
}

function renderPlans(plans) {
  const container = document.getElementById('plans-container');
  if (!container) return;

  container.innerHTML = plans.map(p => `
    <div class="stat-card" style="border: 1px solid ${p.is_popular ? 'var(--primary)' : 'var(--card-border)'};">
      <div class="stat-header">
        <span class="badge-status ${p.status}">${p.status}</span>
        ${p.is_popular ? '<span class="badge-status admin">Popular</span>' : ''}
      </div>
      <h3 style="font-size: 20px; color: var(--text-primary); margin: 10px 0;">${p.name}</h3>
      <div style="font-size: 28px; font-weight: 800; color: var(--text-primary); margin-bottom: 10px;">
        $${parseFloat(p.price).toFixed(2)}<span style="font-size: 14px; color: var(--text-secondary);">/${p.duration}</span>
      </div>
      <div style="color: var(--text-secondary); font-size: 13px; margin-bottom: 15px;">
        ${p.credits} credits | ${p.image_limit} images | ${p.video_limit} videos
      </div>
      <div style="display: flex; gap: 10px;">
        <button class="action-btn" onclick="editPlan(${p.id})"><i class="fas fa-edit"></i> Edit</button>
        <button class="action-btn danger" onclick="deletePlan(${p.id})"><i class="fas fa-trash"></i> Delete</button>
      </div>
    </div>
  `).join('');
}

async function editPlan(planId) {
  try {
    const result = await adminApi('get-plans', { plan_id: planId });
    if (result.data && result.data[0]) {
      const p = result.data[0];
      document.getElementById('plan-id').value = p.id;
      document.getElementById('plan-name').value = p.name;
      document.getElementById('plan-price').value = p.price;
      document.getElementById('plan-credits').value = p.credits;
      document.getElementById('plan-duration').value = p.duration;
      document.getElementById('plan-image-limit').value = p.image_limit;
      document.getElementById('plan-video-limit').value = p.video_limit;
      document.getElementById('plan-chat-limit').value = p.chat_limit;
      document.getElementById('plan-popular').checked = p.is_popular == 1;
      document.getElementById('plan-status').value = p.status;

      const features = typeof p.features === 'string' ? JSON.parse(p.features) : (p.features || []);
      renderFeatureList(features);
      openModal('editPlanModal');
    }
  } catch (err) {
    showToast('Failed to load plan details', 'error');
  }
}

function renderFeatureList(features) {
  const list = document.getElementById('feature-list');
  if (!list) return;
  list.innerHTML = features.map((f, i) => `
    <li>
      <span>${f}</span>
      <button class="remove-feature" onclick="removeFeature(${i})"><i class="fas fa-times"></i></button>
    </li>
  `).join('');
}

function addFeature() {
  const input = document.getElementById('new-feature');
  if (!input || !input.value.trim()) return;
  const list = document.getElementById('feature-list');
  const features = getCurrentFeatures();
  features.push(input.value.trim());
  renderFeatureList(features);
  input.value = '';
}

function removeFeature(index) {
  const features = getCurrentFeatures();
  features.splice(index, 1);
  renderFeatureList(features);
}

function getCurrentFeatures() {
  const items = document.querySelectorAll('#feature-list li span');
  return Array.from(items).map(el => el.textContent);
}

async function savePlan() {
  const features = getCurrentFeatures();
  const data = {
    plan_id: document.getElementById('plan-id').value || null,
    name: document.getElementById('plan-name').value,
    price: document.getElementById('plan-price').value,
    credits: document.getElementById('plan-credits').value,
    duration: document.getElementById('plan-duration').value,
    image_limit: document.getElementById('plan-image-limit').value,
    video_limit: document.getElementById('plan-video-limit').value,
    chat_limit: document.getElementById('plan-chat-limit').value,
    is_popular: document.getElementById('plan-popular').checked ? 1 : 0,
    status: document.getElementById('plan-status').value,
    features: JSON.stringify(features)
  };
  try {
    await adminApi('save-plan', data);
    showToast('Plan saved successfully');
    closeModal('editPlanModal');
    loadPlans();
  } catch (err) {
    showToast('Failed to save plan', 'error');
  }
}

function deletePlan(planId) {
  showConfirm('Delete Plan', 'Are you sure you want to delete this plan?', async () => {
    try {
      await adminApi('delete-plan', { plan_id: planId });
      showToast('Plan deleted successfully');
      loadPlans();
    } catch (err) {
      showToast('Failed to delete plan', 'error');
    }
  });
}

// ============================================
// SITE SETTINGS
// ============================================

async function loadSettings() {
  try {
    const result = await adminApi('get-settings');
    if (result.data) {
      const s = result.data;
      const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) {
          if (el.type === 'checkbox') el.checked = val == 1;
          else el.value = val || '';
        }
      };
      set('setting-site-name', s.site_name);
      set('setting-site-desc', s.site_description);
      set('setting-site-email', s.site_email);
      set('setting-default-credits', s.default_credits);
      set('setting-maintenance', s.maintenance_mode);
      set('setting-maintenance-msg', s.maintenance_message);
      set('setting-allow-registration', s.allow_registration);
      set('setting-email-verification', s.email_verification);
      set('setting-primary-color', s.primary_color);
      set('setting-secondary-color', s.secondary_color);
      set('setting-twitter', s.social_twitter);
      set('setting-instagram', s.social_instagram);
      set('setting-discord', s.social_discord);
      set('setting-github', s.social_github);
    }
  } catch (err) {
    showToast('Failed to load settings', 'error');
  }
}

async function saveSettings() {
  const data = {
    site_name: document.getElementById('setting-site-name')?.value,
    site_description: document.getElementById('setting-site-desc')?.value,
    site_email: document.getElementById('setting-site-email')?.value,
    default_credits: document.getElementById('setting-default-credits')?.value,
    maintenance_mode: document.getElementById('setting-maintenance')?.checked ? 1 : 0,
    maintenance_message: document.getElementById('setting-maintenance-msg')?.value,
    allow_registration: document.getElementById('setting-allow-registration')?.checked ? 1 : 0,
    email_verification: document.getElementById('setting-email-verification')?.checked ? 1 : 0,
    primary_color: document.getElementById('setting-primary-color')?.value,
    secondary_color: document.getElementById('setting-secondary-color')?.value,
    social_twitter: document.getElementById('setting-twitter')?.value,
    social_instagram: document.getElementById('setting-instagram')?.value,
    social_discord: document.getElementById('setting-discord')?.value,
    social_github: document.getElementById('setting-github')?.value
  };
  try {
    await adminApi('save-settings', data);
    showToast('Settings saved successfully');
  } catch (err) {
    showToast('Failed to save settings', 'error');
  }
}

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', () => {
  // Check admin auth
  if (!checkAdminAuth()) return;

  // Set admin name in topbar
  const user = JSON.parse(localStorage.getItem('fw_user') || '{}');
  const adminNameEl = document.getElementById('admin-name');
  const adminAvatarEl = document.getElementById('admin-avatar');
  if (adminNameEl && user.name) adminNameEl.textContent = user.name;
  if (adminAvatarEl && user.name) adminAvatarEl.textContent = user.name.charAt(0).toUpperCase();

  // Highlight active sidebar link
  const currentPage = window.location.pathname.split('/').pop();
  document.querySelectorAll('.sidebar-nav a').forEach(link => {
    if (link.getAttribute('href') === currentPage) {
      link.classList.add('active');
    }
  });

  // Init tabs
  initTabs('.admin-content');

  // Load page-specific data
  if (currentPage === 'index.html') {
    loadDashboardStats();
    loadRecentUsers();
  } else if (currentPage === 'users.html') {
    loadUsers();
  } else if (currentPage === 'api-settings.html') {
    loadApiKeys();
  } else if (currentPage === 'analytics.html') {
    loadAnalytics();
  } else if (currentPage === 'plans.html') {
    loadPlans();
  } else if (currentPage === 'settings.html') {
    loadSettings();
  }

  // Search debounce
  const searchInput = document.getElementById('user-search');
  if (searchInput) {
    let timeout;
    searchInput.addEventListener('input', () => {
      clearTimeout(timeout);
      timeout = setTimeout(() => {
        usersSearch = searchInput.value;
        loadUsers(1);
      }, 300);
    });
  }

  // Filter change
  document.querySelectorAll('.filter-select').forEach(select => {
    select.addEventListener('change', () => {
      usersFilter.role = document.getElementById('filter-role')?.value || '';
      usersFilter.status = document.getElementById('filter-status')?.value || '';
      loadUsers(1);
    });
  });

  // Close sidebar on mobile link click
  document.querySelectorAll('.sidebar-nav a').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 768) {
        document.querySelector('.admin-sidebar')?.classList.remove('open');
      }
    });
  });
});
