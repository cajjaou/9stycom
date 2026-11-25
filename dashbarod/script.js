/* ============ PAGINATION HELPER ============ */
function renderPagination(pg) {
  if (!pg || pg.total_pages <= 1) return '';

  const { page, total_pages } = pg;
  const start = Math.max(1, page - 2);
  const end = Math.min(total_pages, page + 2);

  let html = '<div class="pagination">';

  // Prev (RTL → يمين)
  html += `<button class="pg-btn" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>
                 <i data-lucide="chevron-right"></i>
               </button>`;

  if (start > 1) {
    html += `<button class="pg-btn" data-page="1">1</button>`;
    if (start > 2) html += `<span class="pg-dots">…</span>`;
  }

  for (let i = start; i <= end; i++) {
    html += `<button class="pg-btn ${i === page ? 'active' : ''}" data-page="${i}">${i}</button>`;
  }

  if (end < total_pages) {
    if (end < total_pages - 1) html += `<span class="pg-dots">…</span>`;
    html += `<button class="pg-btn" data-page="${total_pages}">${total_pages}</button>`;
  }

  // Next (RTL → يسار)
  html += `<button class="pg-btn" data-page="${page + 1}" ${page >= total_pages ? 'disabled' : ''}>
                 <i data-lucide="chevron-left"></i>
               </button>`;

  html += '</div>';
  return html;
}

function bindPagination(container, handler) {
  container.querySelectorAll('.pg-btn[data-page]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.disabled) return;
      handler(parseInt(btn.dataset.page, 10));
    });
  });
}
// ---------- Utilities ----------
function escapeHTML(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m]);
}
function escapeJS(str) {
  if (!str) return '';
  return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\r/g, '\\r').replace(/\n/g, '\\n');
}
function refreshIcons() {
  if (window.lucide) lucide.createIcons({ attrs: { 'stroke-width': 2 } });
}

function showToast(message, type = 'success') {
  var box = document.getElementById('toast-box');
  var icon = type === 'success' ? 'check-circle-2' : type === 'error' ? 'alert-circle' : 'info';
  var toast = document.createElement('div');
  toast.className = 'toast ' + type;
  toast.innerHTML = `
        <span class="toast-icon"><i data-lucide="${icon}"></i></span>
        <span class="toast-text">${escapeHTML(message)}</span>
      `;
  box.appendChild(toast);
  refreshIcons();
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(-20px)';
    toast.style.transition = 'all .3s';
    setTimeout(() => toast.remove(), 300);
  }, 3800);
}

function openModal(name) {
  var m = document.getElementById('modal-' + name);
  if (m) { m.classList.add('show'); refreshIcons(); }
}
function closeModal(name) {
  var m = document.getElementById('modal-' + name);
  if (m) m.classList.remove('show');
}
document.querySelectorAll('.modal-backdrop').forEach(bd => {
  bd.addEventListener('click', e => {
    if (e.target === bd) bd.classList.remove('show');
  });
});

// ---------- Theme ----------
function applyTheme() {
  var dark = localStorage.getItem('admin-dark') === 'true';
  document.documentElement.classList.toggle('dark-mode', dark);
  var btn = document.getElementById('theme-toggle-btn');
  if (btn) {
    btn.innerHTML = dark
      ? '<i data-lucide="sun"></i><span>الوضع الفاتح</span>'
      : '<i data-lucide="moon"></i><span>الوضع الداكن</span>';
    refreshIcons();
  }
  if (typeof refreshAnalyticsChartsTheme === 'function') {
    refreshAnalyticsChartsTheme();
  }
}
function toggleTheme() {
  var dark = document.documentElement.classList.toggle('dark-mode');
  localStorage.setItem('admin-dark', dark);
  applyTheme();
}

// ---------- Mobile sidebar ----------
function openMobileMenu() {
  document.getElementById('sidebar').classList.add('mobile-open');
  document.getElementById('mobile-overlay').classList.add('show');
}
function closeMobileMenu() {
  document.getElementById('sidebar').classList.remove('mobile-open');
  document.getElementById('mobile-overlay').classList.remove('show');
}

// ---------- Data cache ----------
var categoriesList = [];
function fetchCategoriesData() {
  fetch('api.php?action=list_categories')
    .then(r => r.json())
    .then(d => { if (d.success) categoriesList = d.categories; })
    .catch(() => { });
}

// ---------- Router ----------
var SECTION_META = {
  overview: { title: 'لوحة القيادة', subtitle: 'نظرة سريعة على أداء الموقع' },
  analytics: { title: 'التحليلات المتقدمة', subtitle: 'إحصائيات تفصيلية ومؤشرات الأداء' },
  posts: { title: 'إدارة الأخبار والمقالات', subtitle: 'إضافة وتعديل وحذف المقالات' },
  shortposts: { title: 'المنشورات القصيرة (Short Posts)', subtitle: 'إدارة المنشورات السريعة، الصور، والروابط برمز كود' },
  categories: { title: 'الأقسام', subtitle: 'تنظيم تصنيفات الموقع' },
  adsense: { title: 'إعلانات أدسنس', subtitle: 'إدارة مساحات Google AdSense' },
  banners: { title: 'البنرات المخصصة', subtitle: 'إدارة الإعلانات المخصصة' },
  comments: { title: 'التعليقات', subtitle: 'مراجعة تعليقات القراء' },
  users: { title: 'المستخدمون', subtitle: 'صلاحيات الوصول للوحة' },
  shortlinks: { title: 'الروابط المختصرة', subtitle: 'إدارة الروابط المخصصة' },
  shares: { title: 'تتبع المشاركات (SI)', subtitle: 'عدد المشاركات والزيارات القادمة عبر كل رمز مشاركة' },
  settings: { title: 'إعدادات التتبع', subtitle: 'Analytics, Pixel & SEO' },
  trash: { title: 'سلة المهملات', subtitle: 'العناصر المحذوفة — استعادتها أو حذفها نهائياً' }

};

function loadSection(name) {
  closeMobileMenu();
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  var menu = document.getElementById('menu-' + name);
  if (menu) menu.classList.add('active');

  var meta = SECTION_META[name] || { title: 'لوحة التحكم', subtitle: '' };
  document.getElementById('page-title').textContent = meta.title;
  document.getElementById('page-subtitle').textContent = meta.subtitle;

  var area = document.getElementById('main-content');
  area.innerHTML = `
        <div class="card">
          <div class="loader-shimmer" style="width:40%; margin-bottom:16px;"></div>
          <div class="loader-shimmer" style="margin-bottom:10px;"></div>
          <div class="loader-shimmer" style="margin-bottom:10px;"></div>
          <div class="loader-shimmer" style="width:80%;"></div>
        </div>`;

  switch (name) {
    case 'overview': renderOverview(area); break;
    case 'analytics': renderAnalytics(area); break;
    case 'posts': renderPosts(area); break;
    case 'shortposts': renderShortPosts(area); break;
    case 'categories': renderCategories(area); break;
    case 'adsense': renderAdsense(area); break;
    case 'banners': renderBanners(area); break;
    case 'comments': renderComments(area); break;
    case 'users': renderUsers(area); break;
    case 'shortlinks': renderShortLinks(area); break;
    case 'shares': renderShares(area); break;
    case 'settings': renderSettings(area); break;
    case 'trash': renderTrash(area); break;
  }
}

// ---------- Share Referrals (SI) ----------
function renderShares(container, page = 1) {
  container.innerHTML = `<div class="card"><div class="loader-shimmer" style="height:80px;"></div></div>`;
  fetch(`api.php?action=list_shares&page=${page}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        container.innerHTML = `<div class="error-msg">${escapeHTML(data.message)}</div>`;
        return;
      }
      var stats = data.stats || { total_shares: 0, total_visits: 0, active_sharers: 0, top_platform: '—' };
      var rows = '';
      (data.shares || []).forEach(s => {
        var shareUrl = `https://9sty.com/s/${encodeURIComponent(s.short_code)}?si=${encodeURIComponent(s.si_code)}`;
        rows += `
          <tr>
            <td style="font-weight:600;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHTML(s.post_title || s.short_code)}">${escapeHTML(s.post_title || ('/s/' + s.short_code))}</td>
            <td><code style="background:var(--brand-50);color:var(--brand-600);padding:2px 8px;border-radius:6px;font-weight:700;">${escapeHTML(s.si_code)}</code></td>
            <td><span class="badge active">${escapeHTML(s.platform || 'share')}</span></td>
            <td style="font-weight:800;color:#2563eb;text-align:center;">${Number(s.visits || 0).toLocaleString('en')}</td>
            <td style="direction:ltr;text-align:right;font-size:12px;"><a href="${shareUrl}" target="_blank" style="color:var(--brand-600);">/s/${escapeHTML(s.short_code)}?si=${escapeHTML(s.si_code)}</a></td>
            <td style="font-size:12px;color:var(--text-muted);">${escapeHTML((s.created_at || '').substring(0, 16))}</td>
            <td style="font-size:12px;color:var(--text-muted);">${escapeHTML((s.last_visit_at || '—').substring(0, 16))}</td>
          </tr>`;
      });

      var pag = data.pagination || { page: 1, total_pages: 1 };
      var pagHtml = '';
      if (pag.total_pages > 1) {
        pagHtml = `<div style="display:flex;gap:8px;justify-content:center;margin-top:16px;">`;
        for (var p = 1; p <= pag.total_pages; p++) {
          pagHtml += `<button class="btn ${p === pag.page ? '' : 'btn-secondary'}" style="padding:6px 12px;" onclick="renderShares(document.getElementById('main-content'), ${p})">${p}</button>`;
        }
        pagHtml += `</div>`;
      }

      container.innerHTML = `
        <div class="stats-grid" style="margin-bottom:20px;">
          <div class="stat-card">
            <div class="stat-icon" style="background:#ede9fe;color:#7c3aed;"><i data-lucide="share-2"></i></div>
            <div class="stat-info"><span class="stat-label">إجمالي المشاركات</span><span class="stat-value">${(stats.total_shares || 0).toLocaleString('en')}</span></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#2563eb;"><i data-lucide="mouse-pointer-click"></i></div>
            <div class="stat-info"><span class="stat-label">الزيارات عبر المشاركة</span><span class="stat-value">${(stats.total_visits || 0).toLocaleString('en')}</span></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background:#ecfdf5;color:#059669;"><i data-lucide="users"></i></div>
            <div class="stat-info"><span class="stat-label">مشاركون جلبوا زواراً</span><span class="stat-value">${(stats.active_sharers || 0).toLocaleString('en')}</span></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i data-lucide="award"></i></div>
            <div class="stat-info"><span class="stat-label">أكثر منصة مشاركة</span><span class="stat-value" style="font-size:16px;">${escapeHTML(stats.top_platform || '—')}</span></div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <h2>سجل روابط المشاركة (SI)</h2>
          </div>
          <div class="table-responsive">
            <table>
              <thead><tr><th>المقال</th><th>كود SI</th><th>المنصة</th><th>الزوار</th><th>الرابط</th><th>تاريخ المشاركة</th><th>آخر زيارة</th></tr></thead>
              <tbody>${rows || `<tr><td colspan="7"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد مشاركات مسجلة بعد</p></div></td></tr>`}</tbody>
            </table>
          </div>
          ${pagHtml}
        </div>`;
      refreshIcons();
    })
    .catch(() => { container.innerHTML = `<div class="error-msg">تعذّر الاتصال</div>`; });
}

// ---------- Overview ----------
function renderOverview(container) {
  fetch('api.php?action=get_stats')
    .then(r => r.json())
    .then(data => {
      if (!data.success) { container.innerHTML = `<div class="error-msg">${escapeHTML(data.message)}</div>`; return; }
      var s = data.stats;
      container.innerHTML = `
            <div class="stats-grid">
              <div class="stat-card">
                <div class="stat-icon" style="background:#fffbeb; color:#d97706;"><i data-lucide="newspaper"></i></div>
                <div class="stat-info">
                  <span class="stat-label">إجمالي الأخبار</span>
                  <span class="stat-value">${s.posts.toLocaleString('en')}</span>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon" style="background:#eef2ff; color:#4f46e5;"><i data-lucide="eye"></i></div>
                <div class="stat-info">
                  <span class="stat-label">المشاهدات</span>
                  <span class="stat-value">${s.views.toLocaleString('en')}</span>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon" style="background:#ecfdf5; color:#059669;"><i data-lucide="folder-tree"></i></div>
                <div class="stat-info">
                  <span class="stat-label">الأقسام</span>
                  <span class="stat-value">${s.categories.toLocaleString('en')}</span>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon" style="background:#fff1f2; color:#e11d48;"><i data-lucide="message-circle"></i></div>
                <div class="stat-info">
                  <span class="stat-label">التعليقات</span>
                  <span class="stat-value">${s.comments.toLocaleString('en')}</span>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <h2>مرحباً بك 👋</h2>
              </div>
              <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px; line-height:1.7;">
                يمكنك من هنا إدارة كل جوانب الموقع: المقالات، الأقسام، الإعلانات، البنرات، التعليقات والمستخدمين.
              </p>
              <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <button class="btn" onclick="loadSection('analytics')">
                  <i data-lucide="line-chart"></i> التحليلات المتقدمة
                </button>
                <button class="btn btn-secondary" onclick="loadSection('posts')">
                  <i data-lucide="newspaper"></i> إدارة الأخبار
                </button>
                <button class="btn btn-secondary" onclick="loadSection('banners')">
                  <i data-lucide="image"></i> البنرات الإعلانية
                </button>
                <button class="btn btn-secondary" onclick="loadSection('settings')">
                  <i data-lucide="settings"></i> إعدادات التتبع
                </button>
              </div>
            </div>
          `;
      refreshIcons();
    })
    .catch(() => { container.innerHTML = `<div class="error-msg">تعذّر الاتصال بالخادم</div>`; });
}

// =========================================================
// ================= ANALYTICS DASHBOARD ===================
// =========================================================

var analyticsState = {
  range: 'last_7_days',
  customStart: '',
  customEnd: '',
  customOpen: false,
  tableTab: 'posts', // 'posts' | 'ads' | 'visits' | 'categories'
  postsPage: 1,
  postsSearch: '',
  postsSort: 'views',
  postsOrder: 'DESC',
  filterTableByRange: false,
  adsPage: 1,
  adsSearch: '',
  adsSourceFilter: '',
  visitsPage: 1,
  visitsSearch: '',
  googleCrawlerPage: 1,
  rawTrends: null,
  rawDist: null,
  rawComp: null,
  loading: false,
  loadId: 0,
  loadController: null
};

var analyticsChartInstances = {};

function destroyAnalyticsCharts() {
  Object.keys(analyticsChartInstances).forEach(k => {
    if (analyticsChartInstances[k]) {
      try { analyticsChartInstances[k].destroy(); } catch (e) { }
      delete analyticsChartInstances[k];
    }
  });
}

function getChartThemeColors() {
  var isDark = document.documentElement.classList.contains('dark-mode');
  return {
    textColor: isDark ? '#94a3b8' : '#64748b',
    gridColor: isDark ? 'rgba(255, 255, 255, 0.07)' : 'rgba(15, 23, 42, 0.06)',
    surfaceColor: isDark ? '#1e293b' : '#ffffff',
    brand: '#f59e0b',
    brandLight: 'rgba(245, 158, 11, 0.15)',
    indigo: '#6366f1',
    indigoLight: 'rgba(99, 102, 241, 0.15)',
    emerald: '#10b981',
    emeraldLight: 'rgba(16, 185, 129, 0.15)',
    blue: '#3b82f6',
    blueLight: 'rgba(59, 130, 246, 0.15)',
    rose: '#f43f5e',
    muted: isDark ? '#475569' : '#cbd5e1'
  };
}

function renderAnalytics(container) {
  installAnalyticsErrorDiagnostics();
  destroyAnalyticsCharts();
  container.innerHTML = `
        <div id="analytics-toolbar-root"></div>
        <div id="analytics-loading-banner" class="analytics-loading-banner" role="status" aria-live="polite" hidden>
          <span class="analytics-loading-spinner" aria-hidden="true"></span>
          <span>جاري تحميل التحليلات حسب الفترة المختارة…</span>
        </div>
        <div id="analytics-kpis-root">
          <div class="kpi-grid">
            <div class="card"><div class="loader-shimmer" style="height:80px;"></div></div>
            <div class="card"><div class="loader-shimmer" style="height:80px;"></div></div>
            <div class="card"><div class="loader-shimmer" style="height:80px;"></div></div>
            <div class="card"><div class="loader-shimmer" style="height:80px;"></div></div>
            <div class="card"><div class="loader-shimmer" style="height:80px;"></div></div>
            <div class="card"><div class="loader-shimmer" style="height:80px;"></div></div>
          </div>
        </div>
        <div id="analytics-charts-root"></div>
        <div id="analytics-tables-root"></div>
      `;
  loadAnalyticsData();
}

function installAnalyticsErrorDiagnostics() {
  if (window.__9styAnalyticsDiagnosticsInstalled) return;
  window.__9styAnalyticsDiagnosticsInstalled = true;
  window.addEventListener('error', function (event) {
    if (document.getElementById('analytics-toolbar-root')) {
      console.error('[9sty Analytics] Uncaught browser error:', {
        message: event.message,
        file: event.filename,
        line: event.lineno,
        column: event.colno,
        stack: event.error && event.error.stack
      });
    }
  });
  window.addEventListener('unhandledrejection', function (event) {
    if (document.getElementById('analytics-toolbar-root')) {
      var reason = event.reason;
      console.error('[9sty Analytics] Unhandled promise rejection:', reason && reason.stack ? reason.stack : reason);
    }
  });
}

function buildAnalyticsQuery(action) {
  var q = `api.php?action=${action}&range=${encodeURIComponent(analyticsState.range)}`;
  if (analyticsState.range === 'custom') {
    if (analyticsState.customStart) q += `&start_date=${encodeURIComponent(analyticsState.customStart)}`;
    if (analyticsState.customEnd) q += `&end_date=${encodeURIComponent(analyticsState.customEnd)}`;
  }
  return q;
}

function setAnalyticsLoading(isLoading) {
  var banner = document.getElementById('analytics-loading-banner');
  if (banner) banner.hidden = !isLoading;
  var toolbar = document.getElementById('analytics-toolbar-root');
  if (toolbar) toolbar.setAttribute('aria-busy', isLoading ? 'true' : 'false');
}

// Parse analytics responses with enough context to identify PHP, HTTP, and JSON failures.
function fetchAnalyticsJson(action, signal) {
  var url = buildAnalyticsQuery(action);
  return fetch(url, { cache: 'no-store', signal: signal }).then(function (response) {
    return response.text().then(function (body) {
      var data;
      try {
        data = JSON.parse(body);
      } catch (parseError) {
        var preview = (body || '').replace(/\s+/g, ' ').slice(0, 500);
        var error = new Error('Invalid JSON from ' + action + ' (HTTP ' + response.status + '): ' + (preview || '[empty response]'));
        error.action = action;
        error.status = response.status;
        error.responseBody = body;
        error.cause = parseError;
        throw error;
      }
      if (!response.ok) {
        var httpError = new Error((data && data.message) || ('HTTP ' + response.status + ' from ' + action));
        httpError.action = action;
        httpError.status = response.status;
        httpError.response = data;
        throw httpError;
      }
      if (!data || typeof data !== 'object') {
        var shapeError = new Error('Unexpected response shape from ' + action);
        shapeError.action = action;
        shapeError.response = data;
        throw shapeError;
      }
      return data;
    });
  }).catch(function (error) {
    if (!error.action) error.action = action;
    throw error;
  });
}

// ═══════════════════════════════════════════════════════════════
// 🐛 DEBUG — حيد هاد المتغير بعد ما تصلح كلشي (ولا خليه false)
// In-memory cache for ultra-fast instant switching between date ranges
var analyticsDataCache = {};

function normalizeKpis(kpis) {
  kpis = kpis || {};
  var defaults = {
    posts: { all_time: 0, published: 0, draft: 0, archived: 0, current: 0, previous: 0, change: { pct: 0, trend: 'neutral', label: '0%' } },
    views: { all_time: 0, avg: 0, max: 0, period_visits: 0, prev_visits: 0, change: { pct: 0, trend: 'neutral', label: '0%' }, has_tracking: false, total_visits: 0 },
    ads_clicks: { all_time: 0, current: 0, previous: 0, change: { pct: 0, trend: 'neutral', label: '0%' }, top_source: '—' },
    comments: { all_time: 0, current: 0, previous: 0, change: { pct: 0, trend: 'neutral', label: '0%' }, approved: 0, hidden: 0, replies: 0 },
    short_links: { all_time: 0, current: 0, previous: 0, change: { pct: 0, trend: 'neutral', label: '0%' }, total_clicks: 0 },
    geo: { top_country: 'المغرب', countries_cnt: 0 },
    users: { all_time: 0, admins: 0, editors: 0, current: 0, previous: 0, change: { pct: 0, trend: 'neutral', label: '0%' } },
    categories: { total: 0, active: 0 },
    monetization: { banners: 0, banners_total: 0, adsense: 0 }
  };

  Object.keys(defaults).forEach(function (k) {
    if (!kpis[k] || typeof kpis[k] !== 'object') {
      kpis[k] = Object.assign({}, defaults[k]);
    }
    if (!kpis[k].change || typeof kpis[k].change !== 'object') {
      kpis[k].change = { pct: 0, trend: 'neutral', label: '0%' };
    }
  });

  return kpis;
}

function loadAnalyticsData(forceRefresh) {
  if (analyticsState.loadController) analyticsState.loadController.abort();
  analyticsState.loadController = new AbortController();
  var requestId = ++analyticsState.loadId;
  var requestSignal = analyticsState.loadController.signal;

  var cacheKey = analyticsState.range + '_' + (analyticsState.customStart || '') + '_' + (analyticsState.customEnd || '');
  var cached = analyticsDataCache[cacheKey];

  // If cached data exists and not forcing refresh, render instantly with 0ms delay
  if (cached && !forceRefresh) {
    renderAnalyticsToolbar(cached.overview.period);
    renderAnalyticsKPIs(cached.overview.kpis, cached.overview.sparklines, cached.overview.period);
    renderAnalyticsCharts(cached.trends, cached.dist, cached.comp);
    renderAnalyticsTables();
    return;
  }

  analyticsState.loading = true;
  setAnalyticsLoading(true);
  var tbRoot = document.getElementById('analytics-toolbar-root');

  // Progressive parallel loading: Fetch overview first and render KPIs immediately
  Promise.all([
    fetchAnalyticsJson('analytics_overview', requestSignal),
    fetchAnalyticsJson('analytics_trends', requestSignal),
    fetchAnalyticsJson('analytics_distribution', requestSignal),
    fetchAnalyticsJson('analytics_comparison', requestSignal)
  ])
    .then(([overviewData, trendsData, distData, compData]) => {
      if (requestId !== analyticsState.loadId) return;
      analyticsState.loading = false;
      setAnalyticsLoading(false);

      if (!overviewData || !overviewData.success) {
        if (tbRoot) tbRoot.innerHTML = `<div class="error-msg">${escapeHTML((overviewData && overviewData.message) || 'حدث خطأ أثناء تحميل التحليلات')}</div>`;
        return;
      }

      // Normalize KPIs so no undefined property error can ever happen
      overviewData.kpis = normalizeKpis(overviewData.kpis);

      analyticsState.rawTrends = (trendsData && trendsData.success) ? trendsData : null;
      analyticsState.rawDist = (distData && distData.success) ? distData : null;
      analyticsState.rawComp = (compData && compData.success) ? compData : null;

      if (analyticsState.rawComp && analyticsState.rawComp.comparison && overviewData.kpis.ads_clicks) {
        analyticsState.rawComp.comparison.ad_clicks = {
          current: Number(overviewData.kpis.ads_clicks.current || 0),
          previous: Number(overviewData.kpis.ads_clicks.previous || 0),
          diff: Number(overviewData.kpis.ads_clicks.current || 0) - Number(overviewData.kpis.ads_clicks.previous || 0),
          change: overviewData.kpis.ads_clicks.change
        };
      }

      // Cache the resolved data for instant response on future clicks
      analyticsDataCache[cacheKey] = {
        overview: overviewData,
        trends: analyticsState.rawTrends,
        dist: analyticsState.rawDist,
        comp: analyticsState.rawComp
      };

      renderAnalyticsToolbar(overviewData.period);
      renderAnalyticsKPIs(overviewData.kpis, overviewData.sparklines, overviewData.period);
      renderAnalyticsCharts(analyticsState.rawTrends, analyticsState.rawDist, analyticsState.rawComp);
      renderAnalyticsTables();

      // Load active tab data cleanly
      if (analyticsState.tableTab === 'ads') {
        loadAnalyticsAdsTable(analyticsState.adsPage);
      } else if (analyticsState.tableTab === 'visits') {
        loadAnalyticsVisitsTable(analyticsState.visitsPage);
      } else if (analyticsState.tableTab === 'categories') {
        loadAnalyticsCategoriesTable();
      } else {
        loadAnalyticsTopPosts(analyticsState.postsPage);
      }
    })
    .catch(err => {
      if (requestId !== analyticsState.loadId || err.name === 'AbortError') return;
      analyticsState.loading = false;
      setAnalyticsLoading(false);
      console.error('[9sty Analytics] Error loading data:', err);
      var detail = err.message || 'حدث خطأ في الاتصال بالخادم';
      if (tbRoot) tbRoot.innerHTML = `<div class="error-msg">تعذّر تحميل بيانات التحليلات: ${escapeHTML(detail)}</div>`;
    });
}

