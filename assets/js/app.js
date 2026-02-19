/* =============================================================
   Expense Tracker — Core App JavaScript
   Handles API communication, state management, and UI rendering
   ============================================================= */

const API_BASE = 'api';

/* ---- Helpers ---- */
const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/* ---- State ---- */
const State = {
  user: null,
  token: null,
  categories: [],
  transactions: [],
  currentPage: 'dashboard',
  theme: 'dark',
};

/* ============================================================
   THEME MANAGEMENT
   ============================================================ */

/**
 * Apply a theme ('dark' | 'light') to the document and update all UI elements.
 */
function applyTheme(theme, save = true) {
  State.theme = theme;
  const isLight = theme === 'light';

  document.documentElement.setAttribute('data-theme', isLight ? 'light' : 'dark');

  // Update sidebar toggle button
  const icon = document.getElementById('theme-icon');
  const label = document.getElementById('theme-label');
  if (icon) icon.textContent = isLight ? '☀️' : '🌙';
  if (label) label.textContent = isLight ? 'Dark Mode' : 'Light Mode';

  // Update mobile topbar button
  const topbarBtn = document.getElementById('theme-toggle-topbar');
  if (topbarBtn) topbarBtn.textContent = isLight ? '☀️' : '🌙';

  // If charts exist, update their text colours to match the new theme
  const chartTextColor = isLight ? '#64748b' : '#94a3b8';
  const chartGridColor = isLight ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.04)';
  const chartBgColor = isLight ? '#ffffff' : '#1a1d27';
  const chartBorderColor = isLight ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.07)';

  [dashboardCharts, analyticsCharts].forEach(chartGroup => {
    Object.values(chartGroup).forEach(chart => {
      if (!chart) return;
      // Update all scales
      Object.values(chart.options.scales || {}).forEach(scale => {
        if (scale.ticks) scale.ticks.color = chartTextColor;
        if (scale.grid) scale.grid.color = chartGridColor;
        if (scale.r) { scale.grid.color = chartGridColor; scale.ticks.color = chartTextColor; }
      });
      // Update legend and tooltip
      if (chart.options.plugins?.legend?.labels)
        chart.options.plugins.legend.labels.color = chartTextColor;
      if (chart.options.plugins?.tooltip) {
        chart.options.plugins.tooltip.backgroundColor = chartBgColor;
        chart.options.plugins.tooltip.titleColor = isLight ? '#0f172a' : '#f1f5f9';
        chart.options.plugins.tooltip.bodyColor = chartTextColor;
        chart.options.plugins.tooltip.borderColor = chartBorderColor;
      }
      chart.update('none'); // update without animation
    });
  });

  if (save) localStorage.setItem('et_theme', theme);
}

function toggleTheme() {
  applyTheme(State.theme === 'dark' ? 'light' : 'dark');
}

/**
 * Load saved theme preference — called before app init to avoid flash of wrong theme.
 */
function loadTheme() {
  const saved = localStorage.getItem('et_theme') || 'dark';
  applyTheme(saved, false); // don't re-save on load
}


/* ============================================================
   UTILITIES
   ============================================================ */

const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

/**
 * Format number as currency
 */
function formatCurrency(amount, showSign = false) {
  const abs = Math.abs(parseFloat(amount) || 0);
  const formatted = abs.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const prefix = showSign ? (amount >= 0 ? '+$' : '-$') : '$';
  return prefix + formatted;
}

/**
 * Format a date string for display
 */
function formatDate(str) {
  if (!str) return '—';
  const d = new Date(str + 'T00:00:00');
  return d.toLocaleDateString('en-CA', { month: 'short', day: 'numeric', year: 'numeric' });
}

/**
 * Get today's date as YYYY-MM-DD (local timezone, not UTC)
 */
