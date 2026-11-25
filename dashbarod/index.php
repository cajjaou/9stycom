<?php
// dashboard/index.php - Modern Amber Admin Panel
session_set_cookie_params([
  'httponly' => true,
  'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
  'samesite' => 'Strict',
  'path' => '/',
]);
session_start();
require_once file_exists(__DIR__ . '/db.php') ? __DIR__ . '/db.php' : __DIR__ . '/../db.php';
$pdo = get_db();

$adminExists = false;
try {
  $adminExists = ((int) $pdo->query("SELECT COUNT(*) FROM 9s_users WHERE role = 1")->fetchColumn() > 0);
} catch (PDOException $e) {
}

$error = '';

if (!$adminExists && isset($_POST['setup_admin'])) {
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  if (empty($username) || empty($email) || empty($password)) {
    $error = 'جميع الحقول إجبارية لإعداد حساب المدير!';
  } else {
    try {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO 9s_users (username, email, password, role) VALUES (?, ?, ?, 1)");
      $stmt->execute([$username, $email, $hash]);
      $_SESSION['admin_logged'] = true;
      $_SESSION['admin_user_id'] = $pdo->lastInsertId();
      $_SESSION['admin_username'] = $username;
      $_SESSION['admin_role'] = 1;
      session_regenerate_id(true);
      header('Location: index.php');
      exit;
    } catch (PDOException $ex) {
      $error = 'حدث خطأ أثناء إنشاء الحساب: ' . $ex->getMessage();
    }
  }
}

if ($adminExists && isset($_POST['login'])) {
  $usernameOrEmail = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  if (empty($usernameOrEmail) || empty($password)) {
    $error = 'يرجى إدخال اسم المستخدم وكلمة المرور!';
  } else {
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM 9s_users WHERE username = ? OR email = ?");
    $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
      if ((int) $user['role'] === 1) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_user_id'] = (int) $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_role'] = 1;
        session_regenerate_id(true);
        header('Location: index.php');
        exit;
      } else {
        $error = 'عذراً، هذا الحساب ليس له صلاحيات الإدارة!';
      }
    } else {
      $error = 'اسم المستخدم أو كلمة المرور غير صحيحة!';
    }
  }
}

if (isset($_GET['logout'])) {
  unset($_SESSION['admin_logged'], $_SESSION['admin_user_id'], $_SESSION['admin_username'], $_SESSION['admin_role']);
  session_destroy();
  header('Location: index.php');
  exit;
}

$is_logged = isset($_SESSION['admin_logged']);
$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$admin_initial = mb_strtoupper(mb_substr($admin_username, 0, 1));