// ---------- Toolbar & Date Filter ----------
function renderAnalyticsToolbar(period) {
  var root = document.getElementById('analytics-toolbar-root');
  if (!root) return;

  var ranges = [
    { id: 'today', label: 'اليوم' },
    { id: 'yesterday', label: 'أمس' },
    { id: 'this_week', label: 'هذا الأسبوع' },
    { id: 'last_week', label: 'الأسبوع الماضي' },
    { id: 'this_month', label: 'هذا الشهر' },
    { id: 'last_month', label: 'الشهر الماضي' },
    { id: 'last_7_days', label: 'آخر 7 أيام' },
    { id: 'last_30_days', label: 'آخر 30 يوماً' },
    { id: 'custom', label: 'نطاق مخصص', icon: 'calendar-range' }
  ];

  var pillsHtml = ranges.map(r => {
    var active = (analyticsState.range === r.id) ? 'active' : '';
    var iconHtml = r.icon ? `<i data-lucide="${r.icon}"></i>` : '';
    return `<button class="filter-pill ${active}" onclick="setAnalyticsFilter('${r.id}')">${iconHtml}${r.label}</button>`;
  }).join('');

  var customBoxStyle = analyticsState.customOpen ? 'display:flex;' : 'display:none;';
  var customStartVal = analyticsState.customStart || (period ? period.curr_start : '');
  var customEndVal = analyticsState.customEnd || (period ? period.curr_end : '');

  root.innerHTML = `
        <div class="analytics-toolbar">
          <div class="analytics-toolbar-top">
            <div class="filter-pills">${pillsHtml}</div>
            <div class="analytics-period-badge" title="النطاق المعتمد">
              <i data-lucide="calendar"></i>
              <span>${escapeHTML(period ? period.label : '')} (${period ? period.curr_start : ''} إلى ${period ? period.curr_end : ''})</span>
            </div>
          </div>

          <div class="custom-range-card" id="custom-range-card" style="${customBoxStyle}">
            <div class="custom-range-inputs">
              <div class="form-group">
                <label for="ana-start-date">من تاريخ:</label>
                <input type="date" id="ana-start-date" value="${customStartVal}">
              </div>
              <div class="form-group">
                <label for="ana-end-date">إلى تاريخ:</label>
                <input type="date" id="ana-end-date" value="${customEndVal}">
              </div>
            </div>
            <div class="custom-range-actions">
              <button class="btn" onclick="applyCustomDateFilter()"><i data-lucide="check"></i> تطبيق النطاق</button>
              <button class="btn btn-secondary" onclick="resetCustomDateFilter()"><i data-lucide="rotate-ccw"></i> إلغاء</button>
            </div>
          </div>
        </div>
      `;
  refreshIcons();
}

function setAnalyticsFilter(range) {
  if (range === 'custom') {
    analyticsState.customOpen = !analyticsState.customOpen;
    var box = document.getElementById('custom-range-card');
    if (box) box.style.display = analyticsState.customOpen ? 'flex' : 'none';
    if (!analyticsState.customOpen && analyticsState.range === 'custom') {
      analyticsState.range = 'last_7_days';
      loadAnalyticsData();
    }
    return;
  }
  analyticsState.customOpen = false;
  analyticsState.range = range;
  loadAnalyticsData();
}

function applyCustomDateFilter() {
  var s = document.getElementById('ana-start-date').value;
  var e = document.getElementById('ana-end-date').value;

  if (!s || !e) {
    showToast('يرجى تحديد تاريخ البداية والنهاية معاً', 'error');
    return;
  }
  if (s > e) {
    showToast('تاريخ البداية يجب أن يكون قبل أو يساوي تاريخ النهاية', 'error');
    return;
  }

  analyticsState.customStart = s;
  analyticsState.customEnd = e;
  analyticsState.range = 'custom';
  analyticsState.customOpen = true;
  loadAnalyticsData();
}

function resetCustomDateFilter() {
  analyticsState.customOpen = false;
  analyticsState.customStart = '';
  analyticsState.customEnd = '';
  analyticsState.range = 'last_7_days';
  loadAnalyticsData();
}

// ---------- KPI Cards & Sparklines ----------
function renderAnalyticsKPIs(kpis, sparklines, period) {
  var root = document.getElementById('analytics-kpis-root');
  if (!root || !kpis) return;

  function renderBadge(changeObj) {
    if (!changeObj) return '';
    var trend = changeObj.trend;
    var icon = trend === 'up' ? 'trending-up' : trend === 'down' ? 'trending-down' : 'minus';
    return `<span class="kpi-badge ${trend}"><i data-lucide="${icon}"></i> ${escapeHTML(changeObj.label)}</span>`;
  }

  var prevLabelText = period ? period.prev_label : 'الفترة السابقة';
  var allPostsCount = kpis.posts ? (kpis.posts.all_time || 0) : 0;
  var newPostsCount = kpis.posts ? (kpis.posts.current || 0) : 0;
  var visitsCount = kpis.views ? (kpis.views.period_visits || 0) : 0;
  var allViewsCount = kpis.views ? (kpis.views.all_time || 0) : 0;
  var adsClicks = kpis.ads_clicks ? (kpis.ads_clicks.current || 0) : 0;
  var adsAllClicks = kpis.ads_clicks ? (kpis.ads_clicks.all_time || 0) : 0;
  var topAdSource = kpis.ads_clicks ? (kpis.ads_clicks.top_source || '—') : '—';
  var topCountry = (kpis.geo && kpis.geo.top_country) ? kpis.geo.top_country : '—';
  var topCountryVis = (kpis.geo && kpis.geo.top_country_visits) ? kpis.geo.top_country_visits : 0;
  var countriesCnt = (kpis.geo && kpis.geo.countries_cnt) ? kpis.geo.countries_cnt : 0;

  root.innerHTML = `
        <div class="kpi-grid">
          <!-- KPI 1: All Articles (Old & New) -->
          <div class="kpi-card">
            <div class="kpi-top">
              <div class="kpi-title-wrap">
                <div class="kpi-icon" style="background:#d1ebf3; color:#1c3452;"><i data-lucide="newspaper"></i></div>
                <span class="kpi-label">المقالات (قديمة وجديدة)</span>
              </div>
              ${renderBadge(kpis.posts ? kpis.posts.change : null)}
            </div>
            <div class="kpi-body">
              <div class="kpi-main-val">${allPostsCount.toLocaleString('en')}</div>
              <canvas class="kpi-sparkline" id="sparkline-posts" width="80" height="32"></canvas>
            </div>
            <div class="kpi-footer">
              <span>الجديد بالفترة: <strong>${newPostsCount.toLocaleString('en')}</strong></span>
              <span>المنشور: <strong>${kpis.posts.published || 0}</strong></span>
            </div>
          </div>

          <!-- KPI 2: Live Visits (news_tracking) -->
          <div class="kpi-card">
            <div class="kpi-top">
              <div class="kpi-title-wrap">
                <div class="kpi-icon" style="background:#d1ebf3; color:#1c3452;"><i data-lucide="eye"></i></div>
                <span class="kpi-label">زيارات المقالات (تتبع حي)</span>
              </div>
              ${renderBadge(kpis.views ? kpis.views.change : null)}
            </div>
            <div class="kpi-body">
              <div class="kpi-main-val">${visitsCount.toLocaleString('en')}</div>
              <canvas class="kpi-sparkline" id="sparkline-visits" width="80" height="32"></canvas>
            </div>
            <div class="kpi-footer">
              <span>مقارنة بـ <strong>${escapeHTML(prevLabelText)}: ${(kpis.views.prev_visits || 0).toLocaleString('en')}</strong></span>
              <span>التراكمي: <strong>${allViewsCount.toLocaleString('en')}</strong></span>
            </div>
          </div>

          <!-- KPI 3: Promotional Clicks (jb_promo_clicks) -->
          <div class="kpi-card">
            <div class="kpi-top">
              <div class="kpi-title-wrap">
                <div class="kpi-icon" style="background:#d1ebf3; color:#1c3452;"><i data-lucide="mouse-pointer-click"></i></div>
                <span class="kpi-label">نقرات الإعلانات والبنرات</span>
              </div>
              ${renderBadge(kpis.ads_clicks ? kpis.ads_clicks.change : null)}
            </div>
            <div class="kpi-body">
              <div class="kpi-main-val">${adsClicks.toLocaleString('en')}</div>
              <canvas class="kpi-sparkline" id="sparkline-ads" width="80" height="32"></canvas>
            </div>
            <div class="kpi-footer">
              <span>المصدر الأبرز: <strong>${escapeHTML(topAdSource)}</strong></span>
              <span>إجمالي النقرات: <strong>${adsAllClicks.toLocaleString('en')}</strong></span>
            </div>
          </div>

          <!-- KPI 4: Comments -->
          <div class="kpi-card">
            <div class="kpi-top">
              <div class="kpi-title-wrap">
                <div class="kpi-icon" style="background:#d1ebf3; color:#1c3452;"><i data-lucide="messages-square"></i></div>
                <span class="kpi-label">التعليقات والمشاركات</span>
              </div>
              ${renderBadge(kpis.comments ? kpis.comments.change : null)}
            </div>
            <div class="kpi-body">
              <div class="kpi-main-val">${(kpis.comments.current || 0).toLocaleString('en')}</div>
              <canvas class="kpi-sparkline" id="sparkline-comments" width="80" height="32"></canvas>
            </div>
            <div class="kpi-footer">
              <span>معتمد: <strong>${kpis.comments.approved || 0}</strong> | ردود: <strong>${kpis.comments.replies || 0}</strong></span>
              <span>التراكمي: <strong>${(kpis.comments.all_time || 0).toLocaleString('en')}</strong></span>
            </div>
          </div>

          <!-- KPI 5: Shortlink clicks from news_tracking -->
          <div class="kpi-card">
            <div class="kpi-top">
              <div class="kpi-title-wrap">
                <div class="kpi-icon" style="background:#d1ebf3; color:#1c3452;"><i data-lucide="link-2"></i></div>
                <span class="kpi-label">نقرات الروابط المختصرة</span>
              </div>
              ${renderBadge(kpis.short_links ? kpis.short_links.change : null)}
            </div>
            <div class="kpi-body">
              <div class="kpi-main-val">${(kpis.short_links.current || 0).toLocaleString('en')}</div>
            </div>
            <div class="kpi-footer">
              <span>إجمالي الروابط: <strong>${kpis.short_links.all_time || 0}</strong></span>
              <span>إجمالي نقرات الروابط: <strong>${(kpis.short_links.total_clicks || 0).toLocaleString('en')}</strong></span>
            </div>
          </div>

          <!-- KPI 6: Global Audience & Countries -->
          <div class="kpi-card">
            <div class="kpi-top">
              <div class="kpi-title-wrap">
                <div class="kpi-icon" style="background:#d1ebf3; color:#1c3452;"><i data-lucide="globe"></i></div>
                <span class="kpi-label">الانتشار الجغرافي للزوار</span>
              </div>
              <span class="badge" style="background:var(--brand-50); color:var(--brand-600); border:1px solid var(--brand-100);">
                <i data-lucide="map-pin"></i> تتبع دولي
              </span>
            </div>
            <div class="kpi-body">
              <div class="kpi-main-val">${countriesCnt} <span style="font-size:14px; font-weight:600; color:var(--text-muted);">دولة</span></div>
              <div style="font-size:12px; color:var(--text-muted); text-align:left;">
                الأكثر زيارة: <strong>${escapeHTML(topCountry)}</strong>
              </div>
            </div>
            <div class="kpi-footer">
              <span>حجم الزيارات من ${escapeHTML(topCountry)}: <strong>${topCountryVis.toLocaleString('en')}</strong></span>
              <span>الأقسام: <strong>${kpis.categories.active || 0} نشط</strong></span>
            </div>
          </div>
        </div>
      `;
  refreshIcons();

  setTimeout(() => {
    if (sparklines) {
      if (sparklines.posts && sparklines.posts.length > 1) {
        drawSparkline(document.getElementById('sparkline-posts'), sparklines.posts, '#d97706', 'rgba(217, 119, 6, 0.25)');
      }
      if (sparklines.visits && sparklines.visits.length > 1) {
        drawSparkline(document.getElementById('sparkline-visits'), sparklines.visits, '#2563eb', 'rgba(37, 99, 235, 0.25)');
      }
      var promoClickSparkline = analyticsState.rawTrends && analyticsState.rawTrends.ad_clicks;
      if (promoClickSparkline && promoClickSparkline.length > 1) {
        drawSparkline(document.getElementById('sparkline-ads'), promoClickSparkline, '#059669', 'rgba(5, 150, 105, 0.25)');
      }
      if (sparklines.comments && sparklines.comments.length > 1) {
        drawSparkline(document.getElementById('sparkline-comments'), sparklines.comments, '#e11d48', 'rgba(225, 29, 72, 0.25)');
      }
    }
  }, 50);
}

function drawSparkline(canvas, data, strokeColor, fillColor) {
  if (!canvas || !data || data.length < 2) return;
  var ctx = canvas.getContext('2d');
  var w = canvas.width;
  var h = canvas.height;
  ctx.clearRect(0, 0, w, h);

  var min = Math.min(...data);
  var max = Math.max(...data);
  if (min === max) { max = min + 1; }

  var pad = 3;
  var step = (w - pad * 2) / (data.length - 1);

  ctx.beginPath();
  data.forEach((val, i) => {
    var x = pad + i * step;
    var y = h - pad - ((val - min) / (max - min)) * (h - pad * 2);
    if (i === 0) ctx.moveTo(x, y);
    else ctx.lineTo(x, y);
  });

  ctx.strokeStyle = strokeColor;
  ctx.lineWidth = 2;
  ctx.stroke();

  if (fillColor) {
    var grad = ctx.createLinearGradient(0, 0, 0, h);
    grad.addColorStop(0, fillColor);
    grad.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.lineTo(w - pad, h);
    ctx.lineTo(pad, h);
    ctx.closePath();
    ctx.fillStyle = grad;
    ctx.fill();
  }
}

// ---------- Interactive Charts ----------
function renderAnalyticsCharts(trends, dist, comp) {
  var root = document.getElementById('analytics-charts-root');
  if (!root) return;

  root.innerHTML = `
        <div class="charts-grid">
          <!-- Chart 1: Activity Timeline (Visits, Ad clicks, Posts, Comments) -->
          <div class="chart-card full-width">
            <div class="chart-card-header">
              <div class="chart-title-wrap">
                <h3><i data-lucide="trending-up"></i> تطور الزيارات ونقرات الإعلانات والمحتوى اليومي</h3>
                <div class="chart-subtitle">تتبع زيارات المقالات ونقرات العروض وتفاعل النشر</div>
              </div>
            </div>
            <div class="chart-canvas-container tall">
              <canvas id="chart-activity-trends"></canvas>
            </div>
          </div>

          <!-- Chart 2: Ads Sources Breakdown -->
          <div class="chart-card">
            <div class="chart-card-header">
              <div class="chart-title-wrap">
                <h3><i data-lucide="mouse-pointer-click"></i> مصادر نقرات الإعلانات والبنرات</h3>
                <div class="chart-subtitle">توزيع نقرات العروض بحسب المنتج</div>
              </div>
            </div>
            <div class="chart-canvas-container">
              <canvas id="chart-ads-sources"></canvas>
            </div>
          </div>

          <!-- Chart 3: Category Distribution -->
          <div class="chart-card">
            <div class="chart-card-header">
              <div class="chart-title-wrap">
                <h3><i data-lucide="pie-chart"></i> توزيع المقالات والمشاهدات حسب الأقسام</h3>
                <div class="chart-subtitle">نسبة المحتوى والمشاهدات لكل قسم من أقسام الموقع</div>
              </div>
            </div>
            <div class="chart-canvas-container">
              <canvas id="chart-category-dist"></canvas>
            </div>
          </div>

          <!-- Chart 4: Comparison Chart -->
          <div class="chart-card full-width">
            <div class="chart-card-header">
              <div class="chart-title-wrap">
                <h3><i data-lucide="bar-chart-2"></i> مقارنة الأداء الشامل (الفترة الحالية مقابل السابقة)</h3>
                <div class="chart-subtitle">مقارنة نمو الزيارات والنقرات والمقالات والتعليقات مع الفترة المكافئة</div>
              </div>
            </div>
            <div class="chart-canvas-container">
              <canvas id="chart-comparison"></canvas>
            </div>
          </div>
        </div>
      `;
  refreshIcons();

  if (typeof Chart === 'undefined') {
    ['chart-activity-trends', 'chart-ads-sources', 'chart-category-dist', 'chart-comparison'].forEach(id => {
      var el = document.getElementById(id);
      if (el && el.parentElement) {
        el.parentElement.innerHTML = `
              <div class="empty-state" style="padding:40px;">
                <i data-lucide="alert-circle" style="color:var(--brand-600); width:36px; height:36px;"></i>
                <p style="margin-top:10px; font-weight:600;">تعذّر تحميل مكتبة الرسوم البيانية (Chart.js)</p>
                <span style="font-size:12px; color:var(--text-muted);">يرجى التأكد من الاتصال بالإنترنت لإظهار المخططات.</span>
              </div>`;
      }
    });
    refreshIcons();
    return;
  }

  initChartActivityTrends(trends);
  initChartAdsSources(dist);
  initChartCategoryDist(dist);
  initChartComparison(comp);
}

function initChartActivityTrends(trends) {
  var canvas = document.getElementById('chart-activity-trends');
  if (!canvas) return;

  var colors = getChartThemeColors();
  var labels = (trends && trends.labels) ? trends.labels : [];
  var visitsData = (trends && trends.visits) ? trends.visits : [];
  var adsData = (trends && trends.ad_clicks) ? trends.ad_clicks : [];
  var postsData = (trends && trends.posts) ? trends.posts : [];
  var cmtsData = (trends && trends.comments) ? trends.comments : [];

  var ctx = canvas.getContext('2d');
  var gradVisits = ctx.createLinearGradient(0, 0, 0, 300);
  gradVisits.addColorStop(0, 'rgba(59, 130, 246, 0.35)');
  gradVisits.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

  var gradAds = ctx.createLinearGradient(0, 0, 0, 300);
  gradAds.addColorStop(0, 'rgba(16, 185, 129, 0.30)');
  gradAds.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

  if (analyticsChartInstances.activity) {
    analyticsChartInstances.activity.destroy();
  }

  analyticsChartInstances.activity = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'زيارات المقالات (تتبع حي)',
          data: visitsData,
          borderColor: '#3b82f6',
          backgroundColor: gradVisits,
          borderWidth: 2.5,
          fill: true,
          tension: 0.3,
          pointRadius: 3,
          pointHoverRadius: 6,
          pointBackgroundColor: '#3b82f6',
        },
        {
          label: 'نقرات الإعلانات والبنرات',
          data: adsData,
          borderColor: '#10b981',
          backgroundColor: gradAds,
          borderWidth: 2.2,
          fill: true,
          tension: 0.3,
          pointRadius: 3,
          pointHoverRadius: 6,
          pointBackgroundColor: '#10b981',
        },
        {
          label: 'المقالات المنشورة',
          data: postsData,
          borderColor: '#f59e0b',
          backgroundColor: 'transparent',
          borderWidth: 2,
          fill: false,
          tension: 0.3,
          pointRadius: 2.5,
          pointHoverRadius: 5,
          pointBackgroundColor: '#f59e0b',
        },
        {
          label: 'التعليقات',
          data: cmtsData,
          borderColor: '#f43f5e',
          backgroundColor: 'transparent',
          borderWidth: 2,
          borderDash: [4, 4],
          fill: false,
          tension: 0.3,
          pointRadius: 2.5,
          pointHoverRadius: 5,
          pointBackgroundColor: '#f43f5e',
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'top',
          align: 'end',
          rtl: true,
          labels: {
            color: colors.textColor,
            font: { family: 'Cairo', size: 12, weight: 600 },
            boxWidth: 12,
            boxHeight: 12,
            usePointStyle: true,
          }
        },
        tooltip: {
          rtl: true,
          titleFont: { family: 'Cairo', weight: 700 },
          bodyFont: { family: 'Cairo' },
          padding: 10,
          boxPadding: 6,
          cornerRadius: 8,
        }
      },
      scales: {
        x: {
          grid: { color: colors.gridColor, drawBorder: false },
          ticks: { color: colors.textColor, font: { family: 'Outfit, Cairo', size: 11 } }
        },
        y: {
          beginAtZero: true,
          grid: { color: colors.gridColor, drawBorder: false },
          ticks: {
            color: colors.textColor,
            font: { family: 'Outfit, Cairo', size: 11 },
            precision: 0
          }
        }
      }
    }
  });
}

function initChartAdsSources(dist) {
  var canvas = document.getElementById('chart-ads-sources');
  if (!canvas) return;

  var colors = getChartThemeColors();
  var sources = (dist && dist.ad_sources) ? dist.ad_sources : [];

  if (!sources || sources.length === 0) {
    canvas.parentElement.innerHTML = '<div class="empty-state" style="padding:40px;"><i data-lucide="inbox"></i><p>لا توجد نقرات مسجلة للإعلانات في هذه الفترة</p></div>';
    refreshIcons();
    return;
  }

  var labels = sources.map(s => s.source || 'بدون منتج');
  var dataVals = sources.map(s => parseInt(s.clicks, 10));
  var palette = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#64748b'];

  var ctx = canvas.getContext('2d');
  if (analyticsChartInstances.adsSources) {
    analyticsChartInstances.adsSources.destroy();
  }

  analyticsChartInstances.adsSources = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: labels,
      datasets: [{
        data: dataVals,
        backgroundColor: palette.slice(0, labels.length),
        borderWidth: 2,
        borderColor: colors.surfaceColor,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
          rtl: true,
          labels: {
            color: colors.textColor,
            font: { family: 'Cairo', size: 12 },
            boxWidth: 12,
            boxHeight: 12,
            usePointStyle: true
          }
        },
        tooltip: {
          rtl: true,
          callbacks: {
            label: function (item) {
              var val = item.raw || 0;
              return ` ${val.toLocaleString('en')} نقرة`;
            }
          }
        }
      },
      cutout: '65%'
    }
  });
}

function initChartCategoryDist(dist) {
  var canvas = document.getElementById('chart-category-dist');
  if (!canvas) return;

  var colors = getChartThemeColors();
  var cats = (dist && dist.categories) ? dist.categories : [];

  if (!cats || cats.length === 0) {
    canvas.parentElement.innerHTML = '<div class="empty-state" style="padding:40px;"><i data-lucide="inbox"></i><p>لا توجد بيانات أقسام</p></div>';
    refreshIcons();
    return;
  }

  var labels = cats.map(c => c.name);
  var dataVals = cats.map(c => parseInt(c.post_count, 10));
  var palette = ['#f59e0b', '#6366f1', '#10b981', '#f43f5e', '#06b6d4', '#8b5cf6', '#ec4899', '#64748b'];

  var ctx = canvas.getContext('2d');
  if (analyticsChartInstances.categories) {
    analyticsChartInstances.categories.destroy();
  }

  analyticsChartInstances.categories = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: labels,
      datasets: [{
        data: dataVals,
        backgroundColor: palette.slice(0, labels.length),
        borderWidth: 2,
        borderColor: colors.surfaceColor,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
          rtl: true,
          labels: {
            color: colors.textColor,
            font: { family: 'Cairo', size: 12 },
            boxWidth: 12,
            boxHeight: 12,
            usePointStyle: true
          }
        },
        tooltip: {
          rtl: true,
          callbacks: {
            label: function (item) {
              var cat = cats[item.dataIndex];
              var val = item.raw || 0;
              var views = cat ? parseInt(cat.total_views, 10) : 0;
              return ` ${val} مقال (${views.toLocaleString('en')} مشاهدة)`;
            }
          }
        }
      },
      cutout: '65%'
    }
  });
}