function today() {
  const d = new Date();
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

/**
 * Show toast notification
 */
function showToast(msg, type = 'info', duration = 3500) {
  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
  const container = $('#toast-container');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<span class="toast-icon">${icons[type]}</span><span>${msg}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

/* ============================================================
   API HELPERS
   ============================================================ */

async function apiRequest(path, method = 'GET', body = null, params = {}) {
  const url = new URL(`${API_BASE}/${path}`, window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/'));
  Object.entries(params).forEach(([k, v]) => { if (v !== null && v !== '') url.searchParams.set(k, v); });

  const headers = { 'Content-Type': 'application/json' };
  if (State.token) headers['Authorization'] = `Bearer ${State.token}`;

  const opts = { method, headers };
  if (body && method !== 'GET') opts.body = JSON.stringify(body);

  const resp = await fetch(url.toString(), opts);

  // Auto-logout on 401 (expired/invalid token)
  if (resp.status === 401) {
    clearAuth();
    showToast('Session expired. Please sign in again.', 'warning');
    setTimeout(() => showAuthScreen(), 800);
    return { success: false, message: 'Unauthorized' };
  }

  // Try to parse JSON; if the server returned an HTML error page, surface a clean error
  let json;
  try {
    json = await resp.json();
  } catch {
    return { success: false, message: `Server error (HTTP ${resp.status}). Check Apache/PHP logs.` };
  }
  return json;
}

/* ============================================================
   AUTH
   ============================================================ */

function loadAuth() {
  State.token = localStorage.getItem('et_token');
  try { State.user = JSON.parse(localStorage.getItem('et_user')); } catch { State.user = null; }
}

function saveAuth(token, user) {
  State.token = token;
  State.user = user;
  localStorage.setItem('et_token', token);
  localStorage.setItem('et_user', JSON.stringify(user));
}

function clearAuth() {
  State.token = null;
  State.user = null;
  localStorage.removeItem('et_token');
  localStorage.removeItem('et_user');
}

function isLoggedIn() { return !!State.token && !!State.user; }

/* ============================================================
   ROUTING
   ============================================================ */

function navigate(page) {
  State.currentPage = page;
  $$('.page-section').forEach(s => s.classList.add('hidden'));
  const section = $(`#page-${page}`);
  if (section) section.classList.remove('hidden');

  $$('.nav-link').forEach(l => {
    l.classList.toggle('active', l.dataset.page === page);
  });

  // Close mobile sidebar
  closeMobileSidebar();

  // Load page data
  switch (page) {
    case 'dashboard': loadDashboard(); break;
    case 'transactions': loadTransactions(); break;
    case 'budgets': loadBudgets(); break;
    case 'analytics': loadAnalytics(); break;
  }
}

/* ============================================================
   APP INITIALIZATION
   ============================================================ */

async function initApp() {
  loadAuth();

  if (!isLoggedIn()) {
    showAuthScreen();
    return;
  }

  showAppScreen();
  updateUserDisplay();
  await loadCategories();
  navigate('dashboard');
}

function showAuthScreen() {
  $('#auth-screen').classList.remove('hidden');
  $('#app-screen').classList.add('hidden');
  showLoginForm();
}

function showAppScreen() {
  $('#auth-screen').classList.add('hidden');
  $('#app-screen').classList.remove('hidden');
}

function updateUserDisplay() {
  const user = State.user;
  if (!user) return;
  const initials = user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  $$('.user-avatar-initials').forEach(el => el.textContent = initials);
  $$('.user-display-name').forEach(el => el.textContent = user.name);
  $$('.user-display-email').forEach(el => el.textContent = user.email);
}

/* ============================================================
   AUTH UI
   ============================================================ */

function showLoginForm() {
  $('#login-form-wrap').classList.remove('hidden');
  $('#register-form-wrap').classList.add('hidden');
}

function showRegisterForm() {
  $('#login-form-wrap').classList.add('hidden');
  $('#register-form-wrap').classList.remove('hidden');
}

async function handleLogin(e) {
  e.preventDefault();
  const btn = $('#login-submit-btn');
  const email = $('#login-email').value.trim();
  const pass = $('#login-password').value;

  setButtonLoading(btn, true, 'Signing in...');
  try {
    const resp = await apiRequest('auth/login.php', 'POST', { email, password: pass });
    if (resp.success) {
      saveAuth(resp.data.token, resp.data.user);
      showToast(`Welcome back, ${resp.data.user.name}! 👋`, 'success');
      showAppScreen();
      updateUserDisplay();
      await loadCategories();
      navigate('dashboard');
    } else {
      showToast(resp.message || 'Login failed', 'error');
    }
  } catch {
    showToast('Network error. Please check your connection.', 'error');
  }
  setButtonLoading(btn, false, 'Sign In');
}

async function handleRegister(e) {
  e.preventDefault();
  const btn = $('#register-submit-btn');
  const name = $('#register-name').value.trim();
  const email = $('#register-email').value.trim();
  const pass = $('#register-password').value;
  const conf = $('#register-confirm').value;

  if (pass !== conf) { showToast('Passwords do not match', 'error'); return; }
  if (pass.length < 6) { showToast('Password must be at least 6 characters', 'warning'); return; }

  setButtonLoading(btn, true, 'Creating account...');
  try {
    const resp = await apiRequest('auth/register.php', 'POST', { name, email, password: pass });
    if (resp.success) {
      saveAuth(resp.data.token, resp.data.user);
      showToast(`Account created! Welcome, ${resp.data.user.name}! 🎉`, 'success');
      showAppScreen();
      updateUserDisplay();
      await loadCategories();
      navigate('dashboard');
    } else {
      showToast(resp.message || 'Registration failed', 'error');
    }
  } catch {
    showToast('Network error. Please check your connection.', 'error');
  }
  setButtonLoading(btn, false, 'Create Account');
}

function handleLogout() {
  clearAuth();
  showToast('Logged out successfully', 'info');
  setTimeout(() => showAuthScreen(), 300);
}

function setButtonLoading(btn, loading, label) {
  btn.disabled = loading;
  btn.innerHTML = loading
    ? `<span class="spinner"></span> ${label}`
    : label;
}

/* ============================================================
   CATEGORIES
   ============================================================ */

async function loadCategories() {
  try {
    const resp = await apiRequest('categories/index.php');
    if (resp.success) {
      // Deduplicate by id to prevent triple-loading when called multiple times
      const seen = new Set();
      State.categories = resp.data.filter(c => {
        if (seen.has(c.id)) return false;
        seen.add(c.id);
        return true;
      });
    }
  } catch { /* silent fail */ }
}

function getCategoryById(id) {
  return State.categories.find(c => c.id == id) || null;
}

function renderCategoryOptions(selectEl, filter = null) {
  selectEl.innerHTML = '<option value="">Select Category</option>';
  const filtered = filter ? State.categories.filter(c => c.type === filter) : State.categories;
  const groups = { Income: [], Expense: [] };
  filtered.forEach(c => groups[c.type].push(c));
  ['Income', 'Expense'].forEach(type => {
    if (groups[type].length === 0) return;
    const og = document.createElement('optgroup');
    og.label = type;
    groups[type].forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = `${c.icon} ${c.name}`;
      og.appendChild(opt);
    });
    selectEl.appendChild(og);
  });
}

/* ============================================================
   DASHBOARD
   ============================================================ */

let dashboardCharts = {};

async function loadDashboard() {
  loadDashboardSummary();
  loadDashboardCharts();
  loadTopExpenses();
  loadRecentTransactions();
}

async function loadDashboardSummary() {
  try {
    const resp = await apiRequest('dashboard/index.php', 'GET', null, { endpoint: 'summary' });
    if (!resp.success) return;
    const d = resp.data;

    $('#dash-balance').textContent = formatCurrency(d.all_time_balance);
    $('#dash-income').textContent = formatCurrency(d.total_income);
    $('#dash-expense').textContent = formatCurrency(d.total_expense);
    $('#dash-net').textContent = formatCurrency(d.net_this_month);
    $('#dash-txn-count').textContent = d.transaction_count + ' transactions';
    $('#dash-month').textContent = d.month;

    // Color net based on positive/negative
    const netEl = $('#dash-net');
    netEl.className = 'stat-value ' + (d.net_this_month >= 0 ? 'amount-income' : 'amount-expense');
  } catch { /* silent */ }
}

async function loadDashboardCharts() {
  // Destroy previous charts
  Object.values(dashboardCharts).forEach(c => { if (c) c.destroy(); });
  dashboardCharts = {};

  await Promise.all([
    loadMonthlyTrendChart(),
    loadCategoryPieChart(),
  ]);
}

async function loadMonthlyTrendChart() {
  try {
    const resp = await apiRequest('dashboard/index.php', 'GET', null, { endpoint: 'monthly-trend' });
    if (!resp.success || !resp.data.length) return;
    const data = resp.data;

    const ctx = $('#chart-monthly');
    if (!ctx) return;

    dashboardCharts.monthly = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.map(d => d.month_label),
        datasets: [
          {
            label: 'Income',
            data: data.map(d => d.income),
            backgroundColor: 'rgba(16,185,129,0.7)',
            borderColor: '#10b981',
            borderWidth: 0,
            borderRadius: 6,
          },
          {
            label: 'Expenses',
            data: data.map(d => d.expense),
            backgroundColor: 'rgba(239,68,68,0.7)',
            borderColor: '#ef4444',
            borderWidth: 0,
            borderRadius: 6,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#94a3b8', font: { family: 'Inter' } } },
          tooltip: {
            backgroundColor: '#1a1d27',
            borderColor: 'rgba(255,255,255,0.07)',
            borderWidth: 1,
            titleColor: '#f1f5f9',
            bodyColor: '#94a3b8',
            callbacks: { label: ctx => ` ${ctx.dataset.label}: ${formatCurrency(ctx.raw)}` }
          }
        },
        scales: {
          x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { family: 'Inter' } } },
          y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { family: 'Inter' }, callback: v => '$' + v.toLocaleString() } }
        }
      }
    });
  } catch (err) { console.error(err); }
}

