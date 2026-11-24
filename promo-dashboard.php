<?php
/**
 * promo-dashboard.php — Test dashboard bsit bach tchouf statistiques
 * dyal jb_promo_clicks. Zid authentification (login check) 3la had
 * page 9bel ma trfaha l production, hiya dabа bla proteciton bach
 * testeha.
 */
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
    'path' => '/',
]);
session_start();
if (empty($_SESSION['admin_logged']) || (int) ($_SESSION['admin_role'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Forbidden');
}
require __DIR__ . '/db.php';
$pdo = get_db();

$totalClicks = $pdo->query("SELECT COUNT(*) FROM jb_promo_clicks")->fetchColumn();
$last7 = $pdo->query("SELECT COUNT(*) FROM jb_promo_clicks WHERE clicked_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();

$byDevice = $pdo->query("
    SELECT device_type, COUNT(*) AS c
    FROM jb_promo_clicks
    GROUP BY device_type
    ORDER BY c DESC
")->fetchAll();

$byProduct = $pdo->query("
    SELECT product_id, COUNT(*) AS c
    FROM jb_promo_clicks
    GROUP BY product_id
    ORDER BY c DESC
    LIMIT 10
")->fetchAll();

// Top posts li kayjibo clicks (JOIN m3a news_posts bach n3rfo l3nwan)
$byPost = $pdo->query("
    SELECT jc.post_id, p.title, p.slug, COUNT(*) AS c
    FROM jb_promo_clicks jc
    LEFT JOIN news_posts p ON p.id = jc.post_id
    WHERE jc.post_id IS NOT NULL
    GROUP BY jc.post_id, p.title, p.slug
    ORDER BY c DESC
    LIMIT 10
")->fetchAll();

$recent = $pdo->query("
    SELECT jc.*, p.title AS post_title
    FROM jb_promo_clicks jc
    LEFT JOIN news_posts p ON p.id = jc.post_id
    ORDER BY jc.clicked_at DESC
    LIMIT 25
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>Dashboard — Promo Clicks</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  body{font-family:'Cairo',sans-serif;background:#f4f5f7;margin:0;padding:24px;color:#222;}
  h1{font-size:20px;margin-bottom:20px;}
  .stats{display:flex;gap:14px;margin-bottom:24px;flex-wrap:wrap;}
  .stat-card{background:#fff;border-radius:10px;padding:16px 22px;box-shadow:0 1px 4px rgba(0,0,0,.08);min-width:140px;}
  .stat-card .num{font-size:26px;font-weight:800;color:#A32638;}
  .stat-card .label{font-size:12px;color:#777;}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:28px;}
  th,td{padding:9px 12px;text-align:right;font-size:13px;border-bottom:1px solid #eee;}
  th{background:#f1ecel;background:#f1ecdf;font-weight:700;}
  h2{font-size:15px;margin:28px 0 10px;}
  .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;background:#eee;}
</style>
</head>
<body>

<h1>📊 Dashboard — Clicks على بانرات JanaBio</h1>

<div class="stats">
  <div class="stat-card"><div class="num"><?= number_format($totalClicks) ?></div><div class="label">مجموع الكليكات</div></div>
  <div class="stat-card"><div class="num"><?= number_format($last7) ?></div><div class="label">آخر 7 أيام</div></div>
</div>

<h2>حسب الجهاز</h2>
<table>
  <tr><th>الجهاز</th><th>عدد الكليكات</th></tr>
  <?php foreach ($byDevice as $d): ?>
    <tr><td><?= htmlspecialchars($d['device_type']) ?></td><td><?= number_format($d['c']) ?></td></tr>
  <?php endforeach; ?>
</table>

<h2>أكثر المنتجات كليكا (Top 10)</h2>
<table>
  <tr><th>Product ID</th><th>عدد الكليكات</th></tr>
  <?php foreach ($byProduct as $p): ?>
    <tr><td><?= htmlspecialchars($p['product_id']) ?></td><td><?= number_format($p['c']) ?></td></tr>
  <?php endforeach; ?>
</table>

<h2>أكثر المقالات جلبا للكليكات (Top 10)</h2>
<table>
  <tr><th>المقال</th><th>عدد الكليكات</th></tr>
  <?php foreach ($byPost as $p): ?>
    <tr>
      <td><?= $p['title'] ? htmlspecialchars($p['title']) : ('#' . $p['post_id']) ?></td>
      <td><?= number_format($p['c']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<h2>آخر 25 كليك</h2>
<table>
  <tr><th>الوقت</th><th>Product</th><th>المقال</th><th>الجهاز</th><th>الصفحة</th></tr>
  <?php foreach ($recent as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['clicked_at']) ?></td>
      <td><?= htmlspecialchars($r['product_id']) ?></td>
      <td><?= $r['post_title'] ? htmlspecialchars($r['post_title']) : '—' ?></td>
      <td><span class="badge"><?= htmlspecialchars($r['device_type']) ?></span></td>
      <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['page_url'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
</table>

</body>
</html>