function initChartComparison(comp) {
  var canvas = document.getElementById('chart-comparison');
  if (!canvas) return;

  var colors = getChartThemeColors();
  var cData = (comp && comp.comparison) ? comp.comparison : {};
  var periodLabels = (comp && comp.period) ? comp.period : { current: 'الحالية', previous: 'السابقة' };

  var metrics = [
    { label: 'زيارات المقالات (تتبع حي)', key: 'visits' },
    { label: 'نقرات الإعلانات والبنرات', key: 'ad_clicks' },
    { label: 'المقالات المنشورة', key: 'posts' },
    { label: 'التعليقات', key: 'comments' },
    { label: 'الروابط المختصرة', key: 'links' },
  ];

  var labels = metrics.map(m => m.label);
  var currentVals = metrics.map(m => cData[m.key] ? cData[m.key].current : 0);
  var prevVals = metrics.map(m => cData[m.key] ? cData[m.key].previous : 0);

  var ctx = canvas.getContext('2d');
  if (analyticsChartInstances.comparison) {
    analyticsChartInstances.comparison.destroy();
  }

  analyticsChartInstances.comparison = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          label: periodLabels.current || 'الفترة الحالية',
          data: currentVals,
          backgroundColor: '#f59e0b',
          borderRadius: 6,
          barPercentage: 0.6,
          categoryPercentage: 0.7
        },
        {
          label: periodLabels.previous || 'الفترة السابقة',
          data: prevVals,
          backgroundColor: colors.muted,
          borderRadius: 6,
          barPercentage: 0.6,
          categoryPercentage: 0.7
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          align: 'end',
          rtl: true,
          labels: {
            color: colors.textColor,
            font: { family: 'Cairo', size: 12 },
            boxWidth: 12,
            boxHeight: 12,
            usePointStyle: true
          }
        },
        tooltip: {
          rtl: true,
          callbacks: {
            afterBody: function (items) {
              if (items.length > 0) {
                var idx = items[0].dataIndex;
                var mKey = metrics[idx].key;
                var item = cData[mKey];
                if (item && item.change) {
                  return `التغير: ${item.change.label}`;
                }
              }
              return '';
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: colors.textColor, font: { family: 'Cairo', size: 12 } }
        },
        y: {
          beginAtZero: true,
          grid: { color: colors.gridColor, drawBorder: false },
          ticks: { color: colors.textColor, precision: 0 }
        }
      }
    }
  });
}

function refreshAnalyticsChartsTheme() {
  if (analyticsState.rawTrends || analyticsState.rawDist || analyticsState.rawComp) {
    initChartActivityTrends(analyticsState.rawTrends);
    initChartAdsSources(analyticsState.rawDist);
    initChartCategoryDist(analyticsState.rawDist);
    initChartComparison(analyticsState.rawComp);
  }
}

// ---------- Detailed Tables Section ----------
function renderAnalyticsTables() {
  var root = document.getElementById('analytics-tables-root');
  if (!root) return;

  root.innerHTML = `
        <div class="analytics-table-card">
          <div class="analytics-tabs">
            <button class="analytics-tab-btn ${analyticsState.tableTab === 'posts' ? 'active' : ''}" onclick="switchAnalyticsTableTab('posts')">
              <i data-lucide="newspaper"></i> المقالات (قديمة وجديدة)
            </button>
            <button class="analytics-tab-btn ${analyticsState.tableTab === 'ads' ? 'active' : ''}" onclick="switchAnalyticsTableTab('ads')">
              <i data-lucide="mouse-pointer-click"></i> تتبع نقرات العروض (jb_promo_clicks)
            </button>
            <button class="analytics-tab-btn ${analyticsState.tableTab === 'visits' ? 'active' : ''}" onclick="switchAnalyticsTableTab('visits')">
              <i data-lucide="footprints"></i> سجل زيارات المقالات (news_tracking)
            </button>
            <button class="analytics-tab-btn ${analyticsState.tableTab === 'categories' ? 'active' : ''}" onclick="switchAnalyticsTableTab('categories')">
              <i data-lucide="folder-tree"></i> أداء وإحصائيات الأقسام
            </button>
          </div>

          <div id="analytics-tab-content">
            <div class="loader-shimmer" style="height:140px;"></div>
          </div>
        </div>
        <details id="analytics-google-crawler-panel" class="analytics-table-card" style="margin-top:18px;">
          <summary style="padding:18px 20px; cursor:pointer; font-weight:700;">زيارات Google Mediapartners (Bot) — سجل مستقل</summary>
          <p style="margin:0; padding:0 20px 14px; color:var(--text-muted); font-size:12.5px;">هاد السجل ما كيدخلش فإحصائيات نقرات الإعلانات ولا الروابط المختصرة.</p>
          <div id="analytics-google-crawler-content" style="padding:0 20px 16px;">حلّ هاد القسم لتحميل السجل.</div>
        </details>
      `;
  refreshIcons();
  var crawlerPanel = document.getElementById('analytics-google-crawler-panel');
  if (crawlerPanel) {
    crawlerPanel.addEventListener('toggle', function () {
      if (crawlerPanel.open && crawlerPanel.dataset.loaded !== 'true') {
        crawlerPanel.dataset.loaded = 'true';
        loadAnalyticsGoogleCrawler(analyticsState.googleCrawlerPage);
      }
    });
  }
}

function loadAnalyticsGoogleCrawler(page = 1) {
  analyticsState.googleCrawlerPage = page;
  var root = document.getElementById('analytics-google-crawler-content');
  if (!root) return;
  fetch(`api.php?action=analytics_google_crawler&page=${page}`, { cache: 'no-store' })
    .then(response => response.json())
    .then(data => {
      if (!data.success) throw new Error(data.message || 'تعذر تحميل سجل Google');
      var rows = (data.visits || []).map(item => {
        var pageName = item.page_url
          ? `<span title="${escapeHTML(item.page_url)}" style="direction:ltr; display:inline-block; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHTML(item.page_url)}</span>`
          : `<span style="color:var(--text-muted);">صفحة غير معرّفة</span>`;
        return `<tr>
          <td>#${escapeHTML(item.id)}</td>
          <td>${pageName}<br><small>المنتج: ${escapeHTML(item.product_id || '—')} | المقال: ${escapeHTML(item.post_id || '—')}</small></td>
          <td>${escapeHTML(item.device_type || '—')}</td>
          <td style="direction:ltr; text-align:right;">${escapeHTML(item.referrer || '—')}</td>
          <td><span class="tracking-time-badge">${escapeHTML(item.visited_at || '—')}</span></td>
          <td>${escapeHTML(item.ip_hash || '—')}</td>
          <td><details><summary style="cursor:pointer;">عرض</summary><small style="direction:ltr; display:block; max-width:360px; overflow-wrap:anywhere;">${escapeHTML(item.user_agent || '')}</small></details></td>
        </tr>`;
      }).join('');
      root.innerHTML = `<div class="analytics-summary-bar" style="margin-top:0;">
          <div class="analytics-stat-chip"><i data-lucide="bot"></i> إجمالي زيارات Google المسجلة: <strong>${Number(data.pagination.total || 0).toLocaleString('en')}</strong></div>
        </div>
        <div class="table-responsive"><table>
          <thead><tr><th>#</th><th>الصفحة</th><th>الجهاز</th><th>الإحالة</th><th>وقت الزيارة</th><th>بصمة IP</th><th>User-Agent</th></tr></thead>
          <tbody>${rows || '<tr><td colspan="7"><div class="empty-state"><p>ما تسجلات حتى زيارة من هاد الـBot</p></div></td></tr>'}</tbody>
        </table></div>
        ${renderPagination(data.pagination)}`;
      refreshIcons();
      bindPagination(root, nextPage => loadAnalyticsGoogleCrawler(nextPage));
    })
    .catch(error => {
      console.error('[9sty Analytics] Google crawler log failed:', error);
      root.innerHTML = `<div class="error-msg">تعذر تحميل سجل زيارات Google: ${escapeHTML(error.message || '')}</div>`;
    });
}

function switchAnalyticsTableTab(tab) {
  analyticsState.tableTab = tab;
  renderAnalyticsTables();
  if (tab === 'posts') {
    loadAnalyticsTopPosts(analyticsState.postsPage);
  } else if (tab === 'ads') {
    loadAnalyticsAdsTable(analyticsState.adsPage);
  } else if (tab === 'visits') {
    loadAnalyticsVisitsTable(analyticsState.visitsPage);
  } else {
    loadAnalyticsCategoriesTable();
  }
}

// ===== Tab 1: Posts (Old and New) =====
function loadAnalyticsTopPosts(page = 1) {
  analyticsState.postsPage = page;
  var content = document.getElementById('analytics-tab-content');
  if (!content) return;

  var url = `api.php?action=analytics_top_posts&page=${page}&per_page=12&sort_by=${encodeURIComponent(analyticsState.postsSort)}&order=${encodeURIComponent(analyticsState.postsOrder)}`;
  if (analyticsState.postsSearch) {
    url += `&search=${encodeURIComponent(analyticsState.postsSearch)}`;
  }
  if (analyticsState.filterTableByRange) {
    url += `&filter_date_range=1&range=${encodeURIComponent(analyticsState.range)}`;
    if (analyticsState.range === 'custom') {
      url += `&start_date=${encodeURIComponent(analyticsState.customStart)}&end_date=${encodeURIComponent(analyticsState.customEnd)}`;
    }
  }

  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        content.innerHTML = `<div class="error-msg">${escapeHTML(data.message || 'خطأ')}</div>`;
        return;
      }

      var rows = '';
      var statusMap = { published: 'منشور', draft: 'مسودة', archived: 'مؤرشف' };

      data.posts.forEach((p, idx) => {
        var rank = (page - 1) * 12 + idx + 1;
        var rankBadge = rank <= 3
          ? `<span style="background:var(--brand-50); color:var(--brand-600); border:1px solid var(--brand-100); width:24px; height:24px; border-radius:50%; display:inline-grid; place-items:center; font-weight:800; font-size:12px;">${rank}</span>`
          : `<span style="color:var(--text-muted); font-weight:600; font-size:12px;">#${rank}</span>`;

        var img = p.image
          ? `<img class="thumb-preview" src="https://cdn.9sty.com/uploads/${p.image}" loading="lazy" width="46" height="32" style="border-radius:6px; object-fit:cover;">`
          : `<span style="color:var(--text-faint); font-size:11px;">—</span>`;

        var scBadge = p.short_code
          ? `<code style="background:var(--surface-2); padding:2px 6px; border-radius:4px; font-size:11px; color:var(--text-muted);">${escapeHTML(p.short_code)}</code>`
          : '';

        var periodVisitsBadge = (p.period_visits !== undefined && p.period_visits !== null)
          ? `<span style="font-weight:700; color:#2563eb;">${(parseInt(p.period_visits, 10) || 0).toLocaleString('en')}</span>`
          : `<span style="color:var(--text-muted);">—</span>`;

        rows += `
              <tr>
                <td style="text-align:center; width:45px;">${rankBadge}</td>
                <td style="width:55px;">${img}</td>
                <td style="font-weight:600; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                  <a href="javascript:void(0)" onclick="urlEditPost(${p.id})" style="color:var(--text);">${escapeHTML(p.title)}</a>
                  ${scBadge ? '<div style="margin-top:2px;">' + scBadge + '</div>' : ''}
                </td>
                <td><span class="badge" style="background:var(--surface-2); border:1px solid var(--border); font-size:11.5px;">${escapeHTML(p.category_name || 'عام')}</span></td>
                <td style="font-weight:700; color:var(--brand-600); font-family:var(--font-display);">${(p.views || 0).toLocaleString('en')}</td>
                <td>${periodVisitsBadge}</td>
                <td style="font-weight:700; color:#f43f5e;">${p.comments_count || 0}</td>
                <td><span class="badge ${p.status}">${statusMap[p.status] || p.status}</span></td>
                <td style="color:var(--text-muted); font-size:12px;">${escapeHTML(p.created_at ? p.created_at.substring(0, 10) : '')}</td>
                <td>
                  <button class="act-btn" onclick="urlEditPost(${p.id})" title="تعديل"><i data-lucide="pencil"></i></button>
                </td>
              </tr>
            `;
      });

      var rangeCheckState = analyticsState.filterTableByRange ? 'checked' : '';

      content.innerHTML = `
            <div class="analytics-filter-controls">
              <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <input type="text" class="analytics-search-input" id="ana-post-search" placeholder="بحث بالعنوان أو الكود..." value="${escapeHTML(analyticsState.postsSearch)}">
                <select class="analytics-sort-select" id="ana-post-sort" onchange="changeAnalyticsPostsSort(this.value)">
                  <option value="views" ${analyticsState.postsSort === 'views' ? 'selected' : ''}>الأعلى مشاهدة تراكمية (الكل)</option>
                  <option value="period_visits" ${analyticsState.postsSort === 'period_visits' ? 'selected' : ''}>الأكثر زيارة في الفترة المحددة</option>
                  <option value="comments" ${analyticsState.postsSort === 'comments' ? 'selected' : ''}>الأكثر تفاعلاً (تعليقات)</option>
                  <option value="created_at" ${analyticsState.postsSort === 'created_at' ? 'selected' : ''}>الأحدث نشراً</option>
                  <option value="id" ${analyticsState.postsSort === 'id' ? 'selected' : ''}>معرف المقال ID</option>
                </select>
              </div>

              <div style="display:flex; align-items:center; gap:8px;">
                <label style="font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; gap:6px;">
                  <input type="checkbox" id="ana-filter-by-range" ${rangeCheckState} onchange="toggleFilterTableByRange(this.checked)">
                  إظهار مقالات النطاق الزمني المحدد فقط
                </label>
                <button class="btn btn-secondary" onclick="exportAnalyticsPostsCSV()" style="padding:6px 12px; font-size:12.5px;">
                  <i data-lucide="download"></i> تصدير CSV
                </button>
              </div>
            </div>

            <div class="table-responsive">
              <table>
                <thead>
                  <tr>
                    <th style="width:45px; text-align:center;">#</th>
                    <th>الغلاف</th>
                    <th>عنوان المقال</th>
                    <th>القسم</th>
                    <th>إجمالي المشاهدات</th>
                    <th>زيارات الفترة</th>
                    <th>التعليقات</th>
                    <th>الحالة</th>
                    <th>تاريخ النشر</th>
                    <th>إجراء</th>
                  </tr>
                </thead>
                <tbody>
                  ${rows || `<tr><td colspan="10"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد نتائج مطابقة</p></div></td></tr>`}
                </tbody>
              </table>
            </div>

            ${renderPagination(data.pagination)}
          `;
      refreshIcons();

      bindPagination(content, p => loadAnalyticsTopPosts(p));

      var searchInput = document.getElementById('ana-post-search');
      if (searchInput) {
        var debounceTimer;
        searchInput.addEventListener('input', e => {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(() => {
            analyticsState.postsSearch = e.target.value.trim();
            analyticsState.postsPage = 1;
            loadAnalyticsTopPosts(1);
          }, 300);
        });
      }
    })
    .catch(() => {
      content.innerHTML = `<div class="error-msg">تعذّر جلب بيانات المقالات</div>`;
    });
}

function changeAnalyticsPostsSort(val) {
  analyticsState.postsSort = val;
  analyticsState.postsPage = 1;
  loadAnalyticsTopPosts(1);
}

function toggleFilterTableByRange(checked) {
  analyticsState.filterTableByRange = checked;
  analyticsState.postsPage = 1;
  loadAnalyticsTopPosts(1);
}

function exportAnalyticsPostsCSV() {
  var url = `api.php?action=analytics_top_posts&page=1&per_page=500&sort_by=${encodeURIComponent(analyticsState.postsSort)}&order=${encodeURIComponent(analyticsState.postsOrder)}`;
  if (analyticsState.postsSearch) {
    url += `&search=${encodeURIComponent(analyticsState.postsSearch)}`;
  }
  if (analyticsState.filterTableByRange) {
    url += `&filter_date_range=1&range=${encodeURIComponent(analyticsState.range)}`;
    if (analyticsState.range === 'custom') {
      url += `&start_date=${encodeURIComponent(analyticsState.customStart)}&end_date=${encodeURIComponent(analyticsState.customEnd)}`;
    }
  }

  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.posts) {
        showToast('تعذر تصدير البيانات', 'error');
        return;
      }
      var csv = '\uFEFF';
      csv += 'ID,العنوان,القسم,المشاهدات_التراكمية,زيارات_الفترة,التعليقات,الحالة,تاريخ_النشر\n';
      data.posts.forEach(p => {
        var title = (p.title || '').replace(/"/g, '""');
        var cat = (p.category_name || 'عام').replace(/"/g, '""');
        var pv = p.period_visits || 0;
        csv += `"${p.id}","${title}","${cat}",${p.views || 0},${pv},${p.comments_count || 0},"${p.status}","${p.created_at || ''}"\n`;
      });

      var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      var link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.setAttribute('download', `analytics_posts_${analyticsState.range}_${new Date().toISOString().slice(0, 10)}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      showToast('تم تصدير ملف CSV بنجاح');
    })
    .catch(() => showToast('خطأ أثناء تصدير الملف', 'error'));
}

// ===== Tab 2: Promotional Click Tracking (jb_promo_clicks) =====
function loadAnalyticsAdsTable(page = 1) {
  analyticsState.adsPage = page;
  var content = document.getElementById('analytics-tab-content');
  if (!content) return;

  var url = buildAnalyticsQuery('analytics_promo_clicks') + `&page=${page}&per_page=15`;
  if (analyticsState.adsSearch) {
    url += `&search=${encodeURIComponent(analyticsState.adsSearch)}`;
  }
  if (analyticsState.adsSourceFilter) {
    url += `&product_id=${encodeURIComponent(analyticsState.adsSourceFilter)}`;
  }

  fetch(url, { cache: 'no-store' })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        content.innerHTML = `<div class="error-msg">${escapeHTML(data.message || 'خطأ')}</div>`;
        return;
      }

      var summary = data.summary || { total_clicks: 0, distinct_sources: 0, distinct_ips: 0, top_source: '—' };
      var rows = '';

      (data.clicks || []).forEach(item => {
        var sourcePill = item.product_id
          ? `<span class="ad-source-pill"><i data-lucide="tag"></i> ${escapeHTML(item.product_id)}</span>`
          : `<span style="color:var(--text-faint); font-size:11px;">—</span>`;

        var pageDisplay = item.page_url
          ? `<span title="${escapeHTML(item.page_url)}" style="color:var(--text); font-weight:600; direction:ltr; display:inline-block; max-width:230px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHTML(item.page_url)}</span>`
          : '<span style="color:var(--text-faint);">—</span>';

        var refDisplay = item.referrer
          ? `<span title="${escapeHTML(item.referrer)}" style="max-width:180px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; direction:ltr; text-align:right; font-size:12px; color:var(--text-muted);">${escapeHTML(item.referrer.replace(/^https?:\/\//, ''))}</span>`
          : `<span style="color:var(--text-faint); font-size:11px;">مباشر (Direct)</span>`;

        var postDisplay = item.post_id
          ? `<code style="background:var(--brand-50); color:var(--brand-600); padding:2px 6px; border-radius:4px; font-weight:700;">#${escapeHTML(item.post_id)}</code>`
          : '<span style="color:var(--text-faint);">—</span>';

        rows += `
              <tr>
                <td style="color:var(--text-muted); font-weight:600;">#${item.id}</td>
                <td>${sourcePill}</td>
                <td>${postDisplay}</td>
                <td>${pageDisplay}</td>
                <td>${refDisplay}</td>
                <td><span class="tracking-ip-badge">${escapeHTML(item.ip_hash || '—')}</span></td>
                <td>${escapeHTML(item.device_type || '—')}</td>
                <td><span class="tracking-time-badge">${escapeHTML(item.clicked_at || '—')}</span></td>
              </tr>
            `;
      });

      content.innerHTML = `
            <div class="analytics-summary-bar">
              <div class="analytics-stat-chip">
                <i data-lucide="mouse-pointer-click" style="color:var(--brand-600); width:16px; height:16px;"></i>
                إجمالي النقرات المسجلة: <strong>${summary.total_clicks.toLocaleString('en')}</strong>
              </div>
              <div class="analytics-stat-chip">
                <i data-lucide="layers" style="color:#2563eb; width:16px; height:16px;"></i>
                المنتجات الفريدة: <strong>${summary.distinct_sources}</strong>
              </div>
              <div class="analytics-stat-chip">
                <i data-lucide="users" style="color:#059669; width:16px; height:16px;"></i>
                بصمات IP الفريدة: <strong>${summary.distinct_ips.toLocaleString('en')}</strong>
              </div>
              <div class="analytics-stat-chip">
                <i data-lucide="star" style="color:#d97706; width:16px; height:16px;"></i>
                المنتج الأكثر نقراً: <strong>${escapeHTML(summary.top_source || '—')}</strong>
              </div>
            </div>

            <div class="analytics-filter-controls">
              <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <input type="text" class="analytics-search-input" id="ana-ads-search" placeholder="بحث بالمنتج، المقال، الجهاز، الرابط، أو الإحالة..." value="${escapeHTML(analyticsState.adsSearch)}">
                <input type="text" class="analytics-search-input" id="ana-ads-source-filter" style="width:160px;" placeholder="فلترة بمعرف المنتج..." value="${escapeHTML(analyticsState.adsSourceFilter)}">
              </div>

              <div style="display:flex; align-items:center; gap:8px;">
                <button class="btn btn-secondary" onclick="exportAnalyticsAdsCSV()" style="padding:6px 12px; font-size:12.5px;">
                  <i data-lucide="download"></i> تصدير سجل النقرات CSV
                </button>
              </div>
            </div>

            <div class="table-responsive">
              <table>
                <thead>
                  <tr>
                    <th>#</th>
                    <th>معرف المنتج</th>
                    <th>معرف المقال</th>
                    <th>رابط الصفحة</th>
                    <th>مصدر الإحالة (Referrer)</th>
                    <th>بصمة IP</th>
                    <th>الجهاز</th>
                    <th>وقت النقر (Clicked At)</th>
                  </tr>
                </thead>
                <tbody>
                  ${rows || `<tr><td colspan="8"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد نقرات مسجلة في هذا النطاق الزمني</p></div></td></tr>`}
                </tbody>
              </table>
            </div>

            ${renderPagination(data.pagination)}
          `;
      refreshIcons();

      bindPagination(content, p => loadAnalyticsAdsTable(p));

      var searchInput = document.getElementById('ana-ads-search');
      if (searchInput) {
        var debounceAdsTimer;
        searchInput.addEventListener('input', e => {
          clearTimeout(debounceAdsTimer);
          debounceAdsTimer = setTimeout(() => {
            analyticsState.adsSearch = e.target.value.trim();
            analyticsState.adsPage = 1;
            loadAnalyticsAdsTable(1);
          }, 300);
        });
      }

      var sourceInput = document.getElementById('ana-ads-source-filter');
      if (sourceInput) {
        var debounceSourceTimer;
        sourceInput.addEventListener('input', e => {
          clearTimeout(debounceSourceTimer);
          debounceSourceTimer = setTimeout(() => {
            analyticsState.adsSourceFilter = e.target.value.trim();
            analyticsState.adsPage = 1;
            loadAnalyticsAdsTable(1);
          }, 300);
        });
      }
    })
    .catch(() => {
      content.innerHTML = `<div class="error-msg">تعذّر جلب بيانات تتبع الإعلانات</div>`;
    });
}