async function loadCategoryPieChart() {
  try {
    const resp = await apiRequest('dashboard/index.php', 'GET', null, { endpoint: 'by-category' });
    if (!resp.success || !resp.data.categories.length) {
      $('#dash-cat-legend').innerHTML = '<p class="text-muted text-sm text-center mt-3">No expenses this month</p>';
      return;
    }
    const cats = resp.data.categories.slice(0, 7);

    const ctx = $('#chart-category');
    if (!ctx) return;

    dashboardCharts.category = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: cats.map(c => c.name),
        datasets: [{
          data: cats.map(c => c.total),
          backgroundColor: cats.map(c => c.color + 'cc'),
          borderColor: cats.map(c => c.color),
          borderWidth: 2,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1a1d27',
            borderColor: 'rgba(255,255,255,0.07)',
            borderWidth: 1,
            titleColor: '#f1f5f9',
            bodyColor: '#94a3b8',
            callbacks: {
              label: ctx => ` ${ctx.label}: ${formatCurrency(ctx.raw)} (${cats[ctx.dataIndex].percentage}%)`
            }
          }
        }
      }
    });

    // Render legend
    $('#dash-cat-legend').innerHTML = cats.map(c =>
      `<div class="legend-item">
        <span class="legend-dot" style="background:${c.color}"></span>
        <span class="legend-name">${c.icon} ${c.name}</span>
        <span class="legend-pct">${c.percentage}%</span>
      </div>`
    ).join('');
  } catch (err) { console.error(err); }
}

