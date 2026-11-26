<?php
declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
    'path' => '/',
]);
session_start();

if (!isset($_SESSION['admin_logged']) || (int) ($_SESSION['admin_role'] ?? 0) !== 1) {
    if (!isset($_SESSION['admin_logged'])) {
        header('Location: /dashbarod/index.php');
        exit;
    }
    http_response_code(403);
    exit('403 Forbidden: Admin access required.');
}

require_once __DIR__ . '/db.php';
$pdo = get_db();
$themeCssVars = function_exists('ja_theme_css_vars') ? ja_theme_css_vars() : [];
$baseUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '9sty.com');

$endpoints = [
    [
        'group' => 'Posts (المقالات)',
        'items' => [
            ['GET', '/api/posts/read', 'جلب قائمة المقالات أو مقال محدد بـ id / slug', 'page, per_page, status, category_id, post_type, search, id, slug', null, '{"success":true,"data":[{"id":1,"title":"عنوان الخبر","short_code":"Uhoia"}]}'],
            ['POST', '/api/posts/create', 'إنشاء مقال أو خبر جديد (مطلوب title)', 'title*, content, excerpt, category_id, image, status, post_type, slug', '{"title":"خبر عاجل جديد","content":"تفاصيل الخبر...","category_id":1,"status":"published"}', '{"success":true,"data":{"id":695,"short_code":"Ab12C"}}'],
            ['PUT / POST', '/api/posts/update', 'تحديث مقال موجود عبر id', 'id*, title, content, excerpt, category_id, image, status, slug', '{"id":695,"title":"عنوان محدث","status":"published"}', '{"success":true,"message":"Post updated.","id":695}'],
            ['DELETE / POST', '/api/posts/delete', 'حذف ناعم (Soft Delete عبر deleted_at)', 'id*', '{"id":695}', '{"success":true,"message":"Post soft-deleted.","id":695}'],
        ],
    ],
    [
        'group' => 'Categories (الأقسام)',
        'items' => [
            ['GET', '/api/categories/read', 'جلب جميع الأقسام أو قسم عبر id', 'id', null, '{"success":true,"data":[{"id":1,"name":"أخبار","slug":"news"}]}'],
            ['POST', '/api/categories/create', 'إضافة قسم جديد', 'name*, slug, status, show_in_header, meta_title, meta_description', '{"name":"اقتصاد","status":"active","show_in_header":1}', '{"success":true,"data":{"id":62,"slug":"اقتصاد"}}'],
            ['PUT / POST', '/api/categories/update', 'تعديل قسم عبر id', 'id*, name, slug, status, show_in_header', '{"id":62,"name":"مال وأعمال"}', '{"success":true,"message":"Category updated.","id":62}'],
            ['DELETE / POST', '/api/categories/delete', 'حذف ناعم للقسم (deleted_at)', 'id*', '{"id":62}', '{"success":true,"message":"Category soft-deleted.","id":62}'],
        ],
    ],
    [
        'group' => 'Banners (البنرات المخصصة)',
        'items' => [
            ['GET', '/api/banners/read', 'جلب البنرات المخصصة النشطة', 'id', null, '{"success":true,"data":[{"id":10,"slug":"VYi8dvxx5lh9","name":"lamsae"}]}'],
            ['POST', '/api/banners/create', 'إنشاء بنر مخصص جديد', 'name*, url*, image*, price, promo_price, is_active', '{"name":"عرض خاص","url":"https://example.com","image":"https://cdn.9sty.com/uploads/img.jpg","price":"199"}', '{"success":true,"data":{"id":11,"slug":"xK9mPq2Ab1"}}'],
            ['PUT / POST', '/api/banners/update', 'تعديل بنر عبر id', 'id*, name, url, image, price, promo_price, is_active', '{"id":11,"is_active":0}', '{"success":true,"message":"Banner updated.","id":11}'],
            ['DELETE / POST', '/api/banners/delete', 'حذف ناعم للبنر (deleted_at)', 'id*', '{"id":11}', '{"success":true,"message":"Banner soft-deleted.","id":11}'],
        ],
    ],
    [
        'group' => 'Short Links (الروابط المختصرة)',
        'items' => [
            ['GET', '/api/shortlinks/read', 'جلب الروابط المختصرة', 'id', null, '{"success":true,"data":[{"id":1,"short_code":"abc12","destination_url":"https://9sty.com"}]}'],
            ['POST', '/api/shortlinks/create', 'إنشاء رابط مختصر جديد', 'destination_url*, short_code', '{"destination_url":"https://9sty.com/post-slug"}', '{"success":true,"data":{"id":136,"short_code":"Xy7zQ","short_url":"https://9sty.com/s/Xy7zQ"}}'],
            ['PUT / POST', '/api/shortlinks/update', 'تعديل رابط مختصر عبر id', 'id*, short_code, destination_url', '{"id":136,"destination_url":"https://9sty.com/new"}', '{"success":true,"message":"Short link updated.","id":136}'],
            ['DELETE / POST', '/api/shortlinks/delete', 'حذف ناعم للرابط (deleted_at)', 'id*', '{"id":136}', '{"success":true,"message":"Short link soft-deleted.","id":136}'],
        ],
    ],
    [
        'group' => 'Comments (التعليقات)',
        'items' => [
            ['GET', '/api/comments/read', 'جلب التعليقات (أو حسب post_id / id)', 'id, post_id', null, '{"success":true,"data":[{"id":1,"post_id":10,"name":"أحمد","status":"approved"}]}'],
            ['POST', '/api/comments/create', 'إضافة تعليق جديد', 'post_id*, name*, comment*, email, parent_id, status', '{"post_id":10,"name":"أحمد","comment":"مقال ممتاز!"}', '{"success":true,"data":{"id":540}}'],
            ['PUT / POST', '/api/comments/update', 'تعديل حالة أو نص تعليق', 'id*, name, email, comment, status', '{"id":540,"status":"hidden"}', '{"success":true,"message":"Comment updated.","id":540}'],
            ['DELETE / POST', '/api/comments/delete', 'حذف ناعم للتعليق (deleted_at)', 'id*', '{"id":540}', '{"success":true,"message":"Comment soft-deleted.","id":540}'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>توثيق ومفاتيح REST API | لوحة التحكم</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script defer src="https://cdn.jsdelivr.net/npm/lucide@0.400.0/dist/umd/lucide.min.js"></script>
  <link href="/dashbarod/style.css?v=<?= (int) filemtime(__DIR__ . '/style.css') ?>" rel="stylesheet">
  <?php if (!empty($themeCssVars)): ?>
    <style id="theme-colors">:root{<?php foreach ($themeCssVars as $k => $v): ?><?= $k ?>:<?= htmlspecialchars($v, ENT_QUOTES) ?>;<?php endforeach; ?>}</style>
  <?php endif; ?>
  <style>
    body{background:var(--bg);color:var(--text);font-family:'Cairo','Outfit',sans-serif;margin:0}
    .docs-wrap{max-width:1180px;margin:0 auto;padding:24px 20px 60px}
    .docs-top{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:24px}
    .code-box{background:#0f172a;color:#e2e8f0;padding:12px 14px;border-radius:10px;font-family:monospace;font-size:12.5px;direction:ltr;text-align:left;overflow-x:auto;position:relative;margin:8px 0}
    .copy-mini{position:absolute;top:8px;right:8px;background:#334155;color:#fff;border:none;border-radius:6px;padding:4px 9px;font-size:11px;cursor:pointer}
    .copy-mini:hover{background:#4f46e5}
    .ep-card{border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:14px;background:var(--surface)}
    .ep-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;direction:ltr;justify-content:flex-end}
    .ep-method{font-weight:800;font-size:12px;padding:3px 9px;border-radius:6px;background:var(--brand-50);color:var(--brand-600)}
    .ep-path{font-family:monospace;font-weight:700;font-size:14px}
    .key-reveal{background:#ecfdf5;border:1px solid #10b981;color:#065f46;padding:14px;border-radius:10px;margin-top:12px;display:none}
  </style>
</head>
<body>
  <div class="docs-wrap">
    <div class="docs-top">
      <div>
        <h1 style="margin:0;font-size:22px;display:flex;align-items:center;gap:8px;"><i data-lucide="book-open-check"></i> توثيق ومفاتيح REST API</h1>
        <p style="margin:4px 0 0;color:var(--text-muted);font-size:13px;">واجهة JSON محمية للتكامل مع <strong>n8n</strong> والأنظمة الخارجية — جميع العمليات تُسجَّل تلقائياً في سجل API.</p>
      </div>
      <div style="display:flex;gap:10px;">
        <a href="/dashbarod/index.php" class="btn btn-secondary"><i data-lucide="arrow-right"></i> العودة للوحة التحكم</a>
      </div>
    </div>

    <!-- API Key Manager -->
    <div class="card" style="margin-bottom:22px;">
      <div class="card-header">
        <h2 style="display:flex;align-items:center;gap:8px;"><i data-lucide="key-round"></i> إدارة مفاتيح API</h2>
      </div>
      <form id="create-key-form" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <input type="text" id="new-key-name" class="form-control" placeholder="اسم المفتاح (مثال: n8n Automation)" style="max-width:320px;" required>
        <button type="submit" class="btn"><i data-lucide="plus"></i> إنشاء مفتاح جديد</button>
      </form>
      <div id="key-reveal" class="key-reveal">
        <strong>تم إنشاء المفتاح! انسخه الآن فلن يظهر كاملاً مجدداً:</strong>
        <div class="code-box" style="margin-top:8px;">
          <button type="button" class="copy-mini" onclick="copyText(document.getElementById('raw-key-val').textContent, this)">نسخ</button>
          <span id="raw-key-val"></span>
        </div>
      </div>
      <div class="table-responsive" style="margin-top:14px;">
        <table>
          <thead><tr><th>ID</th><th>الاسم</th><th>البادئة</th><th>الحالة</th><th>آخر استخدام</th><th>تاريخ الإنشاء</th><th>إجراء</th></tr></thead>
          <tbody id="keys-tbody"><tr><td colspan="7">جاري التحميل...</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- Auth Guide -->
    <div class="card" style="margin-bottom:22px;">
      <div class="card-header"><h2 style="display:flex;align-items:center;gap:8px;"><i data-lucide="shield-check"></i> المصادقة (Authentication) وإعداد n8n</h2></div>
      <p style="font-size:13.5px;color:var(--text-muted);">جميع مسارات <code>/api/*</code> تتطلب مفتاح API صالحاً عبر أحد الهيدرز التالية (بدون مفتاح يتم إرجاع <code>401 Unauthorized</code>):</p>
      <div class="code-box">Authorization: Bearer YOUR_API_KEY
# أو
X-API-Key: YOUR_API_KEY</div>
      <p style="font-size:13px;color:var(--text-muted);margin-top:10px;">
        <strong>في n8n (HTTP Request Node):</strong> اختر <em>Authentication → Generic Credential Type → Header Auth</em>، ثم ضع Name: <code>X-API-Key</code> و Value: مفتاحك، وفعل <em>Send Body → JSON</em> عند استخدام POST/PUT/DELETE.
      </p>
    </div>

    <!-- Endpoints -->
    <?php foreach ($endpoints as $group): ?>
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h2><?= htmlspecialchars($group['group']) ?></h2></div>
        <?php foreach ($group['items'] as [$method, $path, $desc, $params, $reqBody, $resBody]):
          $primaryMethod = explode(' ', $method)[0];
          $curl = "curl -X $primaryMethod \"" . $baseUrl . $path . "\" \\\n  -H \"X-API-Key: YOUR_API_KEY\" \\\n  -H \"Content-Type: application/json\"";
          if ($reqBody !== null) {
              $curl .= " \\\n  -d '" . $reqBody . "'";
          }
        ?>
          <div class="ep-card">
            <div class="ep-head">
              <span class="ep-path"><?= htmlspecialchars($path) ?></span>
              <span class="ep-method"><?= htmlspecialchars($method) ?></span>
            </div>
            <p style="margin:0 0 8px;font-size:13.5px;"><?= htmlspecialchars($desc) ?></p>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;"><strong>المعاملات (* إجباري):</strong> <code><?= htmlspecialchars($params) ?></code></div>
            <div class="code-box">
              <button type="button" class="copy-mini" onclick="copyText(this.nextElementSibling.textContent, this)">نسخ cURL</button>
              <pre style="margin:0;white-space:pre-wrap;"><?= htmlspecialchars($curl) ?></pre>
            </div>
            <div class="code-box" style="background:#1e293b;">
              <pre style="margin:0;white-space:pre-wrap;">Response: <?= htmlspecialchars($resBody) ?></pre>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
  function copyText(txt, btn) {
    navigator.clipboard.writeText(txt).then(() => {
      const old = btn.textContent;
      btn.textContent = 'تم النسخ';
      setTimeout(() => btn.textContent = old, 1400);
    });
  }
  function loadKeys() {
    fetch('/dashbarod/api.php?action=list_api_keys')
      .then(r => r.json())
      .then(d => {
        const tb = document.getElementById('keys-tbody');
        if (!d.success || !d.keys.length) {
          tb.innerHTML = '<tr><td colspan="7">لا توجد مفاتيح بعد</td></tr>';
          return;
        }
        tb.innerHTML = d.keys.map(k => `
          <tr>
            <td>#${k.id}</td>
            <td><strong>${k.name}</strong></td>
            <td style="direction:ltr;text-align:right;"><code>${k.key_prefix}</code></td>
            <td><span class="badge ${k.is_active == 1 ? 'active' : 'inactive'}">${k.is_active == 1 ? 'نشط' : 'ملغى'}</span></td>
            <td style="font-size:12px;">${k.last_used_at || '—'}</td>
            <td style="font-size:12px;">${k.created_at}</td>
            <td>${k.is_active == 1 ? `<button class="btn btn-secondary" style="padding:4px 10px;font-size:12px;" onclick="revokeKey(${k.id})">إلغاء</button>` : '—'}</td>
          </tr>
        `).join('');
      });
  }
  function revokeKey(id) {
    if (!confirm('هل تريد إلغاء هذا المفتاح؟ لن يعمل في n8n بعد الآن.')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('/dashbarod/api.php?action=revoke_api_key', { method: 'POST', body: fd })
      .then(r => r.json()).then(() => loadKeys());
  }
  document.getElementById('create-key-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('name', document.getElementById('new-key-name').value);
    fetch('/dashbarod/api.php?action=create_api_key', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.success && d.api_key) {
          document.getElementById('raw-key-val').textContent = d.api_key;
          document.getElementById('key-reveal').style.display = 'block';
          document.getElementById('new-key-name').value = '';
          loadKeys();
        } else {
          alert(d.message || 'خطأ');
        }
      });
  });
  document.addEventListener('DOMContentLoaded', function () {
    loadKeys();
    if (window.lucide) window.lucide.createIcons();
  });
  </script>
</body>
</html>