function exportAnalyticsAdsCSV() {
  var url = buildAnalyticsQuery('analytics_promo_clicks') + '&page=1&per_page=1000';
  if (analyticsState.adsSearch) {
    url += `&search=${encodeURIComponent(analyticsState.adsSearch)}`;
  }
  if (analyticsState.adsSourceFilter) {
    url += `&product_id=${encodeURIComponent(analyticsState.adsSourceFilter)}`;
  }

  fetch(url, { cache: 'no-store' })
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.clicks) {
        showToast('تعذر تصدير البيانات', 'error');
        return;
      }
      var csv = '\uFEFF';
      csv += 'ID,معرف_المنتج,معرف_المقال,رابط_الصفحة,نوع_الجهاز,بصمة_IP,الإحالة,User_Agent,وقت_النقر\n';
      data.clicks.forEach(a => {
        var fields = [a.id, a.product_id, a.post_id, a.page_url, a.device_type, a.ip_hash, a.referrer, a.user_agent, a.clicked_at];
        csv += fields.map(value => `"${String(value || '').replace(/"/g, '""')}"`).join(',') + '\n';
      });

      var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      var link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.setAttribute('download', `analytics_promo_clicks_${analyticsState.range}_${new Date().toISOString().slice(0, 10)}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      showToast('تم تصدير سجل نقرات الإعلانات بنجاح');
    })
    .catch(() => showToast('خطأ أثناء تصدير الملف', 'error'));
}

// ===== Tab 3: News Visits Tracking (news_tracking) =====
function loadAnalyticsVisitsTable(page = 1) {
  analyticsState.visitsPage = page;
  var content = document.getElementById('analytics-tab-content');
  if (!content) return;

  var url = buildAnalyticsQuery('analytics_news_tracking') + `&page=${page}&per_page=15`;
  if (analyticsState.visitsSearch) {
    url += `&search=${encodeURIComponent(analyticsState.visitsSearch)}`;
  }

  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        content.innerHTML = `<div class="error-msg">${escapeHTML(data.message || 'خطأ')}</div>`;
        return;
      }

      var summary = data.summary || { total_visits: 0, distinct_countries: 0, distinct_ips: 0 };
      var rows = '';

      data.visits.forEach(item => {
        var postDisplay = item.post_title
          ? `<a href="javascript:void(0)" onclick="urlEditPost(${item.post_id})" style="color:var(--text); font-weight:600;">${escapeHTML(item.post_title)}</a>`
          : `<span style="color:var(--text-muted); font-size:12px;">مقال غير معرّف</span>`;

        var scBadge = item.short_code
          ? `<code style="background:var(--brand-50); color:var(--brand-600); padding:2px 6px; border-radius:4px; font-weight:700; font-size:12px; border:1px solid var(--brand-100);">${escapeHTML(item.short_code)}</code>`
          : '<span style="color:var(--text-faint);">—</span>';

        var geoDisplay = item.country
          ? `<span style="display:inline-flex; align-items:center; gap:4px; font-weight:600;"><i data-lucide="map-pin" style="width:13px; height:13px; color:var(--brand-600);"></i> ${escapeHTML(item.country)}${item.city ? ' - ' + escapeHTML(item.city) : ''}</span>`
          : `<span style="color:var(--text-faint); font-size:11px;">غير محدد</span>`;

        var refDisplay = item.referer
          ? `<span title="${escapeHTML(item.referer)}" style="max-width:180px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; direction:ltr; text-align:right; font-size:12px; color:var(--text-muted);">${escapeHTML(item.referer.replace(/^https?:\/\//, ''))}</span>`
          : `<span style="color:var(--text-faint); font-size:11px;">مباشر (Direct)</span>`;

        rows += `
              <tr>
                <td style="color:var(--text-muted); font-weight:600;">#${item.id}</td>
                <td>${scBadge}</td>
                <td style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${postDisplay}</td>
                <td>${geoDisplay}</td>
                <td>${refDisplay}</td>
                <td><span class="tracking-ip-badge">${escapeHTML(item.ip_address || '—')}</span></td>
                <td><span class="tracking-time-badge">${escapeHTML(item.visited_at || '—')}</span></td>
              </tr>
            `;
      });

      content.innerHTML = `
            <div class="analytics-summary-bar">
              <div class="analytics-stat-chip">
                <i data-lucide="footprints" style="color:#2563eb; width:16px; height:16px;"></i>
                إجمالي زيارات الفترة: <strong>${summary.total_visits.toLocaleString('en')}</strong>
              </div>
              <div class="analytics-stat-chip">
                <i data-lucide="globe" style="color:var(--brand-600); width:16px; height:16px;"></i>
                الدول المتواجدة: <strong>${summary.distinct_countries}</strong>
              </div>
              <div class="analytics-stat-chip">
                <i data-lucide="users" style="color:#059669; width:16px; height:16px;"></i>
                عناوين IP الفريدة: <strong>${summary.distinct_ips.toLocaleString('en')}</strong>
              </div>
            </div>

            <div class="analytics-filter-controls">
              <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <input type="text" class="analytics-search-input" id="ana-visits-search" placeholder="بحث بالـ IP، الكود، الدولة، أو الرابط..." value="${escapeHTML(analyticsState.visitsSearch)}">
              </div>

              <div style="display:flex; align-items:center; gap:8px;">
                <button class="btn btn-secondary" onclick="exportAnalyticsVisitsCSV()" style="padding:6px 12px; font-size:12.5px;">
                  <i data-lucide="download"></i> تصدير سجل الزيارات CSV
                </button>
              </div>
            </div>

            <div class="table-responsive">
              <table>
                <thead>
                  <tr>
                    <th>#</th>
                    <th>كود المقال</th>
                    <th>عنوان المقال</th>
                    <th>الدولة والمدينة</th>
                    <th>مصدر الإحالة</th>
                    <th>عنوان IP</th>
                    <th>وقت الزيارة</th>
                  </tr>
                </thead>
                <tbody>
                  ${rows || `<tr><td colspan="7"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد زيارات مسجلة في هذا النطاق الزمني</p></div></td></tr>`}
                </tbody>
              </table>
            </div>

            ${renderPagination(data.pagination)}
          `;
      refreshIcons();

      bindPagination(content, p => loadAnalyticsVisitsTable(p));

      var searchInput = document.getElementById('ana-visits-search');
      if (searchInput) {
        var debounceVisitsTimer;
        searchInput.addEventListener('input', e => {
          clearTimeout(debounceVisitsTimer);
          debounceVisitsTimer = setTimeout(() => {
            analyticsState.visitsSearch = e.target.value.trim();
            analyticsState.visitsPage = 1;
            loadAnalyticsVisitsTable(1);
          }, 300);
        });
      }
    })
    .catch(() => {
      content.innerHTML = `<div class="error-msg">تعذّر جلب سجل زيارات المقالات</div>`;
    });
}