async function loadTopExpenses() {
  try {
    const resp = await apiRequest('dashboard/index.php', 'GET', null, { endpoint: 'top-expenses' });
    if (!resp.success) return;
    const list = resp.data;

    const container = $('#top-expenses-list');
    if (!list.length) {
      container.innerHTML = '<p class="text-muted text-sm text-center">No expenses this month</p>';
      return;
    }

    const max = list[0]?.total || 1;
    container.innerHTML = list.map((item, i) =>
      `<div style="margin-bottom: 14px;">
        <div class="flex items-center justify-between mb-1">
          <span class="text-sm">${item.icon} ${item.name}</span>
          <span class="text-sm font-bold amount-expense">${formatCurrency(item.total)}</span>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width:${(item.total / max * 100).toFixed(1)}%;background:${item.color}"></div>
        </div>
      </div>`
    ).join('');
  } catch { /* silent */ }
}

async function loadRecentTransactions() {
  try {
    const resp = await apiRequest('transactions/index.php', 'GET', null, { limit: 5, sort: 'date', order: 'desc' });
    if (!resp.success) return;
    const txns = resp.data.transactions;

    const tbody = $('#recent-txn-tbody');
    if (!txns.length) {
      tbody.innerHTML = `<tr><td colspan="4" class="text-center"><div class="empty-state"><div class="empty-state-icon">📭</div><div class="empty-state-title">No transactions yet</div></div></td></tr>`;
      return;
    }
    tbody.innerHTML = txns.map(t => renderTransactionRow(t)).join('');
  } catch { /* silent */ }
}

/* ============================================================
   TRANSACTIONS PAGE
   ============================================================ */

let txnFilters = { sort: 'date', order: 'desc', type: '', category_id: '', date_from: '', date_to: '', search: '' };
let txnPagination = { page: 1, limit: 20, total: 0 };
let txnEditId = null;

async function loadTransactions() {
  const tbody = $('#txn-tbody');
  if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center"><div style="padding:40px;color:var(--text-muted)">Loading...</div></td></tr>`;

  // Populate category filter
  const catFilter = $('#txn-filter-category');
  if (catFilter) {
    catFilter.innerHTML = '<option value="">All Categories</option>';
    State.categories.forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = `${c.icon} ${c.name}`;
      catFilter.appendChild(opt);
    });
  }

  await fetchTransactions();
}

async function fetchTransactions() {
  const params = {
    ...txnFilters,
    page: txnPagination.page,
    limit: txnPagination.limit
  };

  try {
    const resp = await apiRequest('transactions/index.php', 'GET', null, params);
    if (!resp.success) { showToast(resp.message, 'error'); return; }

    State.transactions = resp.data.transactions;
    txnPagination = { ...txnPagination, ...resp.data.pagination };

    renderTransactionsTable();
    renderPagination();
    updateTxnSummary(resp.data.transactions);
  } catch {
    showToast('Failed to load transactions', 'error');
  }
}

function renderTransactionsTable() {
  const tbody = $('#txn-tbody');
  if (!tbody) return;

  if (!State.transactions.length) {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">💸</div><div class="empty-state-title">No transactions found</div><div class="empty-state-text">Add your first transaction using the button above.</div></div></td></tr>`;
    return;
  }

  tbody.innerHTML = State.transactions.map(t => renderTransactionRow(t, true)).join('');

  // Bind action buttons
  tbody.querySelectorAll('.txn-edit-btn').forEach(btn => {
    btn.addEventListener('click', () => openEditModal(parseInt(btn.dataset.id)));
  });
  tbody.querySelectorAll('.txn-delete-btn').forEach(btn => {
    btn.addEventListener('click', () => deleteTransaction(parseInt(btn.dataset.id)));
  });
}

function renderTransactionRow(t, showActions = false) {
  const amountClass = t.type === 'Income' ? 'amount-income' : 'amount-expense';
  const amountPrefix = t.type === 'Income' ? '+' : '-';
  const actions = showActions ? `
    <td>
      <div class="flex gap-2">
        <button class="btn btn-sm btn-secondary btn-icon txn-edit-btn" data-id="${t.id}" title="Edit">✏️</button>
        <button class="btn btn-sm btn-danger   btn-icon txn-delete-btn" data-id="${t.id}" title="Delete">🗑️</button>
      </div>
    </td>` : '';

  return `
    <tr>
      <td>${formatDate(t.date)}</td>
      <td>
        <span class="category-chip" style="border-color:${t.category_color}40;color:${t.category_color}">
          ${t.category_icon} ${t.category_name}
        </span>
      </td>
      <td class="${amountClass}">${amountPrefix}${formatCurrency(t.amount)}</td>
      <td class="text-muted">${t.note || '—'}</td>
      <td><span class="badge badge-${t.type === 'Income' ? 'income' : 'expense'}">${t.type}</span></td>
      ${actions}
    </tr>`;
}