// Load theme colors for dynamic CSS
$themeCssVars = function_exists('ja_theme_css_vars') ? ja_theme_css_vars() : [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>لوحة التحكم | أخبار المغرب</title>
  <link rel="icon" type="image/svg+xml" href="https://cdn.9sty.com/uploads/logo_akhbaralmghrib.svg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">

  <script defer src="https://cdn.jsdelivr.net/npm/lucide@0.400.0/dist/umd/lucide.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
  <script>
    window.addEventListener('load', function () {
      if (!window.lucide) {
        var s = document.createElement('script');
        s.src = 'https://unpkg.com/lucide@0.400.0/dist/umd/lucide.min.js';
        s.async = true;
        s.onload = function () { if (typeof refreshIcons === 'function') refreshIcons(); };
        document.head.appendChild(s);
      }
    });
  </script>
  <link href="style.css?v=<?= (int) filemtime(__DIR__ . '/style.css') ?>" rel="stylesheet">
  <?php if (!empty($themeCssVars)): ?>
    <style id="theme-colors">
      :root {
        <?php foreach ($themeCssVars as $k => $v): ?>
          <?= $k ?>
          :
            <?= htmlspecialchars($v, ENT_QUOTES) ?>
          ;
        <?php endforeach; ?>
      }
    </style>
  <?php endif; ?>
</head>

<body>

  <!-- Toast -->
  <div id="toast-box"></div>

  <?php if (!$is_logged): ?>
    <!-- ============ LOGIN ============ -->
    <div class="login-wrap">
      <div class="login-card">
        <div class="login-brand">
          <div class="brand-mark logo-1x1">
            <img src="https://cdn.9sty.com/uploads/logo_akhbaralmghrib.svg" alt="أخبار المغرب" class="brand-logo-img">
          </div>
          <h1>أخبار المغرب</h1>
          <p><?= !$adminExists ? 'إعداد حساب المدير الأول' : 'تسجيل الدخول إلى لوحة التحكم' ?></p>
        </div>

        <?php if ($error !== ''): ?>
          <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$adminExists): ?>
          <form method="POST" action="index.php" class="login-form">
            <div class="form-group">
              <label for="reg-username">اسم المستخدم</label>
              <input type="text" name="username" id="reg-username" class="form-control" required placeholder="admin">
            </div>
            <div class="form-group">
              <label for="reg-email">البريد الإلكتروني</label>
              <input type="email" name="email" id="reg-email" class="form-control" required placeholder="admin@9sty.com">
            </div>
            <div class="form-group">
              <label for="reg-password">كلمة المرور</label>
              <input type="password" name="password" id="reg-password" class="form-control" required placeholder="••••••••">
            </div>
            <button type="submit" name="setup_admin" class="btn">
              <i data-lucide="shield-check"></i>
              إنشاء حساب المدير
            </button>
          </form>
          <div class="login-tip">
            <i data-lucide="lock"></i>
            يُحفظ الحساب مشفّراً بشكل آمن في قاعدة البيانات
          </div>
        <?php else: ?>
          <form method="POST" action="index.php" class="login-form">
            <div class="form-group">
              <label for="login-user">اسم المستخدم أو البريد</label>
              <input type="text" name="username" id="login-user" class="form-control" required placeholder="admin"
                autofocus>
            </div>
            <div class="form-group">
              <label for="login-pass">كلمة المرور</label>
              <input type="password" name="password" id="login-pass" class="form-control" required placeholder="••••••••">
            </div>
            <button type="submit" name="login" class="btn">
              <i data-lucide="log-in"></i>
              تسجيل الدخول
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

  <?php else: ?>
    <!-- ============ DASHBOARD ============ -->
    <div class="app-shell">

      <div class="mobile-overlay" id="mobile-overlay" onclick="closeMobileMenu()"></div>

      <!-- Sidebar -->
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
          <div class="brand-mark logo-1x1">
            <img src="https://cdn.9sty.com/uploads/logo_akhbaralmghrib.svg" alt="أخبار المغرب" class="brand-logo-img">
          </div>
          <div class="brand-text">
            <span class="brand-name">أخبار المغرب</span>
            <span class="brand-tag">لوحة التحكم</span>
          </div>
        </div>

        <div class="sidebar-section">
          <span class="section-label">الرئيسية</span>
          <nav class="nav-list">
            <a class="nav-item active" id="menu-overview" onclick="loadSection('overview')">
              <span class="nav-icon"><i data-lucide="layout-dashboard"></i></span>
              <span>لوحة القيادة</span>
            </a>
            <a class="nav-item" id="menu-analytics" onclick="loadSection('analytics')">
              <span class="nav-icon"><i data-lucide="line-chart"></i></span>
              <span>التحليلات والإحصائيات</span>
            </a>
            <a class="nav-item" id="menu-posts" onclick="loadSection('posts')">
              <span class="nav-icon"><i data-lucide="newspaper"></i></span>
              <span>الأخبار والمقالات</span>
            </a>
            <a class="nav-item" id="menu-shortposts" onclick="loadSection('shortposts')">
              <span class="nav-icon"><i data-lucide="zap"></i></span>
              <span>المنشورات القصيرة</span>
            </a>
            <a class="nav-item" id="menu-categories" onclick="loadSection('categories')">
              <span class="nav-icon"><i data-lucide="folder-tree"></i></span>
              <span>الأقسام</span>
            </a>
          </nav>
        </div>

        <div class="sidebar-section">
          <span class="section-label">الإدارة</span>
          <nav class="nav-list">
            <a class="nav-item" id="menu-adsense" onclick="loadSection('adsense')">
              <span class="nav-icon"><i data-lucide="megaphone"></i></span>
              <span>إعلانات أدسنس</span>
            </a>
            <a class="nav-item" id="menu-banners" onclick="loadSection('banners')">
              <span class="nav-icon"><i data-lucide="image"></i></span>
              <span>البنرات المخصصة</span>
            </a>
            <a class="nav-item" id="menu-comments" onclick="loadSection('comments')">
              <span class="nav-icon"><i data-lucide="messages-square"></i></span>
              <span>التعليقات</span>
            </a>
            <a class="nav-item" id="menu-users" onclick="loadSection('users')">
              <span class="nav-icon"><i data-lucide="users"></i></span>
              <span>المستخدمون</span>
            </a>
            <a class="nav-item" id="menu-shortlinks" onclick="loadSection('shortlinks')">
              <span class="nav-icon"><i data-lucide="link-2"></i></span>
              <span>الروابط المختصرة</span>
            </a>
            <a class="nav-item" id="menu-shares" onclick="loadSection('shares')">
              <span class="nav-icon"><i data-lucide="share-2"></i></span>
              <span>تتبع المشاركات (SI)</span>
            </a>
          </nav>
        </div>
        
        <div class="sidebar-section">
          <span class="section-label">النظام</span>
          <nav class="nav-list">
            <a class="nav-item" id="menu-trash" onclick="loadSection('trash')">
              <span class="nav-icon"><i data-lucide="trash-2"></i></span>
              <span>سلة المهملات</span>
            </a>
            <a class="nav-item" id="menu-settings" onclick="loadSection('settings')">
              <span class="nav-icon"><i data-lucide="settings"></i></span>
              <span>إعدادات التتبع</span>
            </a>
          </nav>
        </div>

        <div class="sidebar-footer">
          <div class="user-card">
            <div class="avatar"><?= htmlspecialchars($admin_initial) ?></div>
            <div class="user-card-info">
              <span class="user-card-name"><?= htmlspecialchars($admin_username) ?></span>
              <span class="user-card-role">مدير النظام</span>
            </div>
          </div>
          <button class="sidebar-btn" onclick="toggleTheme()" id="theme-toggle-btn">
            <i data-lucide="moon"></i>
            <span>الوضع الداكن</span>
          </button>
          <a href="index.php?logout=1" class="sidebar-btn danger">
            <i data-lucide="log-out"></i>
            <span>تسجيل الخروج</span>
          </a>
        </div>
      </aside>

      <!-- Main -->
      <div class="main-area">
        <header class="topbar">
          <div class="topbar-left">
            <button class="mobile-menu-btn" onclick="openMobileMenu()">
              <i data-lucide="menu"></i>
            </button>
            <div class="page-heading">
              <span class="page-title" id="page-title">لوحة القيادة</span>
              <span class="page-subtitle" id="page-subtitle">نظرة سريعة على أداء الموقع</span>
            </div>
          </div>
          <div class="topbar-right">
            <button class="icon-btn keep-on-mobile" onclick="location.reload()" title="تحديث">
              <i data-lucide="refresh-cw"></i>
            </button>
            <a href="/" target="_blank" class="icon-btn" title="عرض الموقع">
              <i data-lucide="external-link"></i>
            </a>
          </div>
        </header>

        <main class="content-area" id="main-content"></main>
      </div>
    </div>

    <!-- ============ MODALS ============ -->

    <!-- Category -->
    <div class="modal-backdrop" id="modal-category">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-header-left">
            <div class="modal-icon"><i data-lucide="folder-plus"></i></div>
            <h3 id="cat-modal-title">إضافة قسم جديد</h3>
          </div>
          <button class="modal-close" onclick="closeModal('category')"><i data-lucide="x"></i></button>
        </div>
        <form id="form-category" onsubmit="saveCategory(event)">
          <div class="modal-body">
            <input type="hidden" name="id" id="cat-id">
            <div class="form-group">
              <label>اسم القسم</label>
              <input type="text" name="name" id="cat-name" class="form-control" required placeholder="أخبار وطنية">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
              <div class="form-group">
                <label>الحالة</label>
                <select name="status" id="cat-status" class="form-control">
                  <option value="active">نشط</option>
                  <option value="inactive">معطل</option>
                </select>
              </div>
              <div class="form-group">
                <label>في الهيدر</label>
                <select name="show_in_header" id="cat-show-in-header" class="form-control">
                  <option value="1">ظاهر</option>
                  <option value="0">مخفي</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Meta Title</label>
              <input type="text" name="meta_title" id="cat-meta-title" class="form-control" placeholder="عنوان الميتا...">
            </div>
            <div class="form-group">
              <label>Meta Description</label>
              <textarea name="meta_description" id="cat-meta-desc" class="form-control" rows="3"
                placeholder="وصف محرك البحث..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('category')">إلغاء</button>
            <button type="submit" class="btn">حفظ القسم</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ShortLink -->
    <div class="modal-backdrop" id="modal-shortlink">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-header-left">
            <div class="modal-icon"><i data-lucide="link"></i></div>
            <h3 id="sl-modal-title">إضافة رابط مختصر</h3>
          </div>
          <button class="modal-close" onclick="closeModal('shortlink')"><i data-lucide="x"></i></button>
        </div>
        <form id="form-shortlink" onsubmit="saveShortLink(event)">
          <div class="modal-body">
            <input type="hidden" name="id" id="sl-id">
            <div class="form-group">
              <label>الرابط المستهدف</label>
              <input type="url" name="destination_url" id="sl-dest-url" class="form-control" required
                placeholder="https://example.com/long/link">
            </div>
            <div class="form-group">
              <label>الكود المختصر (اختياري)</label>
              <input type="text" name="short_code" id="sl-code" class="form-control" placeholder="mylink">
              <small style="font-size:11px; color:var(--text-muted); display:block; margin-top:6px;">
                الناتج: <code>https://9sty.com/s/الكود</code>
              </small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('shortlink')">إلغاء</button>
            <button type="submit" class="btn">حفظ الرابط</button>
          </div>
        </form>
      </div>
    </div>

    <!-- AdSense -->
    <div class="modal-backdrop" id="modal-adsense">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-header-left">
            <div class="modal-icon"><i data-lucide="megaphone"></i></div>
            <h3 id="ad-modal-title">تعديل الإعلان</h3>
          </div>
          <button class="modal-close" onclick="closeModal('adsense')"><i data-lucide="x"></i></button>
        </div>
        <form id="form-adsense" onsubmit="saveAdsense(event)">
          <div class="modal-body">
            <input type="hidden" name="id" id="ad-id">
            <div class="form-group">
              <label>اسم المساحة (Slot Name)</label>
              <input type="text" name="slot_name" id="ad-slot-name" class="form-control" required
                placeholder="sidebar_top">
            </div>
            <div class="form-group">
              <label>كود مخصص (HTML/JS) — يتجاهل حقول أدسنس</label>
              <textarea name="code" id="ad-code" class="form-control" rows="3"
                placeholder="<script>...</script>"></textarea>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
              <div class="form-group">
                <label>Publisher ID</label>
                <input type="text" name="ad_client" id="ad-client" class="form-control" placeholder="ca-pub-XXXX">
              </div>
              <div class="form-group">
                <label>Ad Slot ID</label>
                <input type="text" name="ad_slot" id="ad-slot" class="form-control" placeholder="1234567890">
              </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
              <div class="form-group">
                <label>الصيغة</label>
                <select name="ad_format" id="ad-format" class="form-control">
                  <option value="auto">auto</option>
                  <option value="horizontal">horizontal</option>
                  <option value="vertical">vertical</option>
                  <option value="rectangle">rectangle</option>
                </select>
              </div>
              <div class="form-group">
                <label>الحالة</label>
                <select name="is_active" id="ad-active" class="form-control">
                  <option value="1">نشط</option>
                  <option value="0">معطل</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('adsense')">إلغاء</button>
            <button type="submit" class="btn">حفظ</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Banner -->
    <div class="modal-backdrop" id="modal-banner">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-header-left">
            <div class="modal-icon"><i data-lucide="image"></i></div>
            <h3 id="banner-modal-title">إضافة بنر مخصص</h3>
          </div>
          <button class="modal-close" onclick="closeModal('banner')"><i data-lucide="x"></i></button>
        </div>
        <form id="form-banner" onsubmit="saveBanner(event)">
          <div class="modal-body">
            <input type="hidden" name="id" id="banner-id">
            <div class="form-group">
              <label>اسم البنر</label>
              <input type="text" name="name" id="banner-name" class="form-control" required
                placeholder="عسل السدر الأصلي">
            </div>
            <div class="form-group">
              <label>رفع صورة</label>
              <input type="file" name="image" id="banner-image-file" class="form-control" accept="image/*">
            </div>
            <div class="form-group">
              <label>معاينة الصورة</label>
              <div id="banner-image-preview" class="banner-image-preview">
                <div class="banner-image-preview-empty">
                  <i data-lucide="image"></i>
                  <span>لا توجد صورة بعد</span>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>أو رابط صورة خارجي</label>
              <input type="text" name="image_url" id="banner-image-url" class="form-control"
                placeholder="https://example.com/image.png">
            </div>
            <div class="form-group">
              <label>رابط التوجيه</label>
              <input type="text" name="url" id="banner-url" class="form-control" required
                placeholder="https://janabio.com/promo">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
              <div class="form-group">
                <label>السعر</label>
                <input type="text" name="price" id="banner-price" class="form-control" placeholder="199">
              </div>
              <div class="form-group">
                <label>السعر قبل الخصم</label>
                <input type="text" name="promo_price" id="banner-promo-price" class="form-control" placeholder="299">
              </div>
            </div>
            <div class="form-group">
              <label>الحالة</label>
              <select name="is_active" id="banner-active" class="form-control">
                <option value="1">نشط</option>
                <option value="0">معطل</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('banner')">إلغاء</button>
            <button type="submit" class="btn">حفظ البنر</button>
          </div>
        </form>
      </div>
    </div>

    <!-- User -->
    <div class="modal-backdrop" id="modal-user">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-header-left">
            <div class="modal-icon"><i data-lucide="user-plus"></i></div>
            <h3 id="user-modal-title">إضافة مستخدم</h3>
          </div>
          <button class="modal-close" onclick="closeModal('user')"><i data-lucide="x"></i></button>
        </div>
        <form id="form-user" onsubmit="saveUser(event)">
          <div class="modal-body">
            <input type="hidden" name="id" id="user-id">
            <div class="form-group">
              <label>اسم المستخدم</label>
              <input type="text" name="username" id="user-username" class="form-control" required placeholder="manager">
            </div>
            <div class="form-group">
              <label>البريد الإلكتروني</label>
              <input type="email" name="email" id="user-email" class="form-control" required
                placeholder="manager@9sty.com">
            </div>
            <div class="form-group">
              <label>كلمة المرور</label>
              <input type="password" name="password" id="user-password" class="form-control" placeholder="••••••••">
              <small style="font-size:11px; color:var(--text-muted); display:block; margin-top:6px;">اتركها فارغة للحفاظ
                على القديمة</small>
            </div>
            <div class="form-group">
              <label>الصلاحية</label>
              <select name="role" id="user-role" class="form-control">
                <option value="1">مدير (Admin)</option>
                <option value="0">محرر (Editor)</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('user')">إلغاء</button>
            <button type="submit" class="btn">حفظ الحساب</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Comment -->
    <div class="modal-backdrop" id="modal-comment">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-header-left">
            <div class="modal-icon"><i data-lucide="message-square"></i></div>
            <h3 id="comment-modal-title">تعديل التعليق</h3>
          </div>
          <button class="modal-close" onclick="closeModal('comment')"><i data-lucide="x"></i></button>
        </div>
        <form id="form-comment" onsubmit="saveComment(event)">
          <div class="modal-body">
            <input type="hidden" name="id" id="comment-id">
            <div class="form-group">
              <label>اسم الكاتب</label>
              <input type="text" name="name" id="comment-name" class="form-control" required>
            </div>
            <div class="form-group">
              <label>الحالة</label>
              <select name="status" id="comment-status" class="form-control">
                <option value="approved">ظاهر</option>
                <option value="hidden">مخفي</option>
              </select>
            </div>
            <div class="form-group">
              <label>نص التعليق</label>
              <textarea name="comment" id="comment-text" class="form-control" rows="5" required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('comment')">إلغاء</button>
            <button type="submit" class="btn">حفظ</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ============ JAVASCRIPT ============ -->
    <script src="script.js?v=<?= (int) filemtime(__DIR__ . '/script.js') ?>"></script>
  <?php endif; ?>

</body>

</html>