function exportAnalyticsVisitsCSV() {
  var url = buildAnalyticsQuery('analytics_news_tracking') + '&page=1&per_page=1000';
  if (analyticsState.visitsSearch) {
    url += `&search=${encodeURIComponent(analyticsState.visitsSearch)}`;
  }

  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.visits) {
        showToast('تعذر تصدير البيانات', 'error');
        return;
      }
      var csv = '\uFEFF';
      csv += 'ID,كود_المقال,عنوان_المقال,الدولة,المدينة,الإحالة,IP,وقت_الزيارة\n';
      data.visits.forEach(v => {
        var sc = (v.short_code || '').replace(/"/g, '""');
        var title = (v.post_title || '').replace(/"/g, '""');
        var country = (v.country || '').replace(/"/g, '""');
        var city = (v.city || '').replace(/"/g, '""');
        var ref = (v.referer || '').replace(/"/g, '""');
        csv += `"${v.id}","${sc}","${title}","${country}","${city}","${ref}","${v.ip_address || ''}","${v.visited_at || ''}"\n`;
      });

      var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      var link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.setAttribute('download', `analytics_visits_${analyticsState.range}_${new Date().toISOString().slice(0, 10)}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      showToast('تم تصدير سجل الزيارات بنجاح');
    })
    .catch(() => showToast('خطأ أثناء تصدير الملف', 'error'));
}

// ===== Tab 4: Categories Performance =====
function loadAnalyticsCategoriesTable() {
  var content = document.getElementById('analytics-tab-content');
  if (!content) return;

  fetch('api.php?action=analytics_categories_table')
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        content.innerHTML = `<div class="error-msg">${escapeHTML(data.message || 'خطأ')}</div>`;
        return;
      }

      var rows = '';
      data.categories.forEach(c => {
        var headerBadge = c.show_in_header != 0
          ? '<span class="badge active">ظاهر بالهيدر</span>'
          : '<span class="badge inactive">مخفي</span>';

        rows += `
              <tr>
                <td style="font-weight:700;">#${c.id}</td>
                <td style="font-weight:700; color:var(--text);">${escapeHTML(c.name)}</td>
                <td><code style="font-size:12px; color:var(--text-muted);">/${escapeHTML(c.slug)}</code></td>
                <td style="font-weight:700; color:var(--brand-600);">${(c.posts_count || 0).toLocaleString('en')}</td>
                <td style="font-weight:700; color:#4f46e5;">${(parseInt(c.total_views, 10) || 0).toLocaleString('en')}</td>
                <td style="font-weight:600; color:var(--text-muted);">${(c.avg_views || 0).toLocaleString('en')}</td>
                <td style="font-weight:700; color:#e11d48;">${(c.comments_count || 0).toLocaleString('en')}</td>
                <td>${headerBadge}</td>
                <td><span class="badge ${c.status}">${c.status === 'active' ? 'نشط' : 'معطل'}</span></td>
              </tr>
            `;
      });

      content.innerHTML = `
            <div class="table-responsive">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>اسم القسم</th>
                    <th>الرابط (Slug)</th>
                    <th>عدد المقالات</th>
                    <th>إجمالي المشاهدات</th>
                    <th>متوسط المشاهدات/مقال</th>
                    <th>إجمالي التعليقات</th>
                    <th>الهيدر</th>
                    <th>الحالة</th>
                  </tr>
                </thead>
                <tbody>
                  ${rows || `<tr><td colspan="9"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد أقسام</p></div></td></tr>`}
                </tbody>
              </table>
            </div>
          `;
      refreshIcons();
    })
    .catch(() => {
      content.innerHTML = `<div class="error-msg">تعذّر جلب إحصائيات الأقسام</div>`;
    });
}

// ---------- Posts ----------
function renderPosts(container, page = 1) {
  container.innerHTML = `
            <div class="card">
              <div class="loader-shimmer" style="width:40%; margin-bottom:16px;"></div>
              <div class="loader-shimmer" style="margin-bottom:10px;"></div>
              <div class="loader-shimmer" style="margin-bottom:10px;"></div>
            </div>`;

  fetch(`api.php?action=list_posts&page=${page}&per_page=20`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) { container.innerHTML = `<div class="error-msg">${escapeHTML(data.message)}</div>`; return; }
      let rows = '';

      data.posts.forEach(p => {
        const img = p.image
          ? `<img class="thumb-preview" src="https://cdn.9sty.com/uploads/${p.image}" loading="lazy" decoding="async" width="52" height="36" alt="">`
          : `<span style="color:var(--text-faint); font-size:11px;">—</span>`;
        const viewBtn = p.slug
          ? `<a class="act-btn" href="https://9sty.com/${encodeURIComponent(p.slug)}" target="_blank" rel="noopener" title="عرض المقال على الموقع"><i data-lucide="external-link"></i></a>`
          : (p.short_code
            ? `<a class="act-btn" href="https://9sty.com/s/${encodeURIComponent(p.short_code)}" target="_blank" rel="noopener" title="عرض عبر الكود المختصر"><i data-lucide="external-link"></i></a>`
            : '');

        const scBadge = p.short_code
          ? `<code style="background:var(--surface-2); padding:3px 8px; border-radius:6px; border:1px solid var(--border); font-size:12px; font-weight:700; color:var(--brand-600); letter-spacing:1px;">${p.short_code}</code>`
          : `<span style="color:var(--text-faint); font-size:11px;">—</span>`;

        const statusMap = { published: 'منشور', draft: 'مسودة', archived: 'مؤرشف' };

        rows += `
                  <tr>
                    <td style="color:var(--text-muted); font-weight:600;">#${p.id}</td>
                    <td>${img}</td>
                    <td style="font-weight:600; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHTML(p.title)}</td>
                    <td>${scBadge}</td>
                    <td>${escapeHTML(p.category_name || 'عام')}</td>
                    <td><span class="badge ${p.status}">${statusMap[p.status] || p.status}</span></td>
                    <td style="color:var(--text-muted); font-weight:600;">${p.views}</td>
                    <td>
                      <div class="table-actions">
                        ${viewBtn}
                        <button class="act-btn" onclick="urlEditPost(${p.id})" title="تعديل"><i data-lucide="pencil"></i></button>
                        <button class="act-btn del" onclick="deletePost(${p.id})" title="حذف"><i data-lucide="trash-2"></i></button>
                      </div>
                    </td>
                  </tr>`;
      });

      container.innerHTML = `
                <div class="card">
                  <div class="card-header">
                    <h2>الأخبار والمقالات <span style="color:var(--text-muted); font-size:13px; font-weight:600;">(${data.pagination.total})</span></h2>
                    <button class="btn" onclick="urlNewPost()"><i data-lucide="plus"></i> خبر جديد</button>
                  </div>
                  <div class="table-responsive">
                    <table>
                      <thead><tr>
                        <th>ID</th><th>الغلاف</th><th>العنوان</th><th>Short Code</th>
                        <th>القسم</th><th>الحالة</th><th>المشاهدات</th><th>إجراءات</th>
                      </tr></thead>
                      <tbody>
                        ${rows || `<tr><td colspan="8"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد مقالات</p></div></td></tr>`}
                      </tbody>
                    </table>
                  </div>
                  ${renderPagination(data.pagination)}
                </div>`;

      refreshIcons();
      bindPagination(container, p => renderPosts(container, p));
    })
    .catch(() => { container.innerHTML = `<div class="error-msg">تعذّر الاتصال</div>`; });
}

// ---------- Short Posts (post_type = 'post') ----------
var shortPostsState = {
  page: 1,
  search: '',
  status: ''
};

function renderShortPosts(container, page = 1) {
  shortPostsState.page = page;
  container.innerHTML = `
    <div class="card">
      <div class="loader-shimmer" style="width:40%; margin-bottom:16px;"></div>
      <div class="loader-shimmer" style="margin-bottom:10px;"></div>
      <div class="loader-shimmer" style="margin-bottom:10px;"></div>
    </div>`;

  var params = new URLSearchParams({
    action: 'list_short_posts',
    page: page,
    per_page: 15
  });
  if (shortPostsState.search) params.append('search', shortPostsState.search);
  if (shortPostsState.status) params.append('status', shortPostsState.status);

  fetch('api.php?' + params.toString())
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        container.innerHTML = `<div class="error-msg">${escapeHTML(data.message || 'حدث خطأ أثناء جلب المنشورات')}</div>`;
        return;
      }

      var rows = '';
      data.posts.forEach(p => {
        var imgTag = p.image
          ? `<img class="thumb-preview" src="${p.image.startsWith('http') ? p.image : 'https://cdn.9sty.com/uploads/' + p.image}" loading="lazy" decoding="async" width="52" height="36" alt="" style="object-fit:cover; border-radius:6px;">`
          : `<span style="color:var(--text-faint); font-size:11px;">—</span>`;

        var slugCode = p.slug || '';
        var postUrl = 'https://9sty.com/' + encodeURIComponent(slugCode);

        var slugBadge = slugCode
          ? `<div style="display:inline-flex; align-items:center; gap:6px;">
               <span class="slug-badge-code">${escapeHTML(slugCode)}</span>
               <button class="act-btn" onclick="copyShortPostLink('${escapeJS(slugCode)}')" title="نسخ الرابط"><i data-lucide="copy" style="width:13px; height:13px;"></i></button>
             </div>`
          : `<span style="color:var(--text-faint); font-size:11px;">—</span>`;

        var viewBtn = slugCode
          ? `<a class="act-btn" href="${postUrl}" target="_blank" rel="noopener" title="عرض المنشور"><i data-lucide="external-link"></i></a>`
          : '';

        var statusMap = { published: 'منشور', draft: 'مسودة', archived: 'مؤرشف' };
        var descSnippet = p.excerpt || p.content || '';
        if (descSnippet.length > 95) descSnippet = descSnippet.substring(0, 95) + '...';

        var titleDisplay = p.title
          ? escapeHTML(p.title)
          : `<span style="color:var(--text-faint); font-style:italic; font-size:12px;">بدون عنوان</span>`;

        rows += `
          <tr>
            <td style="color:var(--text-muted); font-weight:600;">#${p.id}</td>
            <td>${imgTag}</td>
            <td>${slugBadge}</td>
            <td style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHTML(p.excerpt || p.content || '')}">
              ${escapeHTML(descSnippet)}
            </td>
            <td style="font-weight:600; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
              <a href="javascript:void(0)" onclick="urlEditShortPost(${p.id})" style="color:var(--text);">${titleDisplay}</a>
            </td>
            <td>${escapeHTML(p.category_name || 'عام')}</td>
            <td><span class="badge ${p.status}">${statusMap[p.status] || p.status}</span></td>
            <td style="color:var(--text-muted); font-weight:600;">${p.views || 0}</td>
            <td style="color:var(--text-muted); font-size:12px; white-space:nowrap;">${(p.created_at || '').substring(0, 10)}</td>
            <td>
              <div class="table-actions">
                ${viewBtn}
                <button class="act-btn" onclick="urlEditShortPost(${p.id})" title="تعديل"><i data-lucide="pencil"></i></button>
                <button class="act-btn del" onclick="deleteShortPost(${p.id})" title="حذف"><i data-lucide="trash-2"></i></button>
              </div>
            </td>
          </tr>`;
      });

      container.innerHTML = `
        <div class="card">
          <div class="card-header" style="flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:10px;">
              <h2>المنشورات القصيرة (Short Posts)</h2>
              <span class="badge" style="background:var(--brand-50); color:var(--brand-600); font-weight:700;">${data.pagination.total} منشور</span>
            </div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
              <input type="text" id="sp-search-input" class="form-control" style="width:200px; padding:7px 12px; font-size:13px;" placeholder="بحث بالكود أو النص..." value="${escapeHTML(shortPostsState.search)}" onkeydown="if(event.key==='Enter') applyShortPostsSearch()">
              <select id="sp-status-filter" class="form-control" style="width:130px; padding:7px 10px; font-size:13px;" onchange="applyShortPostsStatusFilter(this.value)">
                <option value="" ${shortPostsState.status === '' ? 'selected' : ''}>كل الحالات</option>
                <option value="published" ${shortPostsState.status === 'published' ? 'selected' : ''}>منشور</option>
                <option value="draft" ${shortPostsState.status === 'draft' ? 'selected' : ''}>مسودة</option>
                <option value="archived" ${shortPostsState.status === 'archived' ? 'selected' : ''}>مؤرشف</option>
              </select>
              <button class="btn btn-secondary" onclick="applyShortPostsSearch()" title="بحث"><i data-lucide="search"></i></button>
              <button class="btn" onclick="urlNewShortPost()"><i data-lucide="plus"></i> منشور قصير جديد</button>
            </div>
          </div>
          <div class="table-responsive">
            <table>
              <thead><tr>
                <th>ID</th><th>الغلاف</th><th>رمز الرابط (Slug)</th><th>الوصف (Description)</th>
                <th>العنوان</th><th>القسم</th><th>الحالة</th><th>المشاهدات</th><th>التاريخ</th><th>إجراءات</th>
              </tr></thead>
              <tbody>
                ${rows || `<tr><td colspan="10"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد منشورات قصيرة حالياً</p><button class="btn" onclick="urlNewShortPost()" style="margin-top:10px;"><i data-lucide="plus"></i> أضف أول منشور قصير</button></div></td></tr>`}
              </tbody>
            </table>
          </div>
          ${renderPagination(data.pagination)}
        </div>`;

      refreshIcons();
      bindPagination(container, p => renderShortPosts(container, p));
    })
    .catch(() => {
      container.innerHTML = `<div class="error-msg">تعذّر الاتصال بالسيرفر</div>`;
    });
}

function applyShortPostsSearch() {
  var input = document.getElementById('sp-search-input');
  shortPostsState.search = input ? input.value.trim() : '';
  renderShortPosts(document.getElementById('main-content'), 1);
}

function applyShortPostsStatusFilter(val) {
  shortPostsState.status = val;
  renderShortPosts(document.getElementById('main-content'), 1);
}

function populateShortPostCategories(selectedId) {
  var sel = document.getElementById('sp-category');
  if (!sel) return;
  sel.innerHTML = '<option value="">— عام / بدون قسم —</option>';
  categoriesList.forEach(c => {
    var isSel = (selectedId && c.id == selectedId) ? 'selected' : '';
    sel.innerHTML += `<option value="${c.id}" ${isSel}>${escapeHTML(c.name)}</option>`;
  });
}

// ---------- Full-page Short Post Editor (Like Article Editor) ----------
function urlEditShortPost(id) {
  var url = new URL(window.location.href);
  url.searchParams.set('edit_short_post', id);
  url.searchParams.delete('new_short_post');
  window.history.pushState({}, '', url);
  loadShortPostEditor(id);
}

function urlNewShortPost() {
  var url = new URL(window.location.href);
  url.searchParams.set('new_short_post', '1');
  url.searchParams.delete('edit_short_post');
  window.history.pushState({}, '', url);
  loadShortPostEditor(null);
}

function loadShortPostEditor(id) {
  closeMobileMenu();
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  var menu = document.getElementById('menu-shortposts');
  if (menu) menu.classList.add('active');

  document.getElementById('page-title').textContent = id ? 'تعديل المنشور القصير #' + id : 'إضافة منشور قصير جديد (Short Post)';
  document.getElementById('page-subtitle').textContent = 'محرر المنشورات السريعة الاحترافي | محرر مرئي، كود HTML، صورة الغلاف، وبيانات Google SEO';

  var area = document.getElementById('main-content');
  area.innerHTML = `
    <form id="form-shortpost-full" class="post-editor-layout" onsubmit="saveShortPostFull(event)" enctype="multipart/form-data">
      <!-- Main Content Column -->
      <div class="post-editor-main">
        <div class="card editor-main-card">
          <input type="hidden" name="id" id="sp-post-id" value="${id || ''}">

          <!-- Slug Code Notice / Generator -->
          <div style="background:var(--brand-50); border:1px solid var(--brand-100); border-radius:10px; padding:12px 16px; margin-bottom:18px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <i data-lucide="link-2" style="color:var(--brand-600);"></i>
              <span style="font-weight:700; font-size:13px;">رمز الرابط الفريد (Slug Code):</span>
              <input type="text" name="slug" id="sp-slug" style="background:var(--surface); border:1px solid var(--brand-100); font-family:monospace; font-weight:700; color:var(--brand-600); font-size:14px; padding:4px 10px; border-radius:6px; letter-spacing:1px; width:140px; text-align:center; direction:ltr;" required placeholder="AjNz6hWej0" oninput="updateGooglePreview()">
              <button type="button" class="btn btn-secondary" onclick="generateNewShortPostSlug()" title="توليد رمز جديد" style="padding:4px 10px; font-size:12px;">
                <i data-lucide="refresh-cw"></i> كود جديد
              </button>
            </div>
            <div style="font-size:12.5px; color:var(--brand-600); font-weight:600; direction:ltr;">
              URL: https://9sty.com/<span id="sp-slug-preview">AjNz6hWej0</span>
            </div>
          </div>

          <!-- Title (اختياري) -->
          <div class="form-group title-form-group">
            <label for="sp-title" class="editor-label">عنوان المنشور <span class="label-hint">(اختياري — إذا تركته فارغاً سيتم اشتقاقه تلقائياً من المحتوى)</span></label>
            <input type="text" name="title" id="sp-title" class="form-control post-title-input" placeholder="اكتب عنواناً اختيارياً للمنشور..." autocomplete="off" oninput="handleShortPostTitleChange()">
          </div>

          <!-- Excerpt -->
          <div class="form-group excerpt-form-group">
            <label for="sp-excerpt" class="editor-label">مقتطف موجز <span class="label-hint">يظهر في الملخص ونتائج البحث ومواقع التواصل (اختياري)</span></label>
            <textarea name="excerpt" id="sp-excerpt" class="form-control post-excerpt-input" rows="2" placeholder="مقتطف قصير يلخص فكرة المنشور للقارئ..." oninput="updateGooglePreview()"></textarea>
          </div>

          <!-- Rich Content Editor with Visual & HTML Code Toggle -->
          <div class="form-group content-form-group">
            <div class="content-header-row">
              <label class="editor-label">محتوى وتفاصيل المنشور (Description / HTML) <span class="required-star">*</span></label>
              <div class="editor-mode-toggle">
                <button type="button" class="mode-btn active" id="btn-sp-mode-visual" onclick="setShortEditorMode('visual')">
                  <i data-lucide="edit-3"></i> محرر مرئي
                </button>
                <button type="button" class="mode-btn" id="btn-sp-mode-code" onclick="setShortEditorMode('code')">
                  <i data-lucide="code"></i> كود HTML
                </button>
              </div>
            </div>

            <div class="rich-editor-container" id="rich-editor-box">
              <!-- Professional Rich Editor Toolbar -->
              <div class="editor-toolbar" id="sp-editor-toolbar" role="toolbar" aria-label="أدوات تنسيق المحتوى">
                <!-- Headings Group: H1, H2, H3, P -->
                <div class="toolbar-group">
                  <button type="button" class="tb-btn tb-heading" data-command="formatBlock" data-value="H1" title="عنوان رئيسي (H1)"><span>H1</span></button>
                  <button type="button" class="tb-btn tb-heading" data-command="formatBlock" data-value="H2" title="عنوان فرعي (H2)"><span>H2</span></button>
                  <button type="button" class="tb-btn tb-heading" data-command="formatBlock" data-value="H3" title="عنوان ثانوي (H3)"><span>H3</span></button>
                  <button type="button" class="tb-btn tb-heading" data-command="formatBlock" data-value="P" title="فقرة عادية (P)"><span>P</span></button>
                </div>

                <div class="toolbar-divider"></div>

                <!-- Text Formatting: Bold, Italic, Underline, Strikethrough -->
                <div class="toolbar-group">
                  <button type="button" class="tb-btn" data-command="bold" title="خط عريض (Bold)"><i data-lucide="bold"></i></button>
                  <button type="button" class="tb-btn" data-command="italic" title="خط مائل (Italic)"><i data-lucide="italic"></i></button>
                  <button type="button" class="tb-btn" data-command="underline" title="تسطير (Underline)"><i data-lucide="underline"></i></button>
                  <button type="button" class="tb-btn" data-command="strikeThrough" title="يتوسطه خط (Strikethrough)"><i data-lucide="strikethrough"></i></button>
                </div>

                <div class="toolbar-divider"></div>

                <!-- Structure: Lists, Quote, HR -->
                <div class="toolbar-group">
                  <button type="button" class="tb-btn" data-command="insertUnorderedList" title="قائمة نقطية (List •)"><i data-lucide="list"></i></button>
                  <button type="button" class="tb-btn" data-command="insertOrderedList" title="قائمة مرقمة (Numbered List 1.)"><i data-lucide="list-ordered"></i></button>
                  <button type="button" class="tb-btn" data-command="formatBlock" data-value="BLOCKQUOTE" title="اقتباس متميز (Quote)"><i data-lucide="quote"></i></button>
                  <button type="button" class="tb-btn" data-command="insertHorizontalRule" title="خط فاصل أفقي (Divider)"><i data-lucide="minus"></i></button>
                </div>

                <div class="toolbar-divider"></div>

                <!-- Links: A Tag, Unlink -->
                <div class="toolbar-group">
                  <button type="button" class="tb-btn" id="tb-btn-sp-link" onclick="promptInsertShortLink()" title="إدراج رابط (Link a)"><i data-lucide="link"></i></button>
                  <button type="button" class="tb-btn" data-command="unlink" title="إزالة الرابط (Unlink)"><i data-lucide="unlink"></i></button>
                </div>

                <div class="toolbar-divider"></div>

                <!-- Alignment -->
                <div class="toolbar-group">
                  <button type="button" class="tb-btn" data-command="justifyRight" title="محاذاة لليمين"><i data-lucide="align-right"></i></button>
                  <button type="button" class="tb-btn" data-command="justifyCenter" title="توسيط النص"><i data-lucide="align-center"></i></button>
                  <button type="button" class="tb-btn" data-command="justifyLeft" title="محاذاة لليسار"><i data-lucide="align-left"></i></button>
                </div>

                <div class="toolbar-divider"></div>

                <!-- Utility: Clear formatting, Undo, Redo -->
                <div class="toolbar-group">
                  <button type="button" class="tb-btn" data-command="removeFormat" title="مسح التنسيق"><i data-lucide="eraser"></i></button>
                  <button type="button" class="tb-btn" data-command="undo" title="تراجع (Ctrl+Z)"><i data-lucide="undo"></i></button>
                  <button type="button" class="tb-btn" data-command="redo" title="إعادة (Ctrl+Y)"><i data-lucide="redo"></i></button>
                </div>
              </div>

              <!-- Visual Contenteditable Editor Surface -->
              <div id="sp-editor-surface" class="editor-surface" contenteditable="true" spellcheck="false" placeholder="اكتب تفاصيل ومحتوى المنشور القصير هنا بكل احترافية..."></div>

              <!-- Textarea Code (auto-synced) -->
              <textarea name="content" id="sp-content" class="editor-textarea-code" style="display:none;" required placeholder="اكتب كود HTML أو النص هنا..."></textarea>

              <!-- Status & Counters Footer -->
              <div class="editor-footer">
                <div class="editor-stats">
                  <span id="stat-sp-words"><i data-lucide="file-text"></i> <strong>0</strong> كلمة</span>
                  <span class="stat-sep">•</span>
                  <span id="stat-sp-chars"><strong>0</strong> حرف</span>
                  <span class="stat-sep">•</span>
                  <span id="stat-sp-reading-time"><i data-lucide="clock"></i> قراءة في <strong>1</strong> دقيقة</span>
                </div>
                <div class="editor-tip">
                  <span>يدعم كود <strong>HTML</strong> كاملاً مع المعاينة الحية والتنسيق</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar Column -->
      <div class="post-editor-sidebar">
        <!-- Publishing Settings Card -->
        <div class="card meta-card">
          <div class="card-header-clean">
            <div class="card-title-wrap">
              <i data-lucide="send" style="color:var(--brand-600);"></i>
              <h3>إعدادات النشر</h3>
            </div>
            <span class="badge published" id="sp-status-badge">منشور للعموم</span>
          </div>

          <div class="form-group" style="margin-top:14px;">
            <label for="sp-status" class="editor-label">حالة المنشور</label>
            <select name="status" id="sp-status" form="form-shortpost-full" class="form-control" onchange="updateShortStatusBadge(this.value)">
              <option value="published">منشور للعموم (Published)</option>
              <option value="draft">مسودة خاصة (Draft)</option>
              <option value="archived">مؤرشف (Archived)</option>
            </select>
          </div>

          <div class="form-group">
            <label for="sp-category" class="editor-label">القسم أو التصنيف <span class="label-hint">(اختياري)</span></label>
            <select name="category_id" id="sp-category" form="form-shortpost-full" class="form-control">
              <option value="">— عام / بدون قسم —</option>
            </select>
          </div>

          <div class="meta-actions">
            <button type="submit" form="form-shortpost-full" class="btn btn-primary-publish">
              <i data-lucide="check-circle"></i> حفظ ونشر المنشور
            </button>
            <button type="button" class="btn btn-secondary btn-cancel-edit" onclick="cancelShortPostEdit()">
              <i data-lucide="x"></i> إلغاء ورجوع
            </button>
          </div>
        </div>

        <!-- Featured Image Card with LIVE NATURAL SIZE PREVIEW -->
        <div class="card meta-card image-meta-card">
          <div class="card-header-clean">
            <div class="card-title-wrap">
              <i data-lucide="image" style="color:#2563eb;"></i>
              <h3>صورة المنشور (Image)</h3>
            </div>
          </div>

          <div class="image-upload-wrapper">
            <!-- Live Preview Container - natural size, tall images display tall -->
            <div id="sp-image-preview" class="post-image-preview-box" onclick="document.getElementById('sp-image').click()" title="اضغط لاختيار صورة">
              <div class="empty-image-placeholder" id="empty-sp-image-placeholder">
                <i data-lucide="image-plus"></i>
                <span>لا توجد صورة محددة بعد</span>
                <small>اسحب الصورة هنا أو اضغط للاختيار (تظهر بأبعادها الأصلية كاملة)</small>
              </div>
            </div>

            <!-- Upload Controls -->
            <div class="image-input-control">
              <input type="file" name="image" id="sp-image" form="form-shortpost-full" class="hidden-file-input" accept="image/jpeg,image/png,image/webp,image/gif">
              <button type="button" class="btn-upload-trigger" onclick="document.getElementById('sp-image').click()">
                <i data-lucide="upload-cloud"></i> اختيار صورة من الجهاز
              </button>
              <button type="button" class="btn-danger-soft" id="btn-remove-sp-image" style="display:none;" onclick="clearShortPostImageSelection(event)">
                <i data-lucide="trash-2"></i> إزالة
              </button>
            </div>

            <div style="margin-top:10px;">
              <input type="text" name="image_url" id="sp-image-url" form="form-shortpost-full" class="form-control" style="font-size:12px;" placeholder="أو الصق رابط صورة خارجي (Direct URL)...">
            </div>

            <div class="image-upload-hint">
              <span>تظهر الصورة بأبعادها الطبيعية كاملة. وإذا أدخلت رابطاً خارجياً فسيتم تحميل الصورة ورفعها على السيرفر تلقائياً وتخزين اسمها فقط في قاعدة البيانات.</span>
            </div>
          </div>
        </div>

        <!-- Google SEO Metas Card -->
        <div class="card meta-card">
          <div class="card-header-clean">
            <div class="card-title-wrap">
              <i data-lucide="search" style="color:var(--brand-600);"></i>
              <h3>بيانات Google SEO</h3>
            </div>
          </div>

          <div style="margin-top:12px;">
            <div class="form-group">
              <label class="editor-label" style="font-size:12px;">Meta Title (عنوان محرك البحث)</label>
              <input type="text" name="meta_title" id="sp-meta-title" form="form-shortpost-full" class="form-control" placeholder="عنوان SEO في نتائج Google..." oninput="updateGooglePreview()">
            </div>
            <div class="form-group">
              <label class="editor-label" style="font-size:12px;">Meta Description (وصف محرك البحث)</label>
              <textarea name="meta_description" id="sp-meta-desc" form="form-shortpost-full" class="form-control" rows="2" placeholder="وصف موجز يظهر في Google..." oninput="updateGooglePreview()"></textarea>
              <small style="font-size:11px; color:var(--text-muted);"><span id="sp-meta-desc-len">0</span> / 155 حرف</small>
            </div>
            <div class="form-group">
              <label class="editor-label" style="font-size:12px;">Keywords (كلمات مفتاحية)</label>
              <input type="text" name="meta_keywords" id="sp-meta-keywords" form="form-shortpost-full" class="form-control" placeholder="أخبار، عاجل، المغرب...">
            </div>

            <!-- Google Live SERP Preview -->
            <div style="margin-top:10px;">
              <span style="font-size:12px; font-weight:700; color:var(--text); display:block; margin-bottom:4px;">
                <i data-lucide="eye" style="width:14px; height:14px; vertical-align:middle;"></i> معاينة الظهور في Google:
              </span>
              <div class="google-preview-card">
                <div class="google-preview-cite">
                  <span id="google-preview-url">https://9sty.com › AjNz6hWej0</span>
                </div>
                <div class="google-preview-title" id="google-preview-title">عنوان المنشور في Google</div>
                <div class="google-preview-snippet" id="google-preview-desc">وصف المنشور كما سيظهر في نتائج بحث Google للمستخدمين...</div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </form>`;

  // Populate categories
  populateShortPostCategories();

  // Initialize Rich editor & live image preview
  initShortRichEditor();
  initShortImagePreview();

  if (id) {
    fetch('api.php?action=get_short_post&id=' + id)
      .then(r => r.json())
      .then(d => {
        if (!d.success || !d.post) { showToast(d.message || 'تعذر تحميل المنشور', 'error'); return; }
        var p = d.post;
        document.getElementById('sp-title').value = p.title || '';
        document.getElementById('sp-slug').value = p.slug || '';
        document.getElementById('sp-slug-preview').textContent = p.slug || '';
        document.getElementById('sp-excerpt').value = p.excerpt || '';
        document.getElementById('sp-status').value = p.status || 'published';
        updateShortStatusBadge(p.status || 'published');
        populateShortPostCategories(p.category_id);

        var surface = document.getElementById('sp-editor-surface');
        var textarea = document.getElementById('sp-content');
        var rawContent = p.content || '';
        if (surface) surface.innerHTML = rawContent;
        if (textarea) textarea.value = rawContent;
        updateShortEditorStats();

        // Metas
        var metas = p.metas_parsed || {};
        document.getElementById('sp-meta-title').value = metas.meta_title || '';
        document.getElementById('sp-meta-desc').value = metas.meta_description || '';
        document.getElementById('sp-meta-keywords').value = metas.keywords || '';

        if (p.image) {
          var src = p.image.startsWith('http') ? p.image : 'https://cdn.9sty.com/uploads/' + p.image;
          renderShortImagePreview(src, p.image, 'الصورة الحالية', false);
        }
        updateGooglePreview();
        refreshIcons();
      });
  } else {
    // New short post: generate unique code like "AjNz6hWej0"
    generateNewShortPostSlug();
  }

  refreshIcons();
}

function setShortEditorMode(mode) {
  var surface = document.getElementById('sp-editor-surface');
  var textarea = document.getElementById('sp-content');
  var toolbar = document.getElementById('sp-editor-toolbar');
  var btnVisual = document.getElementById('btn-sp-mode-visual');
  var btnCode = document.getElementById('btn-sp-mode-code');

  if (!surface || !textarea) return;

  if (mode === 'code') {
    textarea.value = surface.innerHTML;
    surface.style.display = 'none';
    textarea.style.display = 'block';
    if (toolbar) toolbar.style.opacity = '0.5';
    if (toolbar) toolbar.style.pointerEvents = 'none';
    if (btnVisual) btnVisual.classList.remove('active');
    if (btnCode) btnCode.classList.add('active');
  } else {
    surface.innerHTML = textarea.value;
    textarea.style.display = 'none';
    surface.style.display = 'block';
    if (toolbar) toolbar.style.opacity = '1';
    if (toolbar) toolbar.style.pointerEvents = 'auto';
    if (btnCode) btnCode.classList.remove('active');
    if (btnVisual) btnVisual.classList.add('active');
    surface.focus();
  }
}

function initShortRichEditor() {
  var surface = document.getElementById('sp-editor-surface');
  var textarea = document.getElementById('sp-content');
  var toolbar = document.getElementById('sp-editor-toolbar');
  if (!surface || !textarea || !toolbar) return;

  toolbar.querySelectorAll('.tb-btn[data-command]').forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var cmd = btn.getAttribute('data-command');
      var val = btn.getAttribute('data-value') || null;

      surface.focus();
      if (cmd === 'formatBlock') {
        document.execCommand('formatBlock', false, '<' + val + '>');
      } else {
        document.execCommand(cmd, false, val);
      }

      textarea.value = surface.innerHTML;
      updateShortEditorStats();
      updateGooglePreview();
    });
  });

  surface.addEventListener('input', function () {
    textarea.value = surface.innerHTML;
    updateShortEditorStats();
    updateGooglePreview();
  });

  textarea.addEventListener('input', function () {
    surface.innerHTML = textarea.value;
    updateShortEditorStats();
    updateGooglePreview();
  });

  updateShortEditorStats();
}

function updateShortEditorStats() {
  var surface = document.getElementById('sp-editor-surface');
  if (!surface) return;
  var text = surface.innerText || surface.textContent || '';
  var cleanText = text.trim();
  var wordCount = cleanText ? cleanText.split(/\s+/).filter(Boolean).length : 0;
  var charCount = cleanText.length;
  var readingMinutes = Math.max(1, Math.ceil(wordCount / 180));

  var elWords = document.getElementById('stat-sp-words');
  var elChars = document.getElementById('stat-sp-chars');
  var elTime = document.getElementById('stat-sp-reading-time');

  if (elWords) elWords.innerHTML = `<i data-lucide="file-text"></i> <strong>${wordCount}</strong> كلمة`;
  if (elChars) elChars.innerHTML = `<strong>${charCount}</strong> حرف`;
  if (elTime) elTime.innerHTML = `<i data-lucide="clock"></i> قراءة في <strong>${readingMinutes}</strong> دقيقة`;
  refreshIcons();
}

function promptInsertShortLink() {
  var surface = document.getElementById('sp-editor-surface');
  if (!surface) return;
  var url = prompt('أدخل رابط الموقع (URL):', 'https://');
  if (!url || url.trim() === '' || url.trim() === 'https://') return;
  url = url.trim();

  surface.focus();
  document.execCommand('createLink', false, url);

  var textarea = document.getElementById('sp-content');
  if (textarea) textarea.value = surface.innerHTML;
  updateShortEditorStats();
  updateGooglePreview();
}

function updateShortStatusBadge(status) {
  var badge = document.getElementById('sp-status-badge');
  if (!badge) return;
  badge.className = 'badge ' + status;
  var map = { published: 'منشور للعموم', draft: 'مسودة خاصة', archived: 'مؤرشف' };
  badge.textContent = map[status] || status;
}

function initShortImagePreview() {
  var fileInput = document.getElementById('sp-image');
  var previewBox = document.getElementById('sp-image-preview');
  var urlInput = document.getElementById('sp-image-url');
  if (!fileInput || !previewBox) return;

  fileInput.addEventListener('change', function (e) {
    var file = e.target.files && e.target.files[0];
    if (!file) return;

    if (!file.type.match(/^image\//)) {
      showToast('يرجى اختيار ملف صورة صالح', 'error');
      fileInput.value = '';
      return;
    }

    var reader = new FileReader();
    reader.onload = function (evt) {
      var sizeKb = (file.size / 1024).toFixed(1) + ' KB';
      renderShortImagePreview(evt.target.result, file.name, sizeKb, true);
    };
    reader.readAsDataURL(file);
  });

  if (urlInput) {
    urlInput.addEventListener('input', function () {
      var val = urlInput.value.trim();
      if (val && (!fileInput.files || !fileInput.files.length)) {
        renderShortImagePreview(val, 'صورة خارجية', 'رابط مباشر', true);
      }
    });
  }

  previewBox.addEventListener('dragover', function (e) {
    e.preventDefault();
    previewBox.classList.add('drag-active');
  });

  previewBox.addEventListener('dragleave', function () {
    previewBox.classList.remove('drag-active');
  });

  previewBox.addEventListener('drop', function (e) {
    e.preventDefault();
    previewBox.classList.remove('drag-active');
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      fileInput.files = e.dataTransfer.files;
      var event = new Event('change');
      fileInput.dispatchEvent(event);
    }
  });
}

function renderShortImagePreview(src, name, metaLabel, isNew) {
  var previewBox = document.getElementById('sp-image-preview');
  var btnRemove = document.getElementById('btn-remove-sp-image');
  if (!previewBox) return;

  var badgeText = isNew ? 'معاينة الصورة الجديدة' : 'الصورة الحالية';
  var badgeIcon = isNew ? 'sparkles' : 'check';

  previewBox.innerHTML = `
    <div class="preview-img-container" style="width:100%; height:auto; display:flex; justify-content:center; align-items:center; position:relative;">
      <img src="${src}" class="main-preview-img" alt="${escapeHTML(name || 'غلاف المنشور')}" style="width:100%; height:auto; max-height:none; object-fit:contain; display:block; border-radius:10px;">
      <div class="preview-badge-overlay">
        <i data-lucide="${badgeIcon}"></i>
        <span>${badgeText} (${escapeHTML(metaLabel || '')})</span>
      </div>
    </div>
  `;

  if (btnRemove) btnRemove.style.display = 'inline-flex';
  refreshIcons();
}

function clearShortPostImageSelection(e) {
  if (e) e.stopPropagation();
  var fileInput = document.getElementById('sp-image');
  if (fileInput) fileInput.value = '';
  var urlInput = document.getElementById('sp-image-url');
  if (urlInput) urlInput.value = '';

  var previewBox = document.getElementById('sp-image-preview');
  if (previewBox) {
    previewBox.innerHTML = `
      <div class="empty-image-placeholder" id="empty-sp-image-placeholder">
        <i data-lucide="image-plus"></i>
        <span>لا توجد صورة محددة بعد</span>
        <small>اسحب الصورة هنا أو اضغط للاختيار (تظهر بأبعادها الأصلية كاملة)</small>
      </div>
    `;
  }

  var btnRemove = document.getElementById('btn-remove-sp-image');
  if (btnRemove) btnRemove.style.display = 'none';
  refreshIcons();
}

function saveShortPostFull(e) {
  e.preventDefault();
  var form = document.getElementById('form-shortpost-full');
  if (!form) return;

  var surface = document.getElementById('sp-editor-surface');
  var textarea = document.getElementById('sp-content');
  if (surface && textarea) {
    if (surface.style.display !== 'none') {
      textarea.value = surface.innerHTML;
    } else {
      surface.innerHTML = textarea.value;
    }
  }

  var contentVal = textarea ? textarea.value.trim() : '';
  if (!contentVal || contentVal === '<p><br></p>' || contentVal === '<br>') {
    showToast('المرجو كتابة محتوى أو وصف للمنشور (حقل إجباري)', 'error');
    return;
  }

  var btn = form.querySelector('button[type="submit"]');
  var original = btn ? btn.innerHTML : 'حفظ';
  if (btn) { btn.disabled = true; btn.innerHTML = 'جارٍ الحفظ...'; }

  var fd = new FormData(form);

  fetch('api.php?action=save_short_post', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showToast(d.message || 'تم حفظ المنشور القصير بنجاح');
        cancelShortPostEdit();
      } else {
        showToast(d.message || 'حدث خطأ أثناء الحفظ', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = original; refreshIcons(); }
      }
    })
    .catch(() => {
      showToast('خطأ في الاتصال بالسيرفر', 'error');
      if (btn) { btn.disabled = false; btn.innerHTML = original; refreshIcons(); }
    });
}

function cancelShortPostEdit() {
  var url = new URL(window.location.href);
  url.searchParams.delete('edit_short_post');
  url.searchParams.delete('new_short_post');
  window.history.pushState({}, '', url);
  loadSection('shortposts');
}

function generateNewShortPostSlug() {
  fetch('api.php?action=generate_short_post_code')
    .then(r => r.json())
    .then(d => {
      if (d.success && d.slug) {
        var slugInput = document.getElementById('sp-slug');
        if (slugInput) slugInput.value = d.slug;
        var preview = document.getElementById('sp-slug-preview');
        if (preview) preview.textContent = d.slug;
        var gSlug = document.getElementById('google-preview-slug');
        if (gSlug) gSlug.textContent = d.slug;
        updateGooglePreview();
      }
    })
    .catch(() => {
      // Local fallback generator (10 alphanumeric chars)
      var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
      var res = '';
      for (var i = 0; i < 10; i++) res += chars.charAt(Math.floor(Math.random() * chars.length));
      var slugInput = document.getElementById('sp-slug');
      if (slugInput) slugInput.value = res;
      var preview = document.getElementById('sp-slug-preview');
      if (preview) preview.textContent = res;
      var gSlug = document.getElementById('google-preview-slug');
      if (gSlug) gSlug.textContent = res;
      updateGooglePreview();
    });
}

function handleShortPostTitleChange() {
  updateGooglePreview();
}

function updateGooglePreview() {
  var slug = (document.getElementById('sp-slug') ? document.getElementById('sp-slug').value.trim() : '') || 'AjNz6hWej0';
  var slugEl = document.getElementById('sp-slug-preview');
  if (slugEl) slugEl.textContent = slug;

  var customMetaTitle = document.getElementById('sp-meta-title') ? document.getElementById('sp-meta-title').value.trim() : '';
  var customMetaDesc = document.getElementById('sp-meta-desc') ? document.getElementById('sp-meta-desc').value.trim() : '';
  var postTitle = document.getElementById('sp-title') ? document.getElementById('sp-title').value.trim() : '';
  
  var postDesc = '';
  var surface = document.getElementById('sp-editor-surface');
  var textarea = document.getElementById('sp-content');
  if (surface && surface.innerText) {
    postDesc = surface.innerText.trim();
  } else if (textarea && textarea.value) {
    postDesc = textarea.value.replace(/<[^>]*>/g, '').trim();
  }

  // SERP Title
  var displayTitle = customMetaTitle;
  if (!displayTitle) {
    displayTitle = postTitle || (postDesc.substring(0, 60) || 'عنوان المنشور');
    displayTitle += ' | أخبار المغرب';
  }

  // SERP Desc
  var displayDesc = customMetaDesc || (postDesc.substring(0, 155) || 'وصف المنشور كما سيظهر في نتائج بحث Google للمستخدمين...');

  var gSlug = document.getElementById('google-preview-slug');
  var gTitle = document.getElementById('google-preview-title');
  var gDesc = document.getElementById('google-preview-desc');
  var gLen = document.getElementById('sp-meta-desc-len');

  if (gSlug) gSlug.textContent = slug;
  if (gTitle) gTitle.textContent = displayTitle;
  if (gDesc) gDesc.textContent = displayDesc;
  if (gLen) gLen.textContent = (customMetaDesc || displayDesc).length;
}

function toggleSeoAccordion() {
  var body = document.getElementById('sp-seo-body');
  var chevron = document.getElementById('sp-seo-chevron');
  if (!body) return;
  if (body.style.display === 'none') {
    body.style.display = 'block';
    if (chevron) chevron.style.transform = 'rotate(0deg)';
  } else {
    body.style.display = 'none';
    if (chevron) chevron.style.transform = 'rotate(180deg)';
  }
}

function previewShortPostImage(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var box = document.getElementById('sp-image-preview');
      if (box) box.innerHTML = `<img src="${e.target.result}" style="max-height:160px; max-width:100%; border-radius:8px; object-fit:contain;">`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function previewShortPostImageUrl(url) {
  var trimmed = (url || '').trim();
  var box = document.getElementById('sp-image-preview');
  if (box && trimmed) {
    box.innerHTML = `<img src="${trimmed}" onerror="this.onerror=null; this.src='https://cdn.9sty.com/uploads/no-images-news.webp';" style="max-height:160px; max-width:100%; border-radius:8px; object-fit:contain;">`;
  }
}

function saveShortPost(e) {
  e.preventDefault();
  var form = document.getElementById('form-shortpost');
  if (!form) return;

  var desc = document.getElementById('sp-description').value.trim();
  if (!desc) {
    showToast('المرجو كتابة وصف المنشور (حقل إجباري)', 'error');
    return;
  }

  var btn = document.getElementById('btn-save-shortpost');
  var origText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = 'جارٍ الحفظ...';

  var fd = new FormData(form);

  fetch('api.php?action=save_short_post', {
    method: 'POST',
    body: fd
  })
    .then(r => r.json())
    .then(d => {
      btn.disabled = false;
      btn.innerHTML = origText;
      refreshIcons();

      if (d.success) {
        showToast(d.message || 'تم حفظ المنشور بنجاح');
        closeModal('shortpost');
        renderShortPosts(document.getElementById('main-content'), shortPostsState.page);
      } else {
        showToast(d.message || 'حدث خطأ أثناء الحفظ', 'error');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = origText;
      refreshIcons();
      showToast('تعذر الاتصال بالسيرفر', 'error');
    });
}

function deleteShortPost(id) {
  if (!confirm('هل أنت متأكد من حذف هذا المنشور القصير؟')) return;

  var fd = new FormData();
  fd.append('id', id);

  fetch('api.php?action=delete_short_post', {
    method: 'POST',
    body: fd
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showToast(d.message || 'تم حذف المنشور');
        renderShortPosts(document.getElementById('main-content'), shortPostsState.page);
      } else {
        showToast(d.message || 'حدث خطأ أثناء الحذف', 'error');
      }
    })
    .catch(() => {
      showToast('تعذر الاتصال بالسيرفر', 'error');
    });
}

function copyShortPostLink(slug) {
  var fullUrl = 'https://9sty.com/' + encodeURIComponent(slug);
  navigator.clipboard.writeText(fullUrl)
    .then(() => showToast('تم نسخ رابط المنشور: ' + fullUrl))
    .catch(() => showToast('تعذر النسخ إلى الحافظة', 'error'));
}

// ---------- Categories ----------
function renderCategories(container) {
  fetch('api.php?action=list_categories')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      var rows = '';
      data.categories.forEach(c => {
        var headerBadge = c.show_in_header != 0
          ? '<span class="badge active">ظاهر</span>'
          : '<span class="badge inactive">مخفي</span>';
        rows += `
              <tr>
                <td style="color:var(--text-muted); font-weight:600;">#${c.id}</td>
                <td style="font-weight:600;">${escapeHTML(c.name)}</td>
                <td><code style="color:var(--text-muted); font-size:12px;">/${escapeHTML(c.slug)}</code></td>
                <td>${headerBadge}</td>
                <td><span class="badge ${c.status}">${c.status === 'active' ? 'نشط' : 'معطل'}</span></td>
                <td>
                  <div class="table-actions">
                    <button class="act-btn" onclick="editCategoryModal(${c.id}, '${escapeJS(c.name)}', '${escapeJS(c.status)}', ${c.show_in_header != 0 ? 1 : 0}, '${escapeJS(c.meta_title)}', '${escapeJS(c.meta_description)}')" title="تعديل"><i data-lucide="pencil"></i></button>
                    <button class="act-btn del" onclick="deleteCategory(${c.id})" title="حذف"><i data-lucide="trash-2"></i></button>
                  </div>
                </td>
              </tr>`;
      });

      container.innerHTML = `
            <div class="card">
              <div class="card-header">
                <h2>الأقسام والتصنيفات</h2>
                <button class="btn" onclick="showAddCategoryModal()"><i data-lucide="plus"></i> قسم جديد</button>
              </div>
              <div class="table-responsive">
                <table>
                  <thead><tr><th>ID</th><th>الاسم</th><th>Slug</th><th>الهيدر</th><th>الحالة</th><th>إجراءات</th></tr></thead>
                  <tbody>${rows || `<tr><td colspan="6"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد أقسام</p></div></td></tr>`}</tbody>
                </table>
              </div>
            </div>`;
      refreshIcons();
    });
}

// ---------- AdSense ----------
function renderAdsense(container) {
  fetch('api.php?action=list_adsense')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      var rows = '';
      data.adsense.forEach(ad => {
        var isCustomCode = ad.code ? '<span class="badge sponsored">كود مخصص</span>' : '<span style="color:var(--text-faint);">—</span>';
        rows += `
              <tr>
                <td style="font-weight:600;">${escapeHTML(ad.slot_name)}</td>
                <td><code style="font-size:12px; color:var(--text-muted);">${escapeHTML(ad.ad_client || '—')}</code></td>
                <td><code style="font-size:12px; color:var(--text-muted);">${escapeHTML(ad.ad_slot || '—')}</code></td>
                <td><code style="font-size:12px;">${escapeHTML(ad.ad_format || '—')}</code></td>
                <td>${isCustomCode}</td>
                <td><span class="badge ${ad.is_active ? 'active' : 'inactive'}">${ad.is_active ? 'نشط' : 'معطل'}</span></td>
                <td>
                  <div class="table-actions">
                    <button class="act-btn" onclick="editAdsenseModal(${ad.id}, '${escapeJS(ad.slot_name)}', '${escapeJS(ad.ad_client || '')}', '${escapeJS(ad.ad_slot || '')}', '${escapeJS(ad.ad_format || '')}', ${ad.is_active}, '${escapeJS(ad.code || '')}')" title="تعديل"><i data-lucide="pencil"></i></button>
                    <button class="act-btn del" onclick="deleteAdsense(${ad.id})" title="حذف"><i data-lucide="trash-2"></i></button>
                  </div>
                </td>
              </tr>`;
      });

      container.innerHTML = `
            <div class="card">
              <div class="card-header">
                <h2>مساحات AdSense</h2>
                <button class="btn" onclick="showAddAdsenseModal()"><i data-lucide="plus"></i> مساحة جديدة</button>
              </div>
              <div class="table-responsive">
                <table>
                  <thead><tr><th>Slot</th><th>Client</th><th>Ad Slot</th><th>Format</th><th>كود مخصص</th><th>الحالة</th><th>إجراءات</th></tr></thead>
                  <tbody>${rows || `<tr><td colspan="7"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد مساحات</p></div></td></tr>`}</tbody>
                </table>
              </div>
            </div>`;
      refreshIcons();
    });
}

// ---------- Banners ----------
function renderBanners(container) {
  container.innerHTML = `<div class="card"><div class="loader-shimmer" style="height:80px;"></div></div>`;

  fetch('api.php?action=list_banners')
    .then(r => r.json())
    .then(data => {
      if (!data.success) { container.innerHTML = `<div class="error-msg">${escapeHTML(data.message)}</div>`; return; }
      var stats = data.stats || { total_clicks: 0, total_unique_ips: 0, top_banner: '—' };
      var rows = '';

      (data.banners || []).forEach(b => {
        var img = b.image ? (b.image.startsWith('http') ? b.image : `https://cdn.9sty.com/uploads/${b.image}`) : '';
        var imgTag = img ? `<img class="thumb-preview" src="${img}" style="width:60px;height:44px;object-fit:cover;">` : '<span style="color:var(--text-faint);font-size:11px;">—</span>';
        var price = b.price ? `<strong style="color:var(--brand-600);">${b.price} MAD</strong>${b.promo_price ? `<div style="text-decoration:line-through;color:var(--text-faint);font-size:10.5px;">${b.promo_price}</div>` : ''}` : '<span style="color:var(--text-faint);font-size:11px;">—</span>';

        var clicksBadge = `<span style="display:inline-flex;align-items:center;gap:4px;font-weight:800;color:#2563eb;font-size:15px;"><i data-lucide="mouse-pointer-click" style="width:14px;height:14px;"></i> ${(b.total_clicks || 0).toLocaleString('en')}</span>`;
        var c7 = `<span style="color:#059669;font-weight:700;">${(b.clicks_7d || 0).toLocaleString('en')}</span>`;
        var c30 = `<span style="color:#d97706;font-weight:700;">${(b.clicks_30d || 0).toLocaleString('en')}</span>`;
        var ips = `<span style="color:var(--text-muted);font-weight:600;">${(b.unique_ips || 0).toLocaleString('en')}</span>`;
        var lastClick = b.last_click
          ? `<span style="font-size:11px;color:var(--text-faint);">${escapeHTML((b.last_click || '').substring(0, 16))}</span>`
          : `<span style="font-size:11px;color:var(--text-faint);">—</span>`;

        var slugBadge = b.slug ? `<code style="font-size:10.5px;background:var(--surface-2);padding:2px 6px;border-radius:4px;color:var(--text-muted);direction:ltr;display:inline-block;">${escapeHTML(b.slug)}</code>` : '';

        rows += `
          <tr>
            <td style="color:var(--text-muted);font-weight:600;">#${b.id}</td>
            <td>${imgTag}</td>
            <td>
              <div style="font-weight:700;">${escapeHTML(b.name)}</div>
              ${slugBadge}
            </td>
            <td>${price}</td>
            <td style="text-align:center;">${clicksBadge}<div style="font-size:10px;color:var(--text-faint);margin-top:2px;">إجمالي</div></td>
            <td style="text-align:center;">${c7}<div style="font-size:10px;color:var(--text-faint);">7 أيام</div></td>
            <td style="text-align:center;">${c30}<div style="font-size:10px;color:var(--text-faint);">30 يوم</div></td>
            <td style="text-align:center;">${ips}<div style="font-size:10px;color:var(--text-faint);">IPs</div></td>
            <td>${lastClick}</td>
            <td><span class="badge ${b.is_active ? 'active' : 'inactive'}">${b.is_active ? 'نشط' : 'معطل'}</span></td>
            <td>
              <div class="table-actions">
                <button class="act-btn" onclick="openBannerClicks('${escapeJS(b.slug)}', '${escapeJS(b.name)}')" title="تفاصيل النقرات"><i data-lucide="bar-chart-3"></i></button>
                <button class="act-btn" onclick="editBannerModal(${b.id}, '${escapeJS(b.name)}', '${escapeJS(b.url)}', '${escapeJS(b.price || '')}', '${escapeJS(b.promo_price || '')}', ${b.is_active}, '${escapeJS(b.image)}')" title="تعديل"><i data-lucide="pencil"></i></button>
                <button class="act-btn del" onclick="deleteBanner(${b.id})" title="حذف"><i data-lucide="trash-2"></i></button>
              </div>
            </td>
          </tr>`;
      });

      container.innerHTML = `
        <div class="stats-grid" style="margin-bottom:20px;">
          <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#2563eb;"><i data-lucide="mouse-pointer-click"></i></div>
            <div class="stat-info"><span class="stat-label">إجمالي النقرات</span><span class="stat-value">${(stats.total_clicks || 0).toLocaleString('en')}</span></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background:#ecfdf5;color:#059669;"><i data-lucide="users"></i></div>
            <div class="stat-info"><span class="stat-label">عناوين IP فريدة</span><span class="stat-value">${(stats.total_unique_ips || 0).toLocaleString('en')}</span></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i data-lucide="star"></i></div>
            <div class="stat-info"><span class="stat-label">البنر الأكثر نقراً</span><span class="stat-value" style="font-size:16px;">${escapeHTML((stats.top_banner || '—').substring(0, 20))}</span></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h2>البنرات المخصصة <span style="color:var(--text-muted);font-size:13px;">(${(data.banners || []).length})</span></h2>
            <button class="btn" onclick="showAddBannerModal()"><i data-lucide="plus"></i> بنر جديد</button>
          </div>
          <div class="table-responsive">
            <table>
              <thead><tr>
                <th>ID</th><th>معاينة</th><th>الاسم</th><th>السعر</th>
                <th>إجمالي</th><th>7 أيام</th><th>30 يوم</th><th>IPs</th><th>آخر نقرة</th><th>الحالة</th><th>إجراءات</th>
              </tr></thead>
              <tbody>${rows || `<tr><td colspan="11"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد بنرات</p></div></td></tr>`}</tbody>
            </table>
          </div>
        </div>`;

      refreshIcons();
    })
    .catch(() => { container.innerHTML = `<div class="error-msg">تعذّر الاتصال</div>`; });
}
// ---------- Users ----------
function renderUsers(container) {
  fetch('api.php?action=list_users')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      var rows = '';
      data.users.forEach(u => {
        rows += `
              <tr>
                <td style="color:var(--text-muted); font-weight:600;">#${u.id}</td>
                <td style="font-weight:600;">${escapeHTML(u.username)}</td>
                <td style="color:var(--text-muted);">${escapeHTML(u.email)}</td>
                <td><span class="badge ${u.role == 1 ? 'active' : 'draft'}">${u.role == 1 ? 'مدير' : 'محرر'}</span></td>
                <td style="color:var(--text-muted); font-size:12px;">${escapeHTML(u.created_at || '')}</td>
                <td>
                  <div class="table-actions">
                    <button class="act-btn" onclick="editUserModal(${u.id}, '${escapeJS(u.username)}', '${escapeJS(u.email)}', ${u.role})" title="تعديل"><i data-lucide="pencil"></i></button>
                    <button class="act-btn del" onclick="deleteUser(${u.id})" title="حذف"><i data-lucide="trash-2"></i></button>
                  </div>
                </td>
              </tr>`;
      });

      container.innerHTML = `
            <div class="card">
              <div class="card-header">
                <h2>المستخدمون</h2>
                <button class="btn" onclick="showAddUserModal()"><i data-lucide="user-plus"></i> مستخدم جديد</button>
              </div>
              <div class="table-responsive">
                <table>
                  <thead><tr><th>ID</th><th>المستخدم</th><th>البريد</th><th>الصلاحية</th><th>الإنشاء</th><th>إجراءات</th></tr></thead>
                  <tbody>${rows || `<tr><td colspan="6"><div class="empty-state"><i data-lucide="inbox"></i><p>لا يوجد مستخدمون</p></div></td></tr>`}</tbody>
                </table>
              </div>
            </div>`;
      refreshIcons();
    });
}