function renderPagination() {
  const pg = txnPagination;
  const container = $('#txn-pagination');
  if (!container || pg.pages <= 1) { if (container) container.innerHTML = ''; return; }

  let html = `<div class="flex items-center gap-2 justify-between mt-4" style="font-size:.85rem;color:var(--text-muted)">
    <span>Showing ${((pg.page - 1) * pg.limit) + 1}–${Math.min(pg.page * pg.limit, pg.total)} of ${pg.total}</span>
    <div class="flex gap-2">`;

  if (pg.page > 1) {
    html += `<button class="btn btn-sm btn-secondary" onclick="changePage(${pg.page - 1})">← Prev</button>`;
  }
  for (let i = Math.max(1, pg.page - 2); i <= Math.min(pg.pages, pg.page + 2); i++) {
    html += `<button class="btn btn-sm ${i === pg.page ? 'btn-primary' : 'btn-secondary'}" onclick="changePage(${i})">${i}</button>`;
  }
  if (pg.page < pg.pages) {
    html += `<button class="btn btn-sm btn-secondary" onclick="changePage(${pg.page + 1})">Next →</button>`;
  }
  html += `</div></div>`;
  container.innerHTML = html;
}

function changePage(p) {
  txnPagination.page = p;
  fetchTransactions();
  $(`#page-transactions`)?.scrollIntoView({ behavior: 'smooth' });
}

function updateTxnSummary(transactions) {
  let income = 0, expense = 0;
  transactions.forEach(t => {
    if (t.type === 'Income') income += parseFloat(t.amount) || 0;
    else expense += parseFloat(t.amount) || 0;
  });
  const incEl = $('#txn-summary-income');
  const expEl = $('#txn-summary-expense');
  if (incEl) incEl.textContent = formatCurrency(income);
  if (expEl) expEl.textContent = formatCurrency(expense);
}

/* ---- Add/Edit Transaction Modal ---- */

function openAddModal() {
  txnEditId = null;
  $('#modal-txn-title').textContent = 'Add Transaction';
  $('#txn-form').reset();
  $('#txn-date').value = today();
  $('#txn-type-income').classList.remove('active');
  $('#txn-type-expense').classList.add('active');
  renderCategoryOptions($('#txn-category'), 'Expense');
  openModal('modal-transaction');
}

function openEditModal(id) {
  const t = State.transactions.find(tx => tx.id === id);
  if (!t) return;
  txnEditId = id;
  $('#modal-txn-title').textContent = 'Edit Transaction';

  // Set type
  const isIncome = t.type === 'Income';
  $('#txn-type-income').classList.toggle('active', isIncome);
  $('#txn-type-expense').classList.toggle('active', !isIncome);
  renderCategoryOptions($('#txn-category'), isIncome ? 'Income' : 'Expense');

  $('#txn-category').value = t.category_id;
  $('#txn-amount').value = t.amount;
  $('#txn-date').value = t.date;
  $('#txn-note').value = t.note || '';

  openModal('modal-transaction');
}

async function handleSaveTransaction(e) {
  e.preventDefault();
  const btn = $('#txn-save-btn');
  const categoryId = parseInt($('#txn-category').value);
  const amount = parseFloat($('#txn-amount').value);
  const date = $('#txn-date').value;
  const note = $('#txn-note').value.trim();

  if (!categoryId || !amount || !date) {
    showToast('Please fill in all required fields', 'warning');
    return;
  }

  setButtonLoading(btn, true, 'Saving...');

  try {
    let resp;
    if (txnEditId) {
      resp = await apiRequest(`transactions/index.php?id=${txnEditId}`, 'PUT', { category_id: categoryId, amount, date, note });
    } else {
      resp = await apiRequest('transactions/index.php', 'POST', { category_id: categoryId, amount, date, note });
    }

    if (resp.success) {
      showToast(txnEditId ? 'Transaction updated!' : 'Transaction added! 💰', 'success');
      closeModal('modal-transaction');
      fetchTransactions();
      loadDashboardSummary();
    } else {
      showToast(resp.message || 'Save failed', 'error');
    }
  } catch {
    showToast('Network error', 'error');
  }

  setButtonLoading(btn, false, 'Save Transaction');
}

async function deleteTransaction(id) {
  if (!confirm('Delete this transaction? This cannot be undone.')) return;
  try {
    const resp = await apiRequest(`transactions/index.php?id=${id}`, 'DELETE');
    if (resp.success) {
      showToast('Transaction deleted', 'success');
      fetchTransactions();
      loadDashboardSummary();
    } else {
      showToast(resp.message || 'Delete failed', 'error');
    }
  } catch {
    showToast('Network error', 'error');
  }
}

function setTransactionType(type) {
  const isIncome = type === 'Income';
  $('#txn-type-income').classList.toggle('active', isIncome);
  $('#txn-type-expense').classList.toggle('active', !isIncome);
  renderCategoryOptions($('#txn-category'), type);
}

