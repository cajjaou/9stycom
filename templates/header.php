<?php
/**
 * templates/header.php — Partagé bin home.php / post.php / category.php.
 * Variables mn9adin lih: $pageTitle (optionnel), $showTicker (optionnel bool).
 * $pdo khass ykon mawjoud (mjib mn index.php).
 */
require_once __DIR__ . '/../seo-helper.php';

$navCategories = $pdo->query("
    SELECT name, slug FROM news_categories
    WHERE status = 'active' AND (show_in_header IS NULL OR show_in_header = 1)
    ORDER BY id ASC
")->fetchAll();

// Retrieve active AdSense client ID from database
$ads = get_adsense_ads();
$adClient = 'ca-pub-1234567890123456'; // Default fallback publisher ID
if (!empty($ads)) {
    $firstAd = reset($ads);
    if (!empty($firstAd['ad_client'])) {
        $adClient = $firstAd['ad_client'];
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta name="p:domain_verify" content="377c5e6bb787dc3ade089adb2fc853ad"/>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= render_seo_tags() ?>
<script>
(function() {
    var theme = localStorage.getItem('theme');
    if (theme === 'dark') {
        document.documentElement.classList.add('dark-mode');
    } else {
        document.documentElement.classList.remove('dark-mode');
    }
})();
</script>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($adClient) ?>" crossorigin="anonymous"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script defer src="https://cdn.jsdelivr.net/npm/lucide@0.400.0/dist/umd/lucide.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="/assets/css/social-share.css">
<?= render_tracking_codes() ?>
<style>
  :root {
    /* Light Theme (Default - Adapted from your custom colors) */
    --paper: #F0F4F6;             /* Clean ice-white background */
    --ink: #1B3651;               /* Dark Navy primary text */
    --ink-soft: #494949;          /* Medium Gray secondary text */
    --line: #C2D7DE;              /* Ice Blue borders */
    --accent: #1B3651;            /* Dark Navy primary accent */
    --panel: #E5ECF0;             /* Soft light panel background */
    --white: #ffffff;             /* Pure white card backgrounds */
    
    /* Layout Helpers in Light Mode */
    --logo-accent: #7AA1A2;       /* Sage Teal logo highlight */
    --topbar-bg: #1B3651;         /* Dark Navy topbar */
    --topbar-text: #C2D7DE;       /* Ice Blue topbar text */
    --ticker-bg: #F4CE28;         /* Bright Yellow ticker */
    --ticker-text: #1B3651;       /* Navy ticker text */
    --btn-text: #ffffff;          /* White text on Navy buttons */
    --comments-border: #C2D7DE;   /* Ice Blue comments divider */
    --article-text: #1B3651;      /* Navy text inside article paragraphs */
    
    /* Light Theme Skeletons (Sage Teal/Ice Blue) */
    --shimmer-start: #E5ECF0;
    --shimmer-middle: #D6E2E6;
    --shimmer-end: #E5ECF0;
  }

  html.dark-mode {
    /* User Custom Dark Palette */
    --color-dark-navy: #1B3651;   /* Navy */
    --color-light-blue: #C2D7DE;  /* Light Ice Blue */
    --color-sage-teal: #7AA1A2;   /* Sage Teal */
    --color-bright-yellow: #F4CE28;/* Bright Yellow */
    --color-soft-yellow: #E8D387;  /* Soft Sand Yellow */
    --color-bg-dark: #3F3F3F;     /* Sidebar Dark Gray */
    --color-bg-main: #494949;     /* Body Middle Gray */

    /* Mapped Dark Theme Variables */
    --paper: var(--color-bg-main);
    --ink: #FFFFFF;
    --ink-soft: var(--color-light-blue);
    --line: var(--color-sage-teal);
    --accent: var(--color-bright-yellow);
    --panel: var(--color-bg-dark);
    --white: var(--color-dark-navy);

    /* Helpers for Dark Theme */
    --logo-accent: var(--accent);
    --topbar-bg: var(--panel);
    --topbar-text: var(--ink-soft);
    --ticker-bg: var(--accent);
    --ticker-text: var(--color-dark-navy);
    --btn-text: var(--color-dark-navy);
    --comments-border: var(--line);
    --article-text: var(--ink);

    /* Dark Theme Skeletons */
    --shimmer-start: #162a3f;
    --shimmer-middle: #223f5e;
    --shimmer-end: #162a3f;
  }

  *{box-sizing:border-box;}
  body{margin:0;font-family:'Cairo','Outfit',system-ui,-apple-system,sans-serif;background:var(--paper);color:var(--ink);line-height:1.7;}
  a{color:inherit;text-decoration:none;}
  .lucide{width:14px;height:14px;vertical-align:-2px;flex-shrink:0;}
  .sidebar-title,.art-meta span,.post-meta .comments{display:inline-flex;align-items:center;gap:6px;}
  .sidebar-title{display:flex;align-items:center;gap:7px;}

  /* ---------- Top bar ---------- */
  .topbar{background:var(--topbar-bg);color:var(--topbar-text);font-size:12px;padding:6px 20px;display:flex;justify-content:space-between;align-items:center;}
  .topbar-socials {display:flex;align-items:center;gap:6px;}
  .social-label {font-size:12px;margin-left:4px;opacity:0.9;}
  .top-social-btn {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:26px;
      height:26px;
      border-radius:50%;
      background:rgba(255,255,255,0.12);
      color:var(--topbar-text);
      transition:background 0.2s ease, transform 0.15s ease;
  }
  .top-social-btn:hover {
      transform:scale(1.15);
  }
  .top-social-btn.fb:hover { background: #1877F2; color: #fff; }
  .top-social-btn.tw:hover { background: #000000; color: #fff; }
  .top-social-btn.ig:hover { background: #E4405F; color: #fff; }
  .top-social-btn.yt:hover { background: #FF0000; color: #fff; }
  .top-social-btn.tg:hover { background: #0088cc; color: #fff; }
  .top-social-btn.wa:hover { background: #25D366; color: #fff; }
  .top-social-btn svg { width:13px; height:13px; fill:currentColor; }

  /* ---------- Header ---------- */
  header{background:var(--white);border-bottom:3px solid var(--line);padding:18px 20px 0;}
  .brand-row{display:flex;align-items:center;justify-content:space-between;max-width:1180px;margin:0 auto 14px;}
  .logo-img-wrapper{display:inline-flex;align-items:center;text-decoration:none;}
  .header-logo{height:64px;width:auto;aspect-ratio:1/1;display:block;transition:height 0.2s ease;}
  .date-line{font-size:12px;color:var(--ink-soft);}
  nav{max-width:1180px;margin:0 auto;display:flex;gap:22px;overflow-x:auto;padding-bottom:10px;}
  nav a{font-weight:700;font-size:14px;white-space:nowrap;padding-bottom:10px;border-bottom:3px solid transparent;}
  nav a:hover,nav a.active{border-color:var(--accent);}

  /* ---------- Breaking ticker ---------- */
  .ticker{
      max-width: 1180px;
      margin: 16px auto 0;
      background: var(--ticker-bg);
      color: var(--ticker-text);
      display: flex;
      align-items: center;
      font-size: 13px;
      font-weight: 600;
      height: 38px;
      border-radius: 6px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  }
  .ticker .tag{
      background: rgba(0, 0, 0, 0.15);
      padding: 0 16px;
      height: 100%;
      display: flex;
      align-items: center;
      flex-shrink: 0;
  }
  .ticker-container {
      position: relative;
      flex: 1;
      height: 100%;
      overflow: hidden;
  }
  .ticker-item {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      padding: 0 16px;
      opacity: 0;
      transform: translateY(8px);
      transition: opacity 0.5s ease, transform 0.5s ease;
      white-space: nowrap;
      text-overflow: ellipsis;
      overflow: hidden;
      color: inherit;
      text-decoration: none;
  }
  .ticker-item.active {
      opacity: 1;
      transform: translateY(0);
      z-index: 5;
  }
  .ticker-item:hover {
      text-decoration: underline;
  }
  @media (max-width: 1220px) {
      .ticker {
          margin: 16px 20px 0;
      }
  }
  @media (max-width: 640px) {
      .ticker {
          font-size: 12px;
          height: 34px;
      }
      .ticker .tag {
          padding: 0 12px;
      }
      .ticker-item {
          padding: 0 10px;
      }
  }

  /* ---------- Layout ---------- */
  .wrap{max-width:1180px;margin:24px auto;padding:0 20px;display:grid;grid-template-columns:1fr 320px;gap:28px;}
  @media (max-width:860px){.wrap{grid-template-columns:1fr;}}

  /* ---------- Hero & Slideshow ---------- */
  .hero{display:grid;grid-template-columns:1.35fr 1fr;gap:18px;margin-bottom:30px;align-items:stretch;}
  @media (max-width:720px){.hero{grid-template-columns:1fr;}}
  .hero-slider{position:relative;border-radius:12px;overflow:hidden;aspect-ratio:16/10;background:#0f172a;box-shadow:0 8px 24px rgba(0,0,0,.08);}
  .hero-slide{position:absolute;inset:0;opacity:0;pointer-events:none;transition:opacity .45s ease;display:block;}
  .hero-slide.active{opacity:1;pointer-events:auto;z-index:2;}
  .hero-slide img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .45s ease;}
  .hero-slide:hover img{transform:scale(1.03);}
  .hero-slide .cap{position:absolute;inset:auto 0 0 0;background:linear-gradient(transparent, rgba(0,0,0,.88) 65%);padding:72px 20px 36px;}
  .hero-slide .cap h2{color:#fff;font-size:21px;font-weight:800;margin:0;line-height:1.5;}
  .hero-nav-btn{position:absolute;top:50%;transform:translateY(-50%);z-index:5;width:34px;height:34px;border-radius:50%;border:none;background:rgba(15,23,42,.62);color:#fff;cursor:pointer;display:grid;place-items:center;transition:background .15s ease;}
  .hero-nav-btn:hover{background:rgba(15,23,42,.88);}
  .hero-nav-btn.prev{right:12px;}
  .hero-nav-btn.next{left:12px;}
  .hero-dots{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);z-index:5;display:flex;gap:6px;}
  .hero-dot{width:8px;height:8px;border-radius:999px;border:none;background:rgba(255,255,255,.45);cursor:pointer;padding:0;transition:width .2s ease, background .2s ease;}
  .hero-dot.active{width:22px;background:#fff;}
  .hero-side{display:flex;flex-direction:column;justify-content:space-between;gap:12px;}
  .hero-side .item{display:flex;gap:12px;align-items:center;background:var(--white);border:1px solid var(--line);border-radius:10px;padding:10px;transition:box-shadow .18s ease, transform .18s ease, border-color .18s ease;}
  .hero-side .item:hover{box-shadow:0 6px 16px rgba(0,0,0,.07);transform:translateY(-2px);border-color:var(--accent);}
  .hero-side .item img{width:108px;height:72px;object-fit:cover;border-radius:7px;flex-shrink:0;}
  .hero-side .item > div{min-width:0;flex:1;}
  .hero-side h3{font-size:13.5px;font-weight:700;margin:0 0 4px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
  .hero-side .item-time{font-size:11px;color:var(--ink-soft);}

  /* ---------- Category label ---------- */
  .cat{display:inline-block;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:999px;color:#fff;margin-bottom:6px;}

  /* ---------- Feed grid ---------- */
  .feed-title{font-size:18px;font-weight:800;border-right:4px solid var(--accent);padding-right:12px;margin:8px 0 18px;display:flex;align-items:center;justify-content:space-between;}
  .feed{display:grid;grid-template-columns:repeat(3, 1fr);gap:18px;}
  @media (max-width:960px){.feed{grid-template-columns:repeat(2, 1fr);}}
  @media (max-width:520px){.feed{grid-template-columns:1fr;gap:14px;}}

  .post-card{background:var(--white);border:1px solid var(--line);border-radius:10px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 2px 10px rgba(0,0,0,.03);transition:box-shadow .18s ease, transform .18s ease, border-color .18s ease;}
  .post-card:hover{box-shadow:0 10px 24px rgba(0,0,0,.08);transform:translateY(-3px);border-color:rgba(0,0,0,.12);}
  .thumb{position:relative;aspect-ratio:16/9;overflow:hidden;background:#eee;}
  .thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s ease;}
  .post-card:hover .thumb img{transform:scale(1.04);}
  .badge{position:absolute;top:10px;right:10px;font-size:10.5px;font-weight:700;padding:3px 10px;border-radius:999px;background:rgba(0,0,0,.7);color:#fff;backdrop-filter:blur(4px);}
  .sponsored-badge{background:var(--accent);}
  .post-body{padding:14px;flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:10px;}
  .post-body h3{font-size:14.5px;font-weight:700;margin:0;line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
  .post-meta{display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:11.5px;color:var(--ink-soft);padding-top:8px;border-top:1px solid rgba(0,0,0,.04);}
  .post-meta .comments{display:flex;align-items:center;gap:4px;}
  .post-meta .price{color:var(--accent);font-weight:800;font-size:13px;}
  .post-meta .price s{color:#aaa;font-weight:400;font-size:11px;margin-left:4px;}
  .post-meta .cta{color:var(--accent);font-weight:700;}
  .post-card.sponsored{border-color:var(--accent);}

  /* ---------- Skeleton (banners loading async) ---------- */
  .jb-slot{padding:0;}
  .jb-skeleton{width:100%;aspect-ratio:1/1;background:linear-gradient(90deg, var(--shimmer-start) 25%, var(--shimmer-middle) 37%, var(--shimmer-end) 63%);background-size:400% 100%;animation:jb-shimmer 1.4s ease infinite;}
  #jb-sidebar-promos .jb-skeleton{aspect-ratio:auto;height:64px;}
  @keyframes jb-shimmer{0%{background-position:100% 50%;}100%{background-position:0 50%;}}
  .jb-slot.jb-loaded{animation:jb-fade-in .35s ease;}
  @keyframes jb-fade-in{from{opacity:0; transform:translateY(4px);}to{opacity:1; transform:translateY(0);}}

  /* ---------- In-article banner (mstatel) ---------- */
  .jb-inline{display:flex;align-items:center;gap:14px;background:var(--white);border:1px solid var(--line);border-right:4px solid var(--accent);border-radius:8px;padding:12px;margin:26px 0;}
  .jb-inline img{width:76px;height:76px;object-fit:cover;border-radius:6px;flex-shrink:0;}
  .jb-inline .body{flex:1;min-width:0;}
  .jb-inline .tag{font-size:10px;font-weight:700;color:var(--accent);margin-bottom:4px;display:block;}
  .jb-inline h4{font-size:13.5px;margin:0 0 6px;line-height:1.5;}
  .jb-inline .price{font-size:13px;font-weight:800;color:var(--accent);}
  .jb-inline .price s{color:#aaa;font-weight:400;font-size:11px;margin-left:4px;}
  .jb-inline .cta{font-size:12px;font-weight:700;color:var(--accent);white-space:nowrap;}
  .jb-skeleton-inline{width:100%;height:100px;border-radius:8px;background:linear-gradient(90deg,var(--shimmer-start) 25%,var(--shimmer-middle) 37%,var(--shimmer-end) 63%);background-size:400% 100%;animation:jb-shimmer 1.4s ease infinite;}
  .jb-inline.jb-loaded{animation:jb-fade-in .35s ease;}

  /* ---------- Article ---------- */
  .crumb{font-size:12px;color:var(--ink-soft);margin-bottom:10px;}
  .art-title{font-size:26px;font-weight:800;line-height:1.55;margin:0 0 14px;}
  .art-meta{display:flex;flex-wrap:wrap;gap:14px;font-size:12.5px;color:var(--ink-soft);margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid var(--line);}
  .art-cover{width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:6px;display:block;margin:0 auto 24px;}
  .art-body p{font-size:16.5px;margin:0 0 20px;color:var(--article-text);}
  .art-body h2, .art-body h3{color:var(--ink); font-weight:800; margin:28px 0 16px; line-height:1.4;}
  .art-body h2{font-size:21px; border-bottom:2px solid var(--line); padding-bottom:6px;}
  .art-body h3{font-size:18px;}
  .art-body a{color:var(--accent); text-decoration:underline; font-weight:600; transition:color 0.2s;}
  .art-body a:hover{color:var(--logo-accent);}
  .art-body ul, .art-body ol{margin:0 24px 20px 0; padding-right:10px;}
  .art-body li{margin-bottom:8px; font-size:16px; color:var(--article-text);}
  .art-body blockquote{border-right:4px solid var(--accent); background:var(--panel); padding:12px 20px; margin:20px 0; border-radius:4px;}

  /* ---------- Comments ---------- */
  .comments-section{margin-top:36px;padding-top:20px;border-top:2px solid var(--comments-border);}
  .comments-title{font-size:17px;font-weight:800;margin-bottom:16px;}
  .comment-form{display:flex;flex-direction:column;gap:8px;margin-bottom:26px;}
  .comment-form textarea{font-family:'Cairo',sans-serif;border:1px solid var(--line);border-radius:6px;padding:10px;font-size:14px;resize:vertical;min-height:70px;}
  .comment-form button{align-self:flex-start;background:var(--accent);color:var(--btn-text);border:none;padding:8px 20px;border-radius:6px;font-weight:700;font-size:13px;cursor:pointer;font-family:'Cairo',sans-serif;}
  .comment{display:flex;gap:12px;padding:14px 0;border-bottom:1px dashed var(--line);}
  .avatar{width:38px;height:38px;border-radius:50%;background:var(--panel);display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--accent);flex-shrink:0;font-size:14px;}
  .comment-body{flex:1;}
  .comment-head{display:flex;gap:10px;align-items:baseline;margin-bottom:4px;}
  .comment-head b{font-size:13.5px;}
  .comment-head span{font-size:11.5px;color:var(--ink-soft);}
  .comment-body p{margin:0;font-size:14px;color:var(--article-text);}

  /* ---------- Sidebar ---------- */
  .sidebar-block{background:var(--white);border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,.03);}
  .sidebar-title{font-size:14.5px;font-weight:800;margin:0 0 12px;padding-bottom:10px;border-bottom:2px solid var(--comments-border);}
  .trend-item{display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px dashed var(--line);}
  .trend-item:last-child{border-bottom:none;padding-bottom:2px;}
  .trend-num{font-weight:800;color:var(--accent);font-size:13px;width:24px;height:24px;border-radius:7px;background:var(--panel);display:grid;place-items:center;flex-shrink:0;margin-top:2px;}
  .trend-item p{font-size:13px;font-weight:600;margin:0;line-height:1.55;}
  .ad-slot{background:var(--panel);border:1px dashed var(--comments-border);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--ink-soft);font-size:12px;padding:40px 10px;margin-bottom:20px;}
  .sb-promo{display:flex;gap:10px;background:var(--white);border:1px solid var(--line);border-radius:8px;padding:10px;margin-bottom:10px;transition:border-color .15s ease, transform .15s ease;}
  .sb-promo:hover{border-color:var(--accent);transform:translateY(-1px);}
  .sb-promo:last-child{margin-bottom:0;}
  .sb-promo img{width:64px;height:64px;object-fit:cover;border-radius:6px;flex-shrink:0;}
  .sb-promo h4{font-size:12.5px;margin:0 0 4px;line-height:1.4;}
  .sb-promo .price{font-size:12px;color:var(--accent);font-weight:800;}

  /* ---------- Pagination ---------- */
  .pagination{display:flex;gap:8px;justify-content:center;margin:26px 0 6px;}
  .pagination a,.pagination span{padding:7px 13px;border-radius:6px;font-size:13px;font-weight:700;background:var(--white);border:1px solid var(--line);}
  .pagination a:hover{border-color:var(--accent);color:var(--accent);}
  .pagination .current{background:var(--accent);color:var(--btn-text);border-color:var(--accent);}

  footer{background:var(--topbar-bg);color:var(--topbar-text);text-align:center;font-size:12px;padding:24px;margin-top:30px;}

  /* ---------- Mobile refinements ---------- */
  @media (max-width:640px){
    .topbar{font-size:10.5px;padding:5px 14px;}
    .topbar span:last-child{display:none;}
    header{padding:14px 14px 0;}
    .header-logo{height:48px;}
    .date-line{display:none;}
    nav{gap:16px;}
    nav a{font-size:13px;}
    /* Removed ticker msg media query - handled above */
    .wrap{padding:0 14px;margin:16px auto;gap:20px;}
    .hero-main .cap{padding:40px 12px 12px;}
    .hero-main .cap h2{font-size:16px;}
    .hero-side .item img{width:76px;}
    .hero-side h3{font-size:13px;}
    .feed{gap:12px;}
    .post-body{padding:10px;}
    .post-body h3{font-size:13.5px;}
    .feed-title{font-size:15px;}
    .art-title{font-size:20px;}
    .art-meta{gap:10px;font-size:11.5px;}
    .art-body p{font-size:15px;}
    .jb-inline{flex-wrap:wrap;}
    .jb-inline img{width:64px;height:64px;}
  }

</style>
<script>
function copyShareLink(link, btn) {
    navigator.clipboard.writeText(link).then(function() {
        var oldHtml = btn.innerHTML;
        btn.innerHTML = '<span style="font-size:10px;font-weight:700;color:#fff;">✓</span>';
        var oldBg = btn.style.background;
        btn.style.background = '#25D366';
        setTimeout(function() {
            btn.innerHTML = oldHtml;
            btn.style.background = oldBg;
        }, 1500);
    }).catch(function() {
        var dummy = document.createElement('input');
        document.body.appendChild(dummy);
        dummy.value = link;
        dummy.select();
        document.execCommand('copy');
        document.body.removeChild(dummy);
        
        var oldHtml = btn.innerHTML;
        btn.innerHTML = '<span style="font-size:10px;font-weight:700;color:#fff;">✓</span>';
        var oldBg = btn.style.background;
        btn.style.background = '#25D366';
        setTimeout(function() {
            btn.innerHTML = oldHtml;
            btn.style.background = oldBg;
        }, 1500);
    });
}
window.refreshIcons = function () {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
};
window.addEventListener('load', function () {
    if (!window.lucide) {
        var s = document.createElement('script');
        s.src = 'https://unpkg.com/lucide@0.400.0/dist/umd/lucide.min.js';
        s.async = true;
        s.onload = window.refreshIcons;
        document.head.appendChild(s);
    } else {
        window.refreshIcons();
    }
});
document.addEventListener('DOMContentLoaded', function () {
    window.refreshIcons();
    var themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        var iconEl = document.getElementById('theme-toggle-icon');
        var textEl = document.getElementById('theme-toggle-text');
        
        function updateToggleUI() {
            var isDark = document.documentElement.classList.contains('dark-mode');
            if (isDark) {
                iconEl.innerHTML = '<i data-lucide="sun"></i>';
                textEl.textContent = 'الوضع الفاتح';
            } else {
                iconEl.innerHTML = '<i data-lucide="moon"></i>';
                textEl.textContent = 'الوضع الداكن';
            }
            window.refreshIcons();
        }
        
        updateToggleUI();
        
        themeToggle.addEventListener('click', function () {
            var isDark = document.documentElement.classList.contains('dark-mode');
            if (isDark) {
                document.documentElement.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            }
            updateToggleUI();
        });
    }
});
</script>
</head>
<body<?= isset($bodyPostId) ? ' data-post-id="' . (int)$bodyPostId . '"' : '' ?>>

<?php
$tzNow = new DateTimeImmutable('now', new DateTimeZone('Africa/Casablanca'));
$arDays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
$arMonths = [1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'ماي', 6 => 'يونيو', 7 => 'يوليوز', 8 => 'غشت', 9 => 'شتنبر', 10 => 'أكتوبر', 11 => 'نونبر', 12 => 'دجنبر'];
$todayArabic = $arDays[(int)$tzNow->format('w')] . ' ' . $tzNow->format('j') . ' ' . $arMonths[(int)$tzNow->format('n')] . ' ' . $tzNow->format('Y');

$lastUpdatedText = 'الآن';
try {
    $latestCreated = $pdo->query("SELECT created_at FROM news_posts WHERE status = 'published' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1")->fetchColumn();
    if ($latestCreated) {
        $lastUpdatedText = time_ago($latestCreated);
    }
} catch (Exception $e) {}
?>
<div class="topbar">
  <span style="display:inline-flex;align-items:center;gap:6px;"><i data-lucide="calendar"></i> <?= htmlspecialchars($todayArabic) ?> — المغرب</span>
  <div style="display:flex; align-items:center; gap:18px;">
    <?php 
    $fb = trim(get_setting('social_facebook', ''));
    $tw = trim(get_setting('social_twitter', ''));
    $ig = trim(get_setting('social_instagram', ''));
    $yt = trim(get_setting('social_youtube', ''));
    $tg = trim(get_setting('social_telegram', ''));
    $wa = trim(get_setting('social_whatsapp', ''));
    $hasSocials = !empty($fb) || !empty($tw) || !empty($ig) || !empty($yt) || !empty($tg) || !empty($wa);
    ?>
    <?php if ($hasSocials): ?>
      <div class="topbar-socials">
        <span class="social-label">تابعنا:</span>
        <?php if (!empty($fb)): ?>
          <a href="<?= htmlspecialchars($fb) ?>" target="_blank" rel="noopener" title="فيسبوك" class="top-social-btn fb">
            <i class="fa-brands fa-facebook-f"></i>
          </a>
        <?php endif; ?>
        <?php if (!empty($tw)): ?>
          <a href="<?= htmlspecialchars($tw) ?>" target="_blank" rel="noopener" title="تويتر / X" class="top-social-btn tw">
            <i class="fa-brands fa-x-twitter"></i>
          </a>
        <?php endif; ?>
        <?php if (!empty($ig)): ?>
          <a href="<?= htmlspecialchars($ig) ?>" target="_blank" rel="noopener" title="انستغرام" class="top-social-btn ig">
            <i class="fa-brands fa-instagram"></i>
          </a>
        <?php endif; ?>
        <?php if (!empty($yt)): ?>
          <a href="<?= htmlspecialchars($yt) ?>" target="_blank" rel="noopener" title="يوتيوب" class="top-social-btn yt">
            <i class="fa-brands fa-youtube"></i>
          </a>
        <?php endif; ?>
        <?php if (!empty($tg)): ?>
          <a href="<?= htmlspecialchars($tg) ?>" target="_blank" rel="noopener" title="تلغرام" class="top-social-btn tg">
            <i class="fa-brands fa-telegram"></i>
          </a>
        <?php endif; ?>
        <?php if (!empty($wa)): ?>
          <a href="<?= htmlspecialchars($wa) ?>" target="_blank" rel="noopener" title="واتساب" class="top-social-btn wa">
            <i class="fa-brands fa-whatsapp"></i>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <button id="theme-toggle" style="background:none; border:none; color:inherit; cursor:pointer; font-family:inherit; font-size:12px; display:flex; align-items:center; gap:6px; padding:0; outline:none;">
        <span id="theme-toggle-icon" style="display:inline-flex;align-items:center;"><i data-lucide="moon"></i></span> <span id="theme-toggle-text">الوضع الداكن</span>
    </button>
  </div>
</div>

<header>
  <div class="brand-row">
    <a href="/" class="logo-img-wrapper">
      <img class="header-logo" src="https://cdn.9sty.com/uploads/logo_akhbaralmghrib.svg" alt="أخبار المغرب">
    </a>
    <div class="date-line" style="display:inline-flex;align-items:center;gap:5px;"><i data-lucide="clock"></i> آخر تحديث: <?= htmlspecialchars($lastUpdatedText) ?></div>
  </div>
  <nav>
    <a href="/">الرئيسية</a>
    <?php foreach ($navCategories as $nc): ?>
      <a href="/<?= htmlspecialchars($nc['slug']) ?>"><?= htmlspecialchars($nc['name']) ?></a>
    <?php endforeach; ?>
  </nav>
</header>

<?php
$tickerPosts = [];
try {
    $tickerPosts = $pdo->query("
        SELECT id, title, slug, short_code FROM news_posts
        WHERE status = 'published' AND deleted_at IS NULL
        ORDER BY id DESC
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {}
?>
<?php if (!empty($showTicker) && !empty($tickerPosts)): ?>
<div class="ticker">
  <span class="tag">عاجل</span>
  <div class="ticker-container">
    <?php foreach ($tickerPosts as $index => $post): ?>
      <a href="/<?= htmlspecialchars($post['slug'] ?: $post['id']) ?>" 
         class="ticker-item <?= $index === 0 ? 'active' : '' ?>">
         <?= htmlspecialchars($post['title']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var items = document.querySelectorAll('.ticker-item');
    if (items.length > 1) {
        var current = 0;
        setInterval(function() {
            items[current].classList.remove('active');
            current = (current + 1) % items.length;
            items[current].classList.add('active');
        }, 4000);
    }
});
</script>
<?php endif; ?>

<div class="wrap">