// ---------- Comments ----------
function renderComments(container, page = 1) {
  container.innerHTML = `<div class="card"><div class="loader-shimmer" style="width:40%; margin-bottom:16px;"></div><div class="loader-shimmer"></div></div>`;

  fetch(`api.php?action=list_comments&page=${page}&per_page=20`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) { container.innerHTML = `<div class="error-msg">${escapeHTML(data.message)}</div>`; return; }
      let rows = '';

      data.comments.forEach(c => {
        const hidden = c.status === 'hidden';
        const statusBadge = hidden ? '<span class="badge inactive">مخفي</span>' : '<span class="badge active">ظاهر</span>';
        const toggleIcon = hidden ? 'eye' : 'eye-off';
        const toggleTitle = hidden ? 'إظهار' : 'إخفاء';
        const postTitle = c.post_title
          ? `<span style="font-weight:600;">${escapeHTML(c.post_title)}</span>`
          : `<span style="color:var(--text-faint); font-size:12px;">(مقال محذوف)</span>`;
        const full = escapeHTML(c.comment || '');
        const snippet = full.length > 80 ? full.substring(0, 80) + '…' : full;

        rows += `
          <tr>
            <td style="color:var(--text-muted); font-weight:600;">#${c.id}</td>
            <td>
              <div style="font-weight:600;">${escapeHTML(c.name)}</div>
              <div style="font-size:11px; color:var(--text-faint); direction:ltr; text-align:right;">${escapeHTML(c.email || '')}</div>
            </td>
            <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${postTitle}</td>
            <td style="max-width:260px; font-size:13px;" title="${full}">${snippet}</td>
            <td>${statusBadge}</td>
            <td style="color:var(--text-muted); font-size:12px;">${escapeHTML(c.created_at || '')}</td>
            <td>
              <div class="table-actions">
                <button class="act-btn" onclick="editCommentModal(${c.id})" title="تعديل"><i data-lucide="pencil"></i></button>
                <button class="act-btn warn" onclick="toggleHideComment(${c.id})" title="${toggleTitle}"><i data-lucide="${toggleIcon}"></i></button>
                <button class="act-btn del" onclick="deleteComment(${c.id})" title="حذف"><i data-lucide="trash-2"></i></button>
              </div>
            </td>
          </tr>`;
      });

      container.innerHTML = `
        <div class="card">
          <div class="card-header">
            <h2>تعليقات القراء <span style="color:var(--text-muted); font-size:13px; font-weight:600;">(${data.pagination.total})</span></h2>
          </div>
          <div class="table-responsive">
            <table>
              <thead><tr><th>ID</th><th>الكاتب</th><th>المقال</th><th>التعليق</th><th>الحالة</th><th>التاريخ</th><th>إجراءات</th></tr></thead>
              <tbody>${rows || `<tr><td colspan="7"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد تعليقات</p></div></td></tr>`}</tbody>
            </table>
          </div>
          ${renderPagination(data.pagination)}
        </div>`;

      refreshIcons();
      bindPagination(container, p => renderComments(container, p));
    })
    .catch(() => { container.innerHTML = `<div class="error-msg">تعذّر جلب التعليقات</div>`; });
}
// ---------- ShortLinks ----------
function renderShortLinks(container, page = 1) {
  container.innerHTML = `<div class="card"><div class="loader-shimmer" style="width:40%; margin-bottom:16px;"></div><div class="loader-shimmer"></div></div>`;

  fetch(`api.php?action=list_short_links&page=${page}&per_page=20`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) { container.innerHTML = `<div class="error-msg">${escapeHTML(data.message)}</div>`; return; }
      let rows = '';

      data.short_links.forEach(l => {
        const shortUrl = `https://9sty.com/s/${l.short_code}`;
        rows += `
          <tr>
            <td style="color:var(--text-muted); font-weight:600;">#${l.id}</td>
            <td><code style="background:var(--brand-50); color:var(--brand-600); padding:4px 10px; border-radius:6px; font-weight:700; font-size:12px;">${escapeHTML(l.short_code)}</code></td>
            <td style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
              <a href="${escapeHTML(l.destination_url)}" target="_blank" style="color:var(--info); font-weight:600; font-size:12.5px;">${escapeHTML(l.destination_url)}</a>
            </td>
            <td>
              <div style="display:flex; align-items:center; gap:6px;">
                <input type="text" readonly value="${shortUrl}" id="sl-url-${l.id}" style="font-size:11.5px; padding:5px 8px; border:1px solid var(--border); border-radius:6px; width:200px; background:var(--surface-2); color:var(--text-muted);">
                <button class="act-btn" onclick="copyShortLinkText('sl-url-${l.id}')" title="نسخ"><i data-lucide="copy"></i></button>
              </div>
            </td>
            <td style="font-weight:700; color:var(--brand-600);">${l.clicks}</td>
            <td style="color:var(--text-muted); font-size:12px;">${escapeHTML(l.created_at || '')}</td>
            <td>
              <div class="table-actions">
                <button class="act-btn" onclick="editShortLinkModal(${l.id}, '${escapeJS(l.short_code)}', '${escapeJS(l.destination_url)}')" title="تعديل"><i data-lucide="pencil"></i></button>
                <button class="act-btn del" onclick="deleteShortLink(${l.id})" title="حذف"><i data-lucide="trash-2"></i></button>
              </div>
            </td>
          </tr>`;
      });

      container.innerHTML = `
        <div class="card">
          <div class="card-header">
            <h2>الروابط المختصرة <span style="color:var(--text-muted); font-size:13px; font-weight:600;">(${data.pagination.total})</span></h2>
            <button class="btn" onclick="showAddShortLinkModal()"><i data-lucide="plus"></i> رابط جديد</button>
          </div>
          <div class="table-responsive">
            <table>
              <thead><tr><th>ID</th><th>الكود</th><th>الرابط</th><th>الرابط المختصر</th><th>النقرات</th><th>الإنشاء</th><th>إجراءات</th></tr></thead>
              <tbody>${rows || `<tr><td colspan="7"><div class="empty-state"><i data-lucide="inbox"></i><p>لا توجد روابط</p></div></td></tr>`}</tbody>
            </table>
          </div>
          ${renderPagination(data.pagination)}
        </div>`;

      refreshIcons();
      bindPagination(container, p => renderShortLinks(container, p));
    })
    .catch(() => { container.innerHTML = `<div class="error-msg">تعذّر الجلب</div>`; });
}
// ---------- Settings ----------
function renderSettings(container) {
  container.innerHTML = '<div class="card"><div class="loader-shimmer" style="height:120px;"></div></div>';

  Promise.all([
    fetch('api.php?action=get_settings').then(r => r.json()),
    fetch('api.php?action=get_color_settings').then(r => r.json())
  ]).then(([data, colorsData]) => {
    if (!data.success) { container.innerHTML = `<div class="error-msg">${escapeHTML(data.message)}</div>`; return; }
    const s = data.settings;
    const colors = (colorsData && colorsData.success && colorsData.colors) ? colorsData.colors : {};

    container.innerHTML = `
      <div class="card">
        <div class="card-header"><h2>إعدادات الموقع والتتبع والألوان</h2></div>
        <form id="form-settings" onsubmit="saveSettings(event)">

          <!-- 🎨 COLORS -->
          <div style="background:var(--brand-50); border:1px solid var(--brand-100); border-radius:14px; padding:20px; margin-bottom:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
              <h3 style="font-size:15px; font-weight:800; color:var(--brand-600); margin:0; display:flex; align-items:center; gap:8px;">
                <i data-lucide="palette"></i> الألوان والمظهر
              </h3>
              <button type="button" onclick="resetThemeColors()" class="btn btn-secondary" style="padding:6px 14px; font-size:12.5px;">
                <i data-lucide="rotate-ccw"></i> استرجاع الافتراضي
              </button>
            </div>
            <p style="font-size:12.5px; color:var(--text-muted); margin:0 0 16px; line-height:1.6;">
              غيّر ألوان اللوحة مباشرة — تُطبَّق فوراً عند التغيير. محفوظة في جدول <code>ja_settings</code>.
            </p>
            <div id="color-pickers-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(210px, 1fr)); gap:12px;">
              ${renderColorSection(colors)}
            </div>
          </div>

          <!-- ⚙️ EXISTING SETTINGS (SEO, tracking, etc) -->
          <h3 style="font-size:14px; font-weight:700; margin-bottom:14px; color:var(--brand-600); display:flex; align-items:center; gap:8px;">
            <i data-lucide="globe"></i> الهوية و SEO
          </h3>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div class="form-group">
              <label>اسم الموقع</label>
              <input type="text" name="site_title" class="form-control" value="${escapeHTML(s.site_title || '')}" placeholder="أخبار المغرب">
            </div>
            <div class="form-group">
              <label>رابط الموقع</label>
              <input type="url" name="site_url" class="form-control" value="${escapeHTML(s.site_url || '')}" placeholder="https://9sty.com">
            </div>
            <div class="form-group" style="grid-column:1/-1;">
              <label>وصف الموقع (SEO)</label>
              <textarea name="site_description" class="form-control" rows="2">${escapeHTML(s.site_description || '')}</textarea>
            </div>
            <div class="form-group">
              <label>شعار الموقع (URL)</label>
              <input type="url" name="site_logo" class="form-control" value="${escapeHTML(s.site_logo || '')}">
            </div>
            <div class="form-group">
              <label>صورة المشاركة الافتراضية</label>
              <input type="url" name="default_seo_image" class="form-control" value="${escapeHTML(s.default_seo_image || '')}">
            </div>
          </div>

          <h3 style="font-size:14px; font-weight:700; margin:24px 0 14px; color:var(--brand-600); display:flex; align-items:center; gap:8px;">
            <i data-lucide="bar-chart-3"></i> أكواد التتبع
          </h3>
          <div class="form-group">
            <label>📊 Google Analytics</label>
            <input type="text" name="google_analytics_id" class="form-control" value="${escapeHTML(s.google_analytics_id || '')}" placeholder="G-XXXXX">
          </div>
          <div class="form-group">
            <label>🎯 Facebook Pixel</label>
            <input type="text" name="facebook_pixel_id" class="form-control" value="${escapeHTML(s.facebook_pixel_id || '')}" placeholder="1234567890">
          </div>

          <h3 style="font-size:14px; font-weight:700; margin:24px 0 14px; color:var(--brand-600); display:flex; align-items:center; gap:8px;">
            <i data-lucide="share-2"></i> شبكات التواصل
          </h3>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div class="form-group"><label>Facebook</label><input type="url" name="social_facebook" class="form-control" value="${escapeHTML(s.social_facebook || '')}"></div>
            <div class="form-group"><label>X / Twitter</label><input type="url" name="social_twitter" class="form-control" value="${escapeHTML(s.social_twitter || '')}"></div>
            <div class="form-group"><label>Instagram</label><input type="url" name="social_instagram" class="form-control" value="${escapeHTML(s.social_instagram || '')}"></div>
            <div class="form-group"><label>YouTube</label><input type="url" name="social_youtube" class="form-control" value="${escapeHTML(s.social_youtube || '')}"></div>
            <div class="form-group"><label>Telegram</label><input type="url" name="social_telegram" class="form-control" value="${escapeHTML(s.social_telegram || '')}"></div>
            <div class="form-group"><label>WhatsApp</label><input type="url" name="social_whatsapp" class="form-control" value="${escapeHTML(s.social_whatsapp || '')}"></div>
          </div>

          <div style="margin-top:24px; display:flex; justify-content:flex-end;">
            <button type="submit" class="btn"><i data-lucide="save"></i> حفظ جميع الإعدادات</button>
          </div>
        </form>
      </div>`;

    refreshIcons();
    bindColorPickers();
  }).catch(() => {
    container.innerHTML = `<div class="error-msg">تعذّر جلب الإعدادات</div>`;
  });
}
function saveSettings(e) {
  e.preventDefault();
  const form = document.getElementById('form-settings');
  const fd = new FormData(form);

  // Collect colors from pickers
  const colors = {};
  document.querySelectorAll('#color-pickers-grid input[type="color"]').forEach(inp => {
    colors[inp.dataset.colorKey] = inp.value;
  });

  const btn = form.querySelector('button[type="submit"]');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = 'جارٍ الحفظ...';

  Promise.all([
    fetch('api.php?action=save_settings', { method: 'POST', body: fd }).then(r => r.json()),
    fetch('api.php?action=save_color_settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(colors)
    }).then(r => r.json())
  ]).then(([settingsRes, colorsRes]) => {
    btn.disabled = false;
    btn.innerHTML = original;
    refreshIcons();
    if (settingsRes.success && colorsRes.success) {
      showToast('تم حفظ الإعدادات والألوان بنجاح');
      // reload to apply persisted values
      setTimeout(() => loadSection('settings'), 400);
    } else {
      showToast((settingsRes.message || '') + ' ' + (colorsRes.message || ''), 'error');
    }
  }).catch(() => {
    btn.disabled = false;
    btn.innerHTML = original;
    refreshIcons();
    showToast('خطأ في الاتصال', 'error');
  });
}
// ---------- Category CRUD ----------
function showAddCategoryModal() {
  document.getElementById('cat-modal-title').textContent = 'إضافة قسم جديد';
  document.getElementById('cat-id').value = '';
  document.getElementById('form-category').reset();
  document.getElementById('cat-show-in-header').value = '1';
  openModal('category');
}
function editCategoryModal(id, name, status, show_in_header, meta_title, meta_desc) {
  document.getElementById('cat-modal-title').textContent = 'تعديل القسم #' + id;
  document.getElementById('cat-id').value = id;
  document.getElementById('cat-name').value = name;
  document.getElementById('cat-status').value = status;
  document.getElementById('cat-show-in-header').value = show_in_header != 0 ? '1' : '0';
  document.getElementById('cat-meta-title').value = meta_title || '';
  document.getElementById('cat-meta-desc').value = meta_desc || '';
  openModal('category');
}
function saveCategory(e) {
  e.preventDefault();
  fetch('api.php?action=save_category', { method: 'POST', body: new FormData(document.getElementById('form-category')) })
    .then(r => r.json()).then(d => {
      if (d.success) { closeModal('category'); showToast(d.message); loadSection('categories'); }
      else showToast(d.message, 'error');
    });
}
function deleteCategory(id) {
  if (!confirm('حذف القسم نهائياً؟')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('api.php?action=delete_category', { method: 'POST', body: fd })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); loadSection('categories'); }
    });
}