/* ============================================================
   ANALYTICS PAGE
   ============================================================ */

let analyticsCharts = {};

async function loadAnalytics() {
  Object.values(analyticsCharts).forEach(c => { if (c) c.destroy(); });
  analyticsCharts = {};

  await Promise.all([
    loadAnalyticsMonthly(),
    loadAnalyticsCategoryExpense(),
    loadAnalyticsCategoryIncome(),
  ]);
}

async function loadAnalyticsMonthly() {
  try {
    const resp = await apiRequest('dashboard/index.php', 'GET', null, { endpoint: 'monthly-trend' });
    if (!resp.success || !resp.data.length) return;
    const data = resp.data;

    const ctx = $('#chart-analytics-monthly');
    if (!ctx) return;

    analyticsCharts.monthly = new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.map(d => d.month_label),
        datasets: [
          {
            label: 'Income',
            data: data.map(d => d.income),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#10b981',
            pointRadius: 5,
          },
          {
            label: 'Expenses',
            data: data.map(d => d.expense),
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239,68,68,0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#ef4444',
            pointRadius: 5,
          }
        ]
      },
      options: getChartOptions('6-Month Income vs Expense Trend')
    });
  } catch (err) { console.error(err); }
}

async function loadAnalyticsCategoryExpense() {
  try {
    const resp = await apiRequest('dashboard/index.php', 'GET', null, { endpoint: 'by-category', type: 'Expense' });
    if (!resp.success || !resp.data.categories.length) return;
    const cats = resp.data.categories;

    const ctx = $('#chart-analytics-expense-cat');
    if (!ctx) return;

    analyticsCharts.expenseCat = new Chart(ctx, {
      type: 'polarArea',
      data: {
        labels: cats.map(c => `${c.icon} ${c.name}`),
        datasets: [{
          data: cats.map(c => c.total),
          backgroundColor: cats.map(c => c.color + '99'),
          borderColor: cats.map(c => c.color),
          borderWidth: 2,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 11 } } },
          tooltip: {
            backgroundColor: '#1a1d27',
            borderColor: 'rgba(255,255,255,0.07)',
            borderWidth: 1,
            titleColor: '#f1f5f9',
            bodyColor: '#94a3b8',
            callbacks: { label: ctx => ` ${formatCurrency(ctx.raw)} (${cats[ctx.dataIndex].percentage}%)` }
          }
        },
        scales: { r: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b' } } }
      }
    });
  } catch (err) { console.error(err); }
}

async function loadAnalyticsCategoryIncome() {
  try {
    const resp = await apiRequest('dashboard/index.php', 'GET', null, { endpoint: 'by-category', type: 'Income' });
    if (!resp.success || !resp.data.categories.length) return;
    const cats = resp.data.categories;

    const ctx = $('#chart-analytics-income-cat');
    if (!ctx) return;

    analyticsCharts.incomeCat = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: cats.map(c => `${c.icon} ${c.name}`),
        datasets: [{
          data: cats.map(c => c.total),
          backgroundColor: cats.map(c => c.color + 'cc'),
          borderColor: cats.map(c => c.color),
          borderWidth: 2,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
          legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 11 } } },
          tooltip: {
            backgroundColor: '#1a1d27',
            borderColor: 'rgba(255,255,255,0.07)',
            borderWidth: 1,
            titleColor: '#f1f5f9',
            bodyColor: '#94a3b8',
            callbacks: { label: ctx => ` ${formatCurrency(ctx.raw)} (${cats[ctx.dataIndex].percentage}%)` }
          }
        }
      }
    });
  } catch (err) { console.error(err); }
}

function getChartOptions(title = '') {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: title ? { display: true, text: title, color: '#94a3b8', font: { family: 'Inter', size: 13 } } : { display: false },
      legend: { labels: { color: '#94a3b8', font: { family: 'Inter' } } },
      tooltip: {
        backgroundColor: '#1a1d27',
        borderColor: 'rgba(255,255,255,0.07)',
        borderWidth: 1,
        titleColor: '#f1f5f9',
        bodyColor: '#94a3b8',
        callbacks: { label: ctx => ` ${ctx.dataset.label}: ${formatCurrency(ctx.raw)}` }
      }
    },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { family: 'Inter' } } },
      y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { family: 'Inter' }, callback: v => '$' + v.toLocaleString() } }
    }
  };
}

/* ============================================================
   BUDGETS PAGE
   ============================================================ */

let budgetEditId = null;

async function loadBudgets() {
  const resp = await apiRequest('dashboard/index.php', 'GET', null, { endpoint: 'budget-status' });
  const container = $('#budgets-container');
  if (!container) return;

  if (!resp.success || !resp.data.length) {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">🎯</div>
        <div class="empty-state-title">No budgets for this month</div>
        <div class="empty-state-text">Set monthly budgets to track your spending limits.</div>
      </div>`;
    return;
  }

  container.innerHTML = `<div class="budget-grid">${resp.data.map(b => renderBudgetCard(b)).join('')}</div>`;
}

function renderBudgetCard(b) {
  const pct = Math.min(b.percent, 100);
  const isOver = b.percent > 100;
  const isWarn = b.percent >= 80 && !isOver;
  const barColor = isOver ? '#ef4444' : isWarn ? '#f59e0b' : b.color;
  const cardClass = isOver ? 'over' : isWarn ? 'warning' : '';
  const monthName = MONTH_NAMES[(b.month || 1) - 1] || '';
  const yearVal = b.year || new Date().getFullYear();

  return `
    <div class="budget-card ${cardClass}">
      <div class="budget-header">
        <span class="budget-icon">${b.icon}</span>
        <div class="budget-meta">
          <div class="budget-name">${b.name}</div>
          <div class="text-xs text-muted" style="margin-bottom:2px">${monthName} ${yearVal}</div>
          <div class="budget-percent" style="color:${barColor}">${b.percent}% used ${isWarn ? '⚠️' : ''} ${isOver ? '🔴 OVER BUDGET' : ''}</div>
        </div>
        <div class="flex gap-1">
          <button class="btn btn-sm btn-secondary btn-icon" onclick="openEditBudgetModal(${b.id},${b.category_id},${b.budget},${b.month},${b.year})" title="Edit budget">✏️</button>
          <button class="btn btn-sm btn-danger btn-icon" onclick="deleteBudget(${b.id})" title="Delete budget">🗑️</button>
        </div>
      </div>
      <div class="progress-bar-wrap">
        <div class="progress-bar-fill" style="width:${pct}%;background:${barColor}"></div>
      </div>
      <div class="budget-amounts">
        <span>Spent: <span class="spent">${formatCurrency(b.spent)}</span></span>
        <span>Budget: ${formatCurrency(b.budget)}</span>
      </div>
      ${b.remaining > 0 ? `<div class="text-xs text-muted mt-1">${formatCurrency(b.remaining)} remaining</div>` : ''}
    </div>`;
}

function openAddBudgetModal() {
  budgetEditId = null;
  $('#modal-budget-title').textContent = 'Set Monthly Budget';
  $('#budget-save-btn').textContent = '💾 Save Budget';
  $('#budget-form').reset();
  // Reset to current month/year
  const now = new Date();
  $('#budget-month').value = now.getMonth() + 1;
  $('#budget-year').value = now.getFullYear();
  renderCategoryOptions($('#budget-category'), 'Expense');
  openModal('modal-budget');
}

function openEditBudgetModal(id, categoryId, amount, month, year) {
  budgetEditId = id;
  $('#modal-budget-title').textContent = 'Edit Budget';
  $('#budget-save-btn').textContent = '💾 Update Budget';
  renderCategoryOptions($('#budget-category'), 'Expense');
  $('#budget-category').value = categoryId;
  $('#budget-amount').value = amount;
  $('#budget-month').value = month;
  $('#budget-year').value = year;
  openModal('modal-budget');
}

async function handleSaveBudget(e) {
  e.preventDefault();
  const btn = $('#budget-save-btn');
  const catId = parseInt($('#budget-category').value);
  const amount = Math.round(parseFloat($('#budget-amount').value) * 100) / 100;
  const month = parseInt($('#budget-month').value);
  const year = parseInt($('#budget-year').value);

  if (!catId || !amount || !month || !year) {
    showToast('All fields are required', 'warning');
    return;
  }
  if (amount <= 0) {
    showToast('Budget amount must be greater than zero', 'warning');
    return;
  }

  const isEdit = !!budgetEditId;
  setButtonLoading(btn, true, 'Saving...');
  try {
    let resp;
    if (isEdit) {
      // PUT to update existing budget
      resp = await apiRequest(`budgets/index.php?id=${budgetEditId}`, 'PUT', { category_id: catId, amount, month, year });
    } else {
      resp = await apiRequest('budgets/index.php', 'POST', { category_id: catId, amount, month, year });
    }
    if (resp.success) {
      showToast(isEdit ? 'Budget updated! ✅' : 'Budget saved! 🎯', 'success');
      closeModal('modal-budget');
      loadBudgets();
    } else {
      showToast(resp.message || 'Failed to save budget', 'error');
    }
  } catch {
    showToast('Network error', 'error');
  }
  setButtonLoading(btn, false, isEdit ? '💾 Update Budget' : '💾 Save Budget');
}

async function deleteBudget(id) {
  if (!confirm('Delete this budget?')) return;
  try {
    const resp = await apiRequest(`budgets/index.php?id=${id}`, 'DELETE');
    if (resp.success) {
      showToast('Budget deleted', 'success');
      loadBudgets();
    } else {
      showToast(resp.message || 'Delete failed', 'error');
    }
  } catch {
    showToast('Network error', 'error');
  }
}

/* ============================================================
   CSV EXPORT
   ============================================================ */

async function exportCSV() {
  try {
    const EXPORT_LIMIT = 5000;
    const resp = await apiRequest('transactions/index.php', 'GET', null, {
      ...txnFilters,
      limit: EXPORT_LIMIT,
      sort: 'date',
      order: 'desc'
    });
    if (!resp.success || !resp.data.transactions.length) {
      showToast('No transactions to export', 'warning');
      return;
    }

    const txns = resp.data.transactions;
    const total = resp.data.pagination?.total || txns.length;
    if (total > EXPORT_LIMIT) {
      showToast(`⚠️ Only first ${EXPORT_LIMIT.toLocaleString()} of ${total.toLocaleString()} transactions exported. Use date filters to export specific ranges.`, 'warning', 6000);
    }

    const headers = ['Date', 'Category', 'Type', 'Amount', 'Note'];
    const rows = txns.map(t => [
      t.date,
      `"${t.category_name}"`,
      t.type,
      t.type === 'Income' ? t.amount : -t.amount,
      `"${(t.note || '').replace(/"/g, '""')}"`
    ]);

    const csv = [headers, ...rows].map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `expense_tracker_${today()}.csv`;
    link.click();
    showToast(`CSV exported! ${txns.length.toLocaleString()} rows 📥`, 'success');
  } catch {
    showToast('Export failed', 'error');
  }
}