// ---------- AdSense CRUD ----------
function showAddAdsenseModal() {
  document.getElementById('ad-modal-title').textContent = 'إضافة مساحة إعلانية';
  document.getElementById('ad-id').value = '';
  document.getElementById('form-adsense').reset();
  document.getElementById('ad-slot-name').readOnly = false;
  document.getElementById('ad-slot-name').style.background = '';
  openModal('adsense');
}
function editAdsenseModal(id, slot_name, client, slot, format, active, code) {
  document.getElementById('ad-modal-title').textContent = 'تعديل الإعلان';
  document.getElementById('ad-id').value = id;
  document.getElementById('ad-slot-name').value = slot_name;
  document.getElementById('ad-slot-name').readOnly = true;
  document.getElementById('ad-slot-name').style.background = 'var(--surface-2)';
  document.getElementById('ad-client').value = client || '';
  document.getElementById('ad-slot').value = slot || '';
  document.getElementById('ad-format').value = format || 'auto';
  document.getElementById('ad-code').value = code || '';
  document.getElementById('ad-active').value = active;
  openModal('adsense');
}
function saveAdsense(e) {
  e.preventDefault();
  fetch('api.php?action=save_adsense', { method: 'POST', body: new FormData(document.getElementById('form-adsense')) })
    .then(r => r.json()).then(d => {
      if (d.success) { closeModal('adsense'); showToast(d.message); loadSection('adsense'); }
      else showToast(d.message, 'error');
    });
}
function deleteAdsense(id) {
  if (!confirm('حذف المساحة الإعلانية؟')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('api.php?action=delete_adsense', { method: 'POST', body: fd })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); loadSection('adsense'); }
    });
}

function showAddBannerModal() {
  document.getElementById('banner-modal-title').textContent = 'إضافة بنر مخصص';
  document.getElementById('banner-id').value = '';
  document.getElementById('form-banner').reset();
  showBannerImagePreview(null);
  openModal('banner');
}

function editBannerModal(id, name, url, price, promo_price, active, img) {
  document.getElementById('banner-modal-title').textContent = 'تعديل البنر #' + id;
  document.getElementById('banner-id').value = id;
  document.getElementById('banner-name').value = name;
  document.getElementById('banner-url').value = url;
  document.getElementById('banner-price').value = price;
  document.getElementById('banner-promo-price').value = promo_price;
  document.getElementById('banner-active').value = active;
  document.getElementById('banner-image-url').value = img && img.startsWith('http') ? img : '';
  if (img) {
    showBannerImagePreview(img.startsWith('http') ? img : 'https://cdn.9sty.com/uploads/' + img);
  } else {
    showBannerImagePreview(null);
  }
  openModal('banner');
}
function saveBanner(e) {
  e.preventDefault();
  fetch('api.php?action=save_banner', { method: 'POST', body: new FormData(document.getElementById('form-banner')) })
    .then(r => r.json()).then(d => {
      if (d.success) { closeModal('banner'); showToast(d.message); loadSection('banners'); }
      else showToast(d.message, 'error');
    });
}
function deleteBanner(id) {
  if (!confirm('نقل البنر إلى سلة المهملات؟')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('api.php?action=delete_banner', { method: 'POST', body: fd })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); loadSection('banners'); }
    });
}

// ---------- User CRUD ----------
function showAddUserModal() {
  document.getElementById('user-modal-title').textContent = 'إضافة مستخدم جديد';
  document.getElementById('user-id').value = '';
  document.getElementById('form-user').reset();
  document.getElementById('user-password').required = true;
  openModal('user');
}
function editUserModal(id, username, email, role) {
  document.getElementById('user-modal-title').textContent = 'تعديل المستخدم #' + id;
  document.getElementById('user-id').value = id;
  document.getElementById('user-username').value = username;
  document.getElementById('user-email').value = email;
  document.getElementById('user-role').value = role;
  document.getElementById('user-password').value = '';
  document.getElementById('user-password').required = false;
  openModal('user');
}
function saveUser(e) {
  e.preventDefault();
  fetch('api.php?action=save_user', { method: 'POST', body: new FormData(document.getElementById('form-user')) })
    .then(r => r.json()).then(d => {
      if (d.success) { closeModal('user'); showToast(d.message); loadSection('users'); }
      else showToast(d.message, 'error');
    });
}
function deleteUser(id) {
  if (!confirm('حذف حساب المستخدم نهائياً؟')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('api.php?action=delete_user', { method: 'POST', body: fd })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); loadSection('users'); }
      else showToast(d.message, 'error');
    });
}

// ---------- Comment CRUD ----------
function editCommentModal(id) {
  fetch('api.php?action=get_comment&id=' + id)
    .then(r => r.json()).then(d => {
      if (d.success) {
        var c = d.comment;
        document.getElementById('comment-id').value = c.id;
        document.getElementById('comment-name').value = c.name || '';
        document.getElementById('comment-status').value = c.status || 'approved';
        document.getElementById('comment-text').value = c.comment || '';
        openModal('comment');
      } else showToast(d.message, 'error');
    });
}
function saveComment(e) {
  e.preventDefault();
  fetch('api.php?action=save_comment', { method: 'POST', body: new FormData(document.getElementById('form-comment')) })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); closeModal('comment'); loadSection('comments'); }
      else showToast(d.message, 'error');
    });
}
function toggleHideComment(id) {
  var fd = new FormData(); fd.append('id', id);
  fetch('api.php?action=toggle_hide_comment', { method: 'POST', body: fd })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); loadSection('comments'); }
      else showToast(d.message, 'error');
    });
}
function deleteComment(id) {
  if (!confirm('نقل التعليق إلى سلة المهملات؟ (لن يُحذف نهائياً)')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('api.php?action=delete_comment', { method: 'POST', body: fd })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); loadSection('comments'); }
    });
}

// ---------- ShortLink CRUD ----------
function showAddShortLinkModal() {
  document.getElementById('sl-modal-title').textContent = 'إضافة رابط مختصر';
  document.getElementById('sl-id').value = '';
  document.getElementById('form-shortlink').reset();
  openModal('shortlink');
}
function editShortLinkModal(id, code, destination_url) {
  document.getElementById('sl-modal-title').textContent = 'تعديل الرابط #' + id;
  document.getElementById('sl-id').value = id;
  document.getElementById('sl-code').value = code;
  document.getElementById('sl-dest-url').value = destination_url;
  openModal('shortlink');
}
function saveShortLink(e) {
  e.preventDefault();
  fetch('api.php?action=save_short_link', { method: 'POST', body: new FormData(document.getElementById('form-shortlink')) })
    .then(r => r.json()).then(d => {
      if (d.success) { closeModal('shortlink'); showToast(d.message); loadSection('shortlinks'); }
      else showToast(d.message, 'error');
    });
}
function deleteShortLink(id) {
  if (!confirm('نقل الرابط إلى سلة المهملات؟')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('api.php?action=delete_short_link', { method: 'POST', body: fd })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); loadSection('shortlinks'); }
    });
}
function copyShortLinkText(inputId) {
  var input = document.getElementById(inputId);
  input.select(); input.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(input.value)
    .then(() => showToast('تم نسخ الرابط'))
    .catch(() => showToast('فشل النسخ', 'error'));
}

// ---------- Full-page Post Editor ----------
function urlEditPost(id) {
  var url = new URL(window.location.href);
  url.searchParams.set('edit_post', id);
  url.searchParams.delete('new_post');
  window.history.pushState({}, '', url);
  loadPostEditor(id);
}
function urlNewPost() {
  var url = new URL(window.location.href);
  url.searchParams.set('new_post', '1');
  url.searchParams.delete('edit_post');
  window.history.pushState({}, '', url);
  loadPostEditor(null);
}
function loadPostEditor(id) {
  closeMobileMenu();
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  var menu = document.getElementById('menu-posts');
  if (menu) menu.classList.add('active');

  document.getElementById('page-title').textContent = id ? 'تعديل الخبر #' + id : 'إضافة خبر جديد';
  document.getElementById('page-subtitle').textContent = 'محرر المقالات والأخبار الاحترافي | صياغة وتنسيق المحتوى';

  var area = document.getElementById('main-content');
  area.innerHTML = `
    <form id="form-post-full" class="post-editor-layout" onsubmit="savePostFull(event)">
      <!-- Main Content Column -->
      <div class="post-editor-main">
        <div class="card editor-main-card">
          <input type="hidden" name="id" id="post-id" value="${id || ''}">
          <div id="post-shortcode-info"></div>

            <!-- Title -->
            <div class="form-group title-form-group">
              <label for="post-title" class="editor-label">عنوان الخبر أو المقال <span class="required-star">*</span></label>
              <input type="text" name="title" id="post-title" class="form-control post-title-input" required placeholder="اكتب عنواناً جذاباً ودقيقاً للخبر..." autocomplete="off">
            </div>

            <!-- Excerpt -->
            <div class="form-group excerpt-form-group">
              <label for="post-excerpt" class="editor-label">مقتطف موجز (المقدمة) <span class="label-hint">يظهر في بطاقات الأخبار ومحركات البحث ومواقع التواصل</span></label>
              <textarea name="excerpt" id="post-excerpt" class="form-control post-excerpt-input" rows="2" placeholder="اكتب مقتطفاً موجزاً في سطرين يلخص فكرة المقال للقارئ..."></textarea>
            </div>

            <!-- Rich Content Editor -->
            <div class="form-group content-form-group">
              <div class="content-header-row">
                <label class="editor-label">محتوى الخبر وتفاصيل المقال <span class="required-star">*</span></label>
                <div class="editor-mode-toggle">
                  <button type="button" class="mode-btn active" id="btn-mode-visual" onclick="setEditorMode('visual')">
                    <i data-lucide="edit-3"></i> محرر مرئي
                  </button>
                  <button type="button" class="mode-btn" id="btn-mode-code" onclick="setEditorMode('code')">
                    <i data-lucide="code"></i> كود HTML
                  </button>
                </div>
              </div>

              <div class="rich-editor-container" id="rich-editor-box">
                <!-- Professional Rich Editor Toolbar -->
                <div class="editor-toolbar" id="editor-toolbar" role="toolbar" aria-label="أدوات تنسيق المحتوى">
                  <!-- Headings Group: H1, H2, H3, P -->
                  <div class="toolbar-group">
                    <button type="button" class="tb-btn tb-heading" data-command="formatBlock" data-value="H1" title="عنوان رئيسي (H1)">
                      <span>H1</span>
                    </button>
                    <button type="button" class="tb-btn tb-heading" data-command="formatBlock" data-value="H2" title="عنوان فرعي (H2)">
                      <span>H2</span>
                    </button>
                    <button type="button" class="tb-btn tb-heading" data-command="formatBlock" data-value="H3" title="عنوان ثانوي (H3)">
                      <span>H3</span>
                    </button>
                    <button type="button" class="tb-btn tb-heading" data-command="formatBlock" data-value="P" title="فقرة عادية (P)">
                      <span>P</span>
                    </button>
                  </div>

                  <div class="toolbar-divider"></div>

                  <!-- Text Formatting: Bold, Italic, Underline, Strikethrough -->
                  <div class="toolbar-group">
                    <button type="button" class="tb-btn" data-command="bold" title="خط عريض (Bold)">
                      <i data-lucide="bold"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="italic" title="خط مائل (Italic)">
                      <i data-lucide="italic"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="underline" title="تسطير (Underline)">
                      <i data-lucide="underline"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="strikeThrough" title="يتوسطه خط (Strikethrough)">
                      <i data-lucide="strikethrough"></i>
                    </button>
                  </div>

                  <div class="toolbar-divider"></div>

                  <!-- Structure: Lists, Quote, HR -->
                  <div class="toolbar-group">
                    <button type="button" class="tb-btn" data-command="insertUnorderedList" title="قائمة نقطية (List •)">
                      <i data-lucide="list"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="insertOrderedList" title="قائمة مرقمة (Numbered List 1.)">
                      <i data-lucide="list-ordered"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="formatBlock" data-value="BLOCKQUOTE" title="اقتباس متميز (Quote)">
                      <i data-lucide="quote"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="insertHorizontalRule" title="خط فاصل أفقي (Divider)">
                      <i data-lucide="minus"></i>
                    </button>
                  </div>

                  <div class="toolbar-divider"></div>

                  <!-- Links: A Tag, Unlink -->
                  <div class="toolbar-group">
                    <button type="button" class="tb-btn" id="tb-btn-link" onclick="promptInsertLink()" title="إدراج رابط (Link a)">
                      <i data-lucide="link"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="unlink" title="إزالة الرابط (Unlink)">
                      <i data-lucide="unlink"></i>
                    </button>
                  </div>

                  <div class="toolbar-divider"></div>

                  <!-- Alignment -->
                  <div class="toolbar-group">
                    <button type="button" class="tb-btn" data-command="justifyRight" title="محاذاة لليمين">
                      <i data-lucide="align-right"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="justifyCenter" title="توسيط النص">
                      <i data-lucide="align-center"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="justifyLeft" title="محاذاة لليسار">
                      <i data-lucide="align-left"></i>
                    </button>
                  </div>

                  <div class="toolbar-divider"></div>

                  <!-- Utility: Clear formatting, Undo, Redo -->
                  <div class="toolbar-group">
                    <button type="button" class="tb-btn" data-command="removeFormat" title="مسح التنسيق">
                      <i data-lucide="eraser"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="undo" title="تراجع (Ctrl+Z)">
                      <i data-lucide="undo"></i>
                    </button>
                    <button type="button" class="tb-btn" data-command="redo" title="إعادة (Ctrl+Y)">
                      <i data-lucide="redo"></i>
                    </button>
                  </div>
                </div>

                <!-- Visual Contenteditable Editor Surface -->
                <div id="post-editor-surface" class="editor-surface" contenteditable="true" spellcheck="false" placeholder="اكتب تفاصيل ومحتوى الخبر هنا بكل احترافية..."></div>

                <!-- Textarea Code (auto-synced) -->
                <textarea name="content" id="post-content" class="editor-textarea-code" style="display:none;" required placeholder="اكتب كود HTML أو النص هنا..."></textarea>

                <!-- Status & Counters Footer -->
                <div class="editor-footer">
                  <div class="editor-stats">
                    <span id="stat-words"><i data-lucide="file-text"></i> <strong>0</strong> كلمة</span>
                    <span class="stat-sep">•</span>
                    <span id="stat-chars"><strong>0</strong> حرف</span>
                    <span class="stat-sep">•</span>
                    <span id="stat-reading-time"><i data-lucide="clock"></i> قراءة في <strong>1</strong> دقيقة</span>
                  </div>
                  <div class="editor-tip">
                    <span>نصيحة: استخدم العناوين <strong>H2</strong> و <strong>H3</strong> لتنظيم المقال وتسهيل القراءة</span>
                  </div>
                </div>
              </div>
            </div>
        </div>
      </div>

      <!-- Sidebar Meta & Actions Column -->
      <div class="post-editor-sidebar">
        <!-- Publishing Settings Card -->
        <div class="card meta-card">
          <div class="card-header-clean">
            <div class="card-title-wrap">
              <i data-lucide="send" style="color:var(--brand-600);"></i>
              <h3>إعدادات النشر</h3>
            </div>
            <span class="badge published" id="post-status-badge">منشور للعموم</span>
          </div>

          <div class="form-group" style="margin-top:14px;">
            <label for="post-status" class="editor-label">حالة المقال</label>
            <select name="status" id="post-status" form="form-post-full" class="form-control" onchange="updateStatusBadge(this.value)">
              <option value="published">منشور للعموم (Published)</option>
              <option value="draft">مسودة خاصة (Draft)</option>
              <option value="archived">مؤرشف (Archived)</option>
            </select>
          </div>

          <div class="form-group">
            <label for="post-category" class="editor-label">تصنيف المقال (القسم) <span class="required-star">*</span></label>
            <select name="category_id" id="post-category" form="form-post-full" class="form-control" required></select>
          </div>

          <div class="meta-actions">
            <button type="submit" form="form-post-full" class="btn btn-primary-publish">
              <i data-lucide="check-circle"></i> حفظ وتحديث الخبر
            </button>
            <button type="button" class="btn btn-secondary btn-cancel-edit" onclick="cancelPostEdit()">
              <i data-lucide="x"></i> إلغاء ورجوع
            </button>
          </div>
        </div>

        <!-- Featured Image Card with LIVE PREVIEW -->
        <div class="card meta-card image-meta-card">
          <div class="card-header-clean">
            <div class="card-title-wrap">
              <i data-lucide="image" style="color:#2563eb;"></i>
              <h3>صورة الغلاف (Featured Image)</h3>
            </div>
          </div>

          <div class="image-upload-wrapper">
            <!-- Live Preview Container -->
            <div id="post-image-preview" class="post-image-preview-box" onclick="document.getElementById('post-image').click()" title="اضغط لاختيار صورة">
              <div class="empty-image-placeholder" id="empty-image-placeholder">
                <i data-lucide="image-plus"></i>
                <span>لا توجد صورة محددة بعد</span>
                <small>اسحب الصورة هنا أو اضغط للاختيار</small>
              </div>
            </div>

            <!-- Upload Controls -->
            <div class="image-input-control">
              <input type="file" name="image" id="post-image" form="form-post-full" class="hidden-file-input" accept="image/jpeg,image/png,image/webp,image/gif">
              <button type="button" class="btn-upload-trigger" onclick="document.getElementById('post-image').click()">
                <i data-lucide="upload-cloud"></i> اختيار صورة من الجهاز
              </button>
              <button type="button" class="btn-danger-soft" id="btn-remove-image" style="display:none;" onclick="clearPostImageSelection(event)">
                <i data-lucide="trash-2"></i> إزالة
              </button>
            </div>

            <div class="image-upload-hint">
              <span>الصيغ المدعومة: WebP, JPG, PNG, GIF (النسبة المثالية 16:9 أو 4:3)</span>
            </div>
          </div>
        </div>
      </div>
    </form>`;

  // Populate Categories
  var select = document.getElementById('post-category');
  select.innerHTML = '<option value="">— اختر القسم —</option>';
  categoriesList.forEach(c => { select.innerHTML += `<option value="${c.id}">${escapeHTML(c.name)}</option>`; });

  // Initialize Rich Text Editor & Live Image Preview handlers
  initRichEditor();
  initImagePreview();

  // If editing an existing post, fetch its data
  if (id) {
    fetch('api.php?action=get_post&id=' + id)
      .then(r => r.json()).then(d => {
        if (!d.success) { showToast(d.message, 'error'); return; }
        var p = d.post;
        document.getElementById('post-title').value = p.title || '';
        document.getElementById('post-category').value = p.category_id || '';
        document.getElementById('post-status').value = p.status || 'published';
        updateStatusBadge(p.status || 'published');
        document.getElementById('post-excerpt').value = p.excerpt || '';

        var surface = document.getElementById('post-editor-surface');
        var textarea = document.getElementById('post-content');
        var rawContent = p.content || '';
        if (surface) surface.innerHTML = rawContent;
        if (textarea) textarea.value = rawContent;
        updateEditorStats();

        if (p.short_code) {
          var el = document.getElementById('post-shortcode-info');
          el.innerHTML = `
            <div style="background:var(--brand-50); border:1px solid var(--brand-100); border-radius:10px; padding:12px 16px; margin-bottom:16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
              <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="link" style="color:var(--brand-600);"></i>
                <span style="font-weight:700; font-size:13px;">كود المشاركة:</span>
                <code style="background:var(--surface); padding:3px 10px; border-radius:6px; border:1px solid var(--brand-100); font-weight:700; color:var(--brand-600); letter-spacing:1px;">${p.short_code}</code>
              </div>
              <a href="https://9sty.com/s/${p.short_code}" target="_blank" style="color:var(--brand-600); font-weight:600; font-size:12.5px; direction:ltr;">https://9sty.com/s/${p.short_code}</a>
            </div>`;
        }

        if (p.image) {
          renderImagePreview('https://cdn.9sty.com/uploads/' + p.image, p.image, 'الصورة الحالية', false);
        }
        refreshIcons();
      });
  }
  refreshIcons();
}

// ---------- Rich Editor Functionality ----------
function initRichEditor() {
  var surface = document.getElementById('post-editor-surface');
  var textarea = document.getElementById('post-content');
  var toolbar = document.getElementById('editor-toolbar');
  if (!surface || !textarea || !toolbar) return;

  // Toolbar button click commands
  toolbar.querySelectorAll('.tb-btn[data-command]').forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var cmd = btn.getAttribute('data-command');
      var val = btn.getAttribute('data-value') || null;

      surface.focus();
      if (cmd === 'formatBlock') {
        document.execCommand('formatBlock', false, '<' + val + '>');
      } else {
        document.execCommand(cmd, false, val);
      }

      // Sync to textarea and stats
      textarea.value = surface.innerHTML;
      updateEditorStats();
    });
  });

  // Sync content on user input
  surface.addEventListener('input', function () {
    textarea.value = surface.innerHTML;
    updateEditorStats();
  });

  // Sync from textarea when editing raw code
  textarea.addEventListener('input', function () {
    surface.innerHTML = textarea.value;
    updateEditorStats();
  });

  updateEditorStats();
}

function updateEditorStats() {
  var surface = document.getElementById('post-editor-surface');
  if (!surface) return;
  var text = surface.innerText || surface.textContent || '';
  var cleanText = text.trim();
  var wordCount = cleanText ? cleanText.split(/\s+/).filter(Boolean).length : 0;
  var charCount = cleanText.length;
  var readingMinutes = Math.max(1, Math.ceil(wordCount / 180));

  var elWords = document.getElementById('stat-words');
  var elChars = document.getElementById('stat-chars');
  var elTime = document.getElementById('stat-reading-time');

  if (elWords) elWords.innerHTML = `<i data-lucide="file-text"></i> <strong>${wordCount}</strong> كلمة`;
  if (elChars) elChars.innerHTML = `<strong>${charCount}</strong> حرف`;
  if (elTime) elTime.innerHTML = `<i data-lucide="clock"></i> قراءة في <strong>${readingMinutes}</strong> دقيقة`;
  refreshIcons();
}