/* ============================================================
   MODAL HELPERS
   ============================================================ */

function openModal(id) {
  const overlay = $(`#${id}`);
  if (overlay) { overlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeModal(id) {
  const overlay = $(`#${id}`);
  if (overlay) { overlay.classList.remove('open'); document.body.style.overflow = ''; }
}

/* ============================================================
   MOBILE SIDEBAR
   ============================================================ */

function openMobileSidebar() {
  $('#sidebar').classList.add('open');
  $('#sidebar-overlay').classList.add('open');
}

function closeMobileSidebar() {
  $('#sidebar').classList.remove('open');
  $('#sidebar-overlay').classList.remove('open');
}

/* ============================================================
   EVENT LISTENERS
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // ⚡ Apply saved theme immediately — prevents flash of wrong theme
  loadTheme();

  // Nav links
  $$('.nav-link[data-page]').forEach(link => {
    link.addEventListener('click', () => navigate(link.dataset.page));
  });

  // Auth forms
  $('#login-form')?.addEventListener('submit', handleLogin);
  $('#register-form')?.addEventListener('submit', handleRegister);
  $('#show-register-link')?.addEventListener('click', e => { e.preventDefault(); showRegisterForm(); });
  $('#show-login-link')?.addEventListener('click', e => { e.preventDefault(); showLoginForm(); });

  // Logout
  $$('.logout-btn').forEach(btn => btn.addEventListener('click', handleLogout));

  // Theme toggle (sidebar + mobile topbar)
  $('#theme-toggle-btn')?.addEventListener('click', toggleTheme);
  $('#theme-toggle-topbar')?.addEventListener('click', toggleTheme);

  // Add transaction
  $('#add-txn-btn')?.addEventListener('click', openAddModal);
  $('#add-txn-btn-2')?.addEventListener('click', openAddModal);
  $('#txn-form')?.addEventListener('submit', handleSaveTransaction);
  $('#txn-type-income')?.addEventListener('click', () => setTransactionType('Income'));
  $('#txn-type-expense')?.addEventListener('click', () => setTransactionType('Expense'));

  // Transaction filters
  $('#txn-filter-category')?.addEventListener('change', e => { txnFilters.category_id = e.target.value; txnPagination.page = 1; fetchTransactions(); });
  $('#txn-filter-type')?.addEventListener('change', e => { txnFilters.type = e.target.value; txnPagination.page = 1; fetchTransactions(); });
  $('#txn-filter-from')?.addEventListener('change', e => { txnFilters.date_from = e.target.value; txnPagination.page = 1; fetchTransactions(); });
  $('#txn-filter-to')?.addEventListener('change', e => { txnFilters.date_to = e.target.value; txnPagination.page = 1; fetchTransactions(); });
  $('#txn-sort')?.addEventListener('change', e => { txnFilters.sort = e.target.value; fetchTransactions(); });
  $('#txn-search')?.addEventListener('input', e => { txnFilters.search = e.target.value.trim(); txnPagination.page = 1; fetchTransactions(); });
  $('#txn-export-btn')?.addEventListener('click', exportCSV);

  // Budget
  $('#add-budget-btn')?.addEventListener('click', openAddBudgetModal);
  $('#budget-form')?.addEventListener('submit', handleSaveBudget);

  // Modal closes
  $$('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.closest('.modal-overlay').id));
  });
  $$('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeModal(overlay.id);
    });
  });

  // Mobile sidebar
  $('#hamburger-btn')?.addEventListener('click', openMobileSidebar);
  $('#sidebar-overlay')?.addEventListener('click', closeMobileSidebar);

  // Set default budget month/year
  const now = new Date();
  const bMonth = $('#budget-month');
  const bYear = $('#budget-year');
  if (bMonth) bMonth.value = now.getMonth() + 1;
  if (bYear) bYear.value = now.getFullYear();

  // Initialize app
  initApp();
});