function setEditorMode(mode) {
  var surface = document.getElementById('post-editor-surface');
  var textarea = document.getElementById('post-content');
  var toolbar = document.getElementById('editor-toolbar');
  var btnVisual = document.getElementById('btn-mode-visual');
  var btnCode = document.getElementById('btn-mode-code');

  if (!surface || !textarea) return;

  if (mode === 'code') {
    textarea.value = surface.innerHTML;
    surface.style.display = 'none';
    textarea.style.display = 'block';
    if (toolbar) toolbar.style.opacity = '0.5';
    if (toolbar) toolbar.style.pointerEvents = 'none';
    if (btnVisual) btnVisual.classList.remove('active');
    if (btnCode) btnCode.classList.add('active');
  } else {
    surface.innerHTML = textarea.value;
    textarea.style.display = 'none';
    surface.style.display = 'block';
    if (toolbar) toolbar.style.opacity = '1';
    if (toolbar) toolbar.style.pointerEvents = 'auto';
    if (btnCode) btnCode.classList.remove('active');
    if (btnVisual) btnVisual.classList.add('active');
    surface.focus();
  }
}

function promptInsertLink() {
  var surface = document.getElementById('post-editor-surface');
  if (!surface) return;
  var selection = window.getSelection();
  var selectedText = selection.toString();

  var url = prompt('أدخل رابط الموقع (URL):', 'https://');
  if (!url || url.trim() === '' || url.trim() === 'https://') return;
  url = url.trim();

  surface.focus();
  document.execCommand('createLink', false, url);

  // Sync to textarea and update
  var textarea = document.getElementById('post-content');
  if (textarea) textarea.value = surface.innerHTML;
  updateEditorStats();
}

// ---------- Live Image Preview Functionality ----------
function initImagePreview() {
  var fileInput = document.getElementById('post-image');
  var previewBox = document.getElementById('post-image-preview');
  if (!fileInput || !previewBox) return;

  fileInput.addEventListener('change', function (e) {
    var file = e.target.files && e.target.files[0];
    if (!file) return;

    // Check if it's an image
    if (!file.type.match(/^image\//)) {
      showToast('يرجى اختيار ملف صورة صالح', 'error');
      fileInput.value = '';
      return;
    }

    var reader = new FileReader();
    reader.onload = function (evt) {
      var sizeKb = (file.size / 1024).toFixed(1) + ' KB';
      renderImagePreview(evt.target.result, file.name, sizeKb, true);
    };
    reader.readAsDataURL(file);
  });

  // Drag and drop events
  previewBox.addEventListener('dragover', function (e) {
    e.preventDefault();
    previewBox.classList.add('drag-active');
  });

  previewBox.addEventListener('dragleave', function () {
    previewBox.classList.remove('drag-active');
  });

  previewBox.addEventListener('drop', function (e) {
    e.preventDefault();
    previewBox.classList.remove('drag-active');
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      fileInput.files = e.dataTransfer.files;
      var event = new Event('change');
      fileInput.dispatchEvent(event);
    }
  });
}

function renderImagePreview(src, name, metaLabel, isNew) {
  var previewBox = document.getElementById('post-image-preview');
  var btnRemove = document.getElementById('btn-remove-image');
  if (!previewBox) return;

  var badgeText = isNew ? 'معاينة الصورة الجديدة' : 'الصورة الحالية';
  var badgeIcon = isNew ? 'sparkles' : 'check';

  previewBox.innerHTML = `
    <div class="preview-img-container">
      <img src="${src}" class="main-preview-img" alt="${escapeHTML(name || 'غلاف المقال')}">
      <div class="preview-badge-overlay">
        <i data-lucide="${badgeIcon}"></i>
        <span>${badgeText} (${escapeHTML(metaLabel || '')})</span>
      </div>
    </div>
  `;

  if (btnRemove) btnRemove.style.display = 'inline-flex';
  refreshIcons();
}

function clearPostImageSelection(e) {
  if (e) e.stopPropagation();
  var fileInput = document.getElementById('post-image');
  if (fileInput) fileInput.value = '';

  var previewBox = document.getElementById('post-image-preview');
  if (previewBox) {
    previewBox.innerHTML = `
      <div class="empty-image-placeholder" id="empty-image-placeholder">
        <i data-lucide="image-plus"></i>
        <span>لا توجد صورة محددة بعد</span>
        <small>اسحب الصورة هنا أو اضغط للاختيار</small>
      </div>
    `;
  }

  var btnRemove = document.getElementById('btn-remove-image');
  if (btnRemove) btnRemove.style.display = 'none';
  refreshIcons();
}

function updateStatusBadge(val) {
  var badge = document.getElementById('post-status-badge');
  if (!badge) return;
  var map = {
    published: { text: 'منشور للعموم', class: 'badge published' },
    draft: { text: 'مسودة خاصة', class: 'badge draft' },
    archived: { text: 'مؤرشف', class: 'badge archived' }
  };
  var s = map[val] || { text: val, class: 'badge' };
  badge.className = s.class;
  badge.textContent = s.text;
}

function cancelPostEdit() {
  var url = new URL(window.location.href);
  url.searchParams.delete('edit_post');
  url.searchParams.delete('new_post');
  window.history.pushState({}, '', url);
  loadSection('posts');
}

function savePostFull(e) {
  e.preventDefault();
  var form = document.getElementById('form-post-full');
  if (!form) { showToast('تعذر العثور على النموذج', 'error'); return; }

  // Synchronize Rich Text editor content into form textarea
  var surface = document.getElementById('post-editor-surface');
  var textarea = document.getElementById('post-content');
  if (surface && textarea) {
    if (surface.style.display !== 'none') {
      textarea.value = surface.innerHTML;
    } else {
      surface.innerHTML = textarea.value;
    }
  }

  var btn = form.querySelector('button[type="submit"]');
  if (!btn) { showToast('تعذر العثور على زر الحفظ', 'error'); return; }

  var original = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = 'جارٍ الحفظ...';
  fetch('api.php?action=save_post', { method: 'POST', body: new FormData(form) })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); cancelPostEdit(); }
      else {
        showToast(d.message, 'error');
        btn.disabled = false; btn.innerHTML = original; refreshIcons();
      }
    })
    .catch(() => {
      showToast('خطأ في الاتصال', 'error');
      btn.disabled = false; btn.innerHTML = original; refreshIcons();
    });
}
function deletePost(id) {
  if (!confirm('حذف الخبر نهائياً؟')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('api.php?action=delete_post', { method: 'POST', body: fd })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); loadSection('posts'); }
      else showToast(d.message, 'error');
    });
}
/* ================= TRASH SECTION ================= */

var trashState = { type: 'all', page: 1, search: '' };

function renderTrash(container) {
  container.innerHTML = `<div class="card"><div class="loader-shimmer" style="height:80px;"></div></div>`;

  var url = `api.php?action=list_trash&type=${trashState.type}&page=${trashState.page}`;
  if (trashState.search) url += `&search=${encodeURIComponent(trashState.search)}`;

  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (!data.success) { container.innerHTML = `<div class="error-msg">${escapeHTML(data.message || 'خطأ')}</div>`; return; }

      var tabs = [
        { id: 'all',        label: 'الكل',              icon: 'trash-2',       count: data.counts.all },
        { id: 'post',       label: 'المقالات',          icon: 'newspaper',     count: data.counts.post },
        { id: 'short_post', label: 'منشورات قصيرة',     icon: 'file-text',     count: data.counts.short_post },
        { id: 'comment',    label: 'التعليقات',         icon: 'message-square',count: data.counts.comment },
        { id: 'short_link', label: 'روابط مختصرة',      icon: 'link-2',        count: data.counts.short_link },
        { id: 'banner',     label: 'بنرات',             icon: 'image',         count: data.counts.banner }
      ];

      var tabsHtml = tabs.map(t => `
        <button class="analytics-tab-btn ${trashState.type === t.id ? 'active' : ''}" onclick="switchTrashTab('${t.id}')">
          <i data-lucide="${t.icon}"></i> ${t.label}
          <span style="background:var(--surface-2);color:var(--text-muted);padding:1px 7px;border-radius:4px;font-size:10.5px;margin-right:4px;">${t.count}</span>
        </button>`).join('');

      var typeColors = {
        post: 'background:#fef3c7;color:#92400e;',
        short_post: 'background:#dbeafe;color:#1e40af;',
        comment: 'background:#ffe4e6;color:#be123c;',
        short_link: 'background:#d1ebf3;color:#1c3452;',
        banner: 'background:#e0e7ff;color:#4338ca;'
      };

      var rows = data.items.map(item => {
        var badge = `<span class="badge" style="font-size:10.5px;${typeColors[item.item_type] || ''}">${item.type_label}</span>`;
        var title = escapeHTML(item.name || '—');
        var sub = '';
        if (item.item_type === 'short_link' && item.destination_url) {
          sub = `<div style="font-size:11px;color:var(--text-faint);direction:ltr;text-align:right;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHTML(item.destination_url)}</div>`;
        } else if (item.item_type === 'banner' && item.url) {
          sub = `<div style="font-size:11px;color:var(--text-faint);direction:ltr;text-align:right;">${escapeHTML(item.url)}</div>`;
        } else if (item.item_type === 'comment' && item.email) {
          sub = `<div style="font-size:11px;color:var(--text-faint);">${escapeHTML(item.email)}</div>`;
        }

        return `<tr>
          <td>${badge}</td>
          <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            ${title}${sub}
          </td>
          <td style="color:var(--text-muted);font-size:12px;white-space:nowrap;">${escapeHTML((item.deleted_at || '').substring(0, 16))}</td>
          <td>
            <div class="table-actions">
              <button class="act-btn" onclick="restoreTrashItem('${item.item_type}', ${item.id})" title="استعادة"><i data-lucide="rotate-ccw"></i></button>
              <button class="act-btn del" onclick="deleteTrashForever('${item.item_type}', ${item.id})" title="حذف نهائي"><i data-lucide="trash-2"></i></button>
            </div>
          </td>
        </tr>`;
      }).join('');

      container.innerHTML = `
        <div class="card">
          <div class="card-header">
            <h2>سلة المهملات <span style="color:var(--text-muted);font-size:13px;">(${data.counts.all})</span></h2>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <input type="text" class="form-control" id="trash-search" placeholder="بحث..." value="${escapeHTML(trashState.search)}" style="width:220px;font-size:12px;">
              ${data.counts.all > 0 ? `<button class="btn btn-danger" onclick="emptyTrash()" style="padding:8px 14px;font-size:12.5px;"><i data-lucide="trash-2"></i> تفريغ السلة</button>` : ''}
            </div>
          </div>
          <div class="analytics-tabs" style="border-bottom:1px solid var(--border);padding-bottom:12px;margin-bottom:16px;">${tabsHtml}</div>
          <div class="table-responsive">
            <table>
              <thead><tr><th>النوع</th><th>العنصر</th><th>تاريخ الحذف</th><th>إجراءات</th></tr></thead>
              <tbody>${rows || `<tr><td colspan="4"><div class="empty-state"><i data-lucide="trash-2"></i><p>السلة فارغة</p></div></td></tr>`}</tbody>
            </table>
          </div>
          ${renderPagination(data.pagination)}
        </div>`;

      refreshIcons();

      var searchInp = document.getElementById('trash-search');
      if (searchInp) {
        var tmr;
        searchInp.addEventListener('input', function () {
          clearTimeout(tmr);
          tmr = setTimeout(() => {
            trashState.search = this.value.trim();
            trashState.page = 1;
            renderTrash(container);
          }, 300);
        });
      }

      bindPagination(container, p => { trashState.page = p; renderTrash(container); });
    })
    .catch(() => { container.innerHTML = `<div class="error-msg">تعذّر الاتصال</div>`; });
}

function switchTrashTab(type) {
  trashState.type = type;
  trashState.page = 1;
  renderTrash(document.getElementById('main-content'));
}

function restoreTrashItem(itemType, id) {
  var fd = new FormData();
  fd.append('item_type', itemType);
  fd.append('id', id);
  fetch('api.php?action=restore_trash', { method: 'POST', body: fd })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); renderTrash(document.getElementById('main-content')); }
      else showToast(d.message || 'خطأ', 'error');
    });
}

function deleteTrashForever(itemType, id) {
  if (!confirm('حذف نهائي؟ لا يمكن التراجع عن هذه العملية!')) return;
  var fd = new FormData();
  fd.append('item_type', itemType);
  fd.append('id', id);
  fetch('api.php?action=delete_forever', { method: 'POST', body: fd })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); renderTrash(document.getElementById('main-content')); }
      else showToast(d.message || 'خطأ', 'error');
    });
}

function emptyTrash() {
  if (!confirm('تفريغ السلة بالكامل؟ سيتم حذف كل العناصر نهائياً!')) return;
  fetch('api.php?action=empty_trash', { method: 'POST' })
    .then(r => r.json()).then(d => {
      if (d.success) { showToast(d.message); renderTrash(document.getElementById('main-content')); }
      else showToast(d.message || 'خطأ', 'error');
    });
}

/* ================= BANNER CLICKS DETAILS ================= */

var bannerClicksState = { slug: '', name: '', page: 1 };

function openBannerClicks(slug, name) {
  bannerClicksState = { slug, name, page: 1 };
  var modal = document.getElementById('modal-banner-clicks');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'modal-banner-clicks';
    modal.className = 'modal-backdrop';
    modal.innerHTML = `
      <div class="modal" style="max-width:900px;">
        <div class="modal-header">
          <div class="modal-header-left">
            <div class="modal-icon"><i data-lucide="mouse-pointer-click"></i></div>
            <h3>نقرات البنر: <span id="banner-clicks-title"></span></h3>
          </div>
          <button class="modal-close" onclick="closeBannerClicks()"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body" id="banner-clicks-body">
          <div class="loader-shimmer" style="height:80px;"></div>
        </div>
      </div>`;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) closeBannerClicks(); });
  }
  document.getElementById('banner-clicks-title').textContent = name;
  modal.classList.add('show');
  refreshIcons();
  loadBannerClicks(1);
}

function closeBannerClicks() {
  var m = document.getElementById('modal-banner-clicks');
  if (m) m.classList.remove('show');
}

function loadBannerClicks(page) {
  bannerClicksState.page = page;
  var body = document.getElementById('banner-clicks-body');
  if (!body) return;
  body.innerHTML = `<div class="loader-shimmer" style="height:80px;"></div>`;

  fetch(`api.php?action=banner_clicks&slug=${encodeURIComponent(bannerClicksState.slug)}&page=${page}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) { body.innerHTML = `<div class="error-msg">${escapeHTML(data.message || 'خطأ')}</div>`; return; }
      var s = data.summary || {};
      var rows = (data.clicks || []).map(c => {
        var ref = c.referrer
          ? `<span title="${escapeHTML(c.referrer)}" style="max-width:180px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;direction:ltr;text-align:right;font-size:12px;color:var(--text-muted);">${escapeHTML(c.referrer.replace(/^https?:\/\//, ''))}</span>`
          : `<span style="color:var(--text-faint);font-size:11px;">مباشر</span>`;
        var pageUrl = c.page_url
          ? `<span title="${escapeHTML(c.page_url)}" style="max-width:240px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;direction:ltr;font-size:12px;">${escapeHTML(c.page_url)}</span>`
          : '—';
        return `<tr>
          <td>#${c.id}</td>
          <td>${pageUrl}</td>
          <td>${ref}</td>
          <td>${escapeHTML(c.device_type || '—')}</td>
          <td><span class="tracking-ip-badge">${escapeHTML(c.ip_hash || '—')}</span></td>
          <td><span class="tracking-time-badge">${escapeHTML(c.clicked_at || '—')}</span></td>
        </tr>`;
      }).join('');

      body.innerHTML = `
        <div class="analytics-summary-bar" style="margin-bottom:16px;">
          <div class="analytics-stat-chip"><i data-lucide="mouse-pointer-click" style="color:#2563eb;width:16px;height:16px;"></i> إجمالي: <strong>${(s.total || 0).toLocaleString('en')}</strong></div>
          <div class="analytics-stat-chip"><i data-lucide="users" style="color:#059669;width:16px;height:16px;"></i> IPs فريدة: <strong>${(s.unique_ips || 0).toLocaleString('en')}</strong></div>
          <div class="analytics-stat-chip"><i data-lucide="calendar" style="color:#d97706;width:16px;height:16px;"></i> أيام نشطة: <strong>${(s.active_days || 0)}</strong></div>
        </div>
        <div class="table-responsive">
          <table>
            <thead><tr><th>#</th><th>الصفحة</th><th>الإحالة</th><th>الجهاز</th><th>IP</th><th>الوقت</th></tr></thead>
            <tbody>${rows || '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">لا توجد نقرات</td></tr>'}</tbody>
          </table>
        </div>
        ${renderPagination(data.pagination)}`;
      refreshIcons();
      bindPagination(body, p => loadBannerClicks(p));
    })
    .catch(() => { body.innerHTML = `<div class="error-msg">تعذّر التحميل</div>`; });
}
// ---------- Boot ----------
document.addEventListener('DOMContentLoaded', () => {
  applyTheme();
  refreshIcons();

  // MutationObserver to auto-refresh icons on dynamic content

  fetchCategoriesData();
  initBannerImagePreview();
  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('edit_post')) {
    setTimeout(() => loadPostEditor(urlParams.get('edit_post')), 200);
  } else if (urlParams.has('new_post')) {
    setTimeout(() => loadPostEditor(null), 200);
  } else if (urlParams.has('edit_short_post')) {
    setTimeout(() => loadShortPostEditor(urlParams.get('edit_short_post')), 200);
  } else if (urlParams.has('new_short_post')) {
    setTimeout(() => loadShortPostEditor(null), 200);
  } else if (urlParams.get('section') === 'shortposts') {
    loadSection('shortposts');
  } else if (urlParams.get('section') === 'analytics') {
    loadSection('analytics');
  } else {
    loadSection('overview');
  }
});
function initBannerImagePreview() {
  var fileInput = document.getElementById('banner-image-file');
  var urlInput = document.getElementById('banner-image-url');
  if (!fileInput || !urlInput) return;

  fileInput.addEventListener('change', function (e) {
    var file = e.target.files && e.target.files[0];
    if (!file) return;
    if (!file.type.match(/^image\//)) {
      showToast('يرجى اختيار ملف صورة صالح', 'error');
      fileInput.value = '';
      return;
    }
    var reader = new FileReader();
    reader.onload = function (evt) { showBannerImagePreview(evt.target.result); };
    reader.readAsDataURL(file);
  });

  urlInput.addEventListener('input', function () {
    if (!fileInput.files || !fileInput.files.length) {
      showBannerImagePreview(urlInput.value.trim() || null);
    }
  });
}

function showBannerImagePreview(src) {
  var box = document.getElementById('banner-image-preview');
  if (!box) return;
  if (src) {
    box.innerHTML = `<img src="${escapeHTML(src)}" alt="preview">`;
  } else {
    box.innerHTML = `<div class="banner-image-preview-empty">
      <i data-lucide="image"></i>
      <span>لا توجد صورة بعد</span>
    </div>`;
    refreshIcons();
  }
}
/* ================= THEME COLOR PICKERS ================= */

var COLOR_LABELS = {
  color_bg: 'خلفية الصفحة',
  color_surface: 'خلفية البطاقات',
  color_surface_2: 'خلفية ثانوية',
  color_border: 'الحدود',
  color_border_strong: 'حدود قوية',
  color_text: 'النص الأساسي',
  color_text_muted: 'نص ثانوي',
  color_text_faint: 'نص خفيف',
  color_brand: 'الذهبي (Brand)',
  color_brand_600: 'ذهبي غامق (نص)',
  color_brand_50: 'خلفية ذهبية فاتحة',
  color_brand_100: 'حدود ذهبية',
  color_sidebar_bg: 'خلفية القائمة',
  color_sidebar_bg_2: 'القائمة (متدرج)',
  color_sidebar_text: 'نص القائمة',
  color_grad_start: 'التدرج - البداية',
  color_grad_mid: 'التدرج - الوسط',
  color_grad_end: 'التدرج - النهاية',
};

var COLOR_GROUPS = {
  'العلامة التجارية': ['color_brand', 'color_brand_600', 'color_brand_50', 'color_brand_100'],
  'الخلفيات': ['color_bg', 'color_surface', 'color_surface_2'],
  'النصوص': ['color_text', 'color_text_muted', 'color_text_faint'],
  'الحدود': ['color_border', 'color_border_strong'],
  'الشريط الجانبي': ['color_sidebar_bg', 'color_sidebar_bg_2', 'color_sidebar_text'],
  'تدرج الأزرار': ['color_grad_start', 'color_grad_mid', 'color_grad_end'],
};

function renderColorPicker(key, value) {
  var label = COLOR_LABELS[key] || key;
  var safeVal = /^#[0-9a-fA-F]{6}$/.test(value) ? value : '#000000';
  return `
    <div class="color-picker-item" style="background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:10px; display:flex; flex-direction:column; gap:8px;">
      <label style="font-size:12px; font-weight:700; color:var(--text);">${label}</label>
      <div style="display:flex; gap:8px; align-items:center;">
        <input type="color" data-color-key="${key}" value="${safeVal}" style="width:44px; height:38px; border:none; border-radius:8px; cursor:pointer; padding:2px; background:transparent;">
        <input type="text" data-color-key="${key}" class="color-hex-input" value="${escapeHTML(value)}" style="flex:1; padding:6px 10px; border:1px solid var(--border); border-radius:6px; background:var(--surface-2); font-family:monospace; font-size:12px; color:var(--text);">
      </div>
    </div>`;
}

function renderColorSection(colors) {
  var html = '';
  Object.entries(COLOR_GROUPS).forEach(([groupName, keys]) => {
    html += `<h4 style="grid-column:1/-1; font-size:12.5px; font-weight:800; margin:14px 0 4px; color:var(--brand-600);">${groupName}</h4>`;
    keys.forEach(k => { html += renderColorPicker(k, colors[k] || '#000000'); });
  });
  return html;
}

function colorKeyToCssVar(key) {
  var map = {
    color_bg: '--bg',
    color_surface: '--surface',
    color_surface_2: '--surface-2',
    color_border: '--border',
    color_border_strong: '--border-strong',
    color_text: '--text',
    color_text_muted: '--text-muted',
    color_text_faint: '--text-faint',
    color_brand: '--brand',
    color_brand_600: '--brand-600',
    color_brand_50: '--brand-50',
    color_brand_100: '--brand-100',
    color_sidebar_bg: '--sidebar-bg',
    color_sidebar_bg_2: '--sidebar-bg-2',
    color_sidebar_text: '--sidebar-text',
  };
  return map[key] || null;
}

function bindColorPickers() {
  document.querySelectorAll('#color-pickers-grid input[type="color"]').forEach(inp => {
    inp.addEventListener('input', function () {
      var key = this.dataset.colorKey;
      var val = this.value;
      var txt = document.querySelector(`.color-hex-input[data-color-key="${key}"]`);
      if (txt) txt.value = val;
      var cssVar = colorKeyToCssVar(key);
      if (cssVar) document.documentElement.style.setProperty(cssVar, val);
      if (key.startsWith('color_grad_')) updateGradientLive();
    });
  });

  document.querySelectorAll('#color-pickers-grid .color-hex-input').forEach(inp => {
    inp.addEventListener('input', function () {
      var key = this.dataset.colorKey;
      var val = this.value.trim();
      if (!/^#[0-9a-fA-F]{3,8}$/.test(val)) return;
      var col = document.querySelector(`#color-pickers-grid input[type="color"][data-color-key="${key}"]`);
      if (col) col.value = val;
      var cssVar = colorKeyToCssVar(key);
      if (cssVar) document.documentElement.style.setProperty(cssVar, val);
      if (key.startsWith('color_grad_')) updateGradientLive();
    });
  });
}

function updateGradientLive() {
  function getVal(k, def) {
    var el = document.querySelector(`.color-hex-input[data-color-key="${k}"]`);
    return el ? el.value.trim() : def;
  }
  var s = getVal('color_grad_start', '#f7e46a');
  var m = getVal('color_grad_mid', '#f1d025');
  var e = getVal('color_grad_end', '#d4b01e');
  document.documentElement.style.setProperty('--brand-grad', `linear-gradient(135deg, ${s} 0%, ${m} 55%, ${e} 100%)`);
}

function resetThemeColors() {
  if (!confirm('استرجاع جميع الألوان الافتراضية؟ سيتم فقدان التعديلات الحالية.')) return;
  fetch('api.php?action=reset_color_settings', { method: 'POST' })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showToast(d.message);
        setTimeout(() => loadSection('settings'), 300);
      } else showToast(d.message || 'خطأ', 'error');
    });
}
