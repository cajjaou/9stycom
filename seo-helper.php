<?php
/**
 * seo-helper.php — Centralized SEO generator for 9sty.
 */

function get_seo_data() {
    global $article, $category, $page, $slug;
    
    // 1) Fetch settings
    $site_url = rtrim(get_setting('site_url', 'https://9sty.com'), '/');
    $site_title = get_setting('site_title', 'أخبار المغرب');
    $site_description = get_setting('site_description', 'تابع آخر الأخبار والمستجدات الوطنية في المغرب، أخبار السياسة والتطورات الوطنية.');
    $site_logo = get_setting('site_logo', 'https://cdn.9sty.com/uploads/logo_akhbaralmghrib.svg');
    $default_image = get_setting('default_seo_image', 'https://cdn.9sty.com/uploads/no-images-news.webp');

    // 2) Identify current page type
    $type = 'home';
    if (!empty($article)) {
        $type = 'article';
    } elseif (!empty($category)) {
        $type = 'category';
    } elseif ($slug === '404' || http_response_code() === 404) {
        $type = '404';
    }

    // 3) Default values
    $seo_title = $site_title . ' — ' . $site_description;
    $seo_desc = $site_description;
    $canonical = $site_url . '/';
    $robots = 'index, follow';
    $og_type = 'website';
    $og_image = $default_image;

    // Article published/modified times
    $published_time = '';
    
    if ($type === 'article') {
        $seo_title = $article['title'] . ' | ' . $site_title;
        $seo_desc = !empty($article['excerpt']) ? $article['excerpt'] : $article['content'];
        $canonical = $site_url . '/' . $article['slug'];
        $og_type = 'article';
        if (!empty($article['image'])) {
            $og_image = post_thumb($article['image']);
        }
        $published_time = date(DateTime::ISO8601, strtotime($article['created_at']));
    } elseif ($type === 'category') {
        if ($page > 1) {
            $seo_title = $category['name'] . ' - صفحة ' . $page . ' | ' . $site_title;
            $canonical = $site_url . '/' . $category['slug'] . '/page/' . $page;
        } else {
            $seo_title = $category['name'] . ' | ' . $site_title;
            $canonical = $site_url . '/' . $category['slug'];
        }
        $seo_desc = 'آخر الأخبار والمستجدات المتعلقة بـ ' . $category['name'] . ' على موقع ' . $site_title . '.';
    } elseif ($type === '404') {
        $seo_title = 'الصفحة غير موجودة | ' . $site_title;
        $robots = 'noindex, nofollow';
        $canonical = $site_url . '/404';
    }

    // Clean description: strip tags, normalize spaces, truncate
    $seo_desc = strip_tags($seo_desc);
    $seo_desc = preg_replace('/\s+/', ' ', $seo_desc);
    $seo_desc = trim($seo_desc);
    if (mb_strlen($seo_desc) > 155) {
        $seo_desc = mb_substr($seo_desc, 0, 152) . '...';
    }

    return [
        'title' => $seo_title,
        'description' => $seo_desc,
        'canonical' => $canonical,
        'robots' => $robots,
        'og_type' => $og_type,
        'og_image' => $og_image,
        'site_title' => $site_title,
        'site_url' => $site_url,
        'site_logo' => $site_logo,
        'published_time' => $published_time,
        'type' => $type
    ];
}

function render_seo_tags() {
    $seo = get_seo_data();
    
    $html = "\n  <!-- Technical SEO Settings -->\n";
    $html .= '  <title>' . htmlspecialchars($seo['title']) . "</title>\n";
    $html .= '  <meta name="description" content="' . htmlspecialchars($seo['description']) . "\">\n";
    $html .= '  <meta name="robots" content="' . htmlspecialchars($seo['robots']) . "\">\n";
    $html .= '  <link rel="canonical" href="' . htmlspecialchars($seo['canonical']) . "\">\n";
    
    // Open Graph
    $html .= "\n  <!-- Open Graph / Facebook -->\n";
    $html .= '  <meta property="og:type" content="' . htmlspecialchars($seo['og_type']) . "\">\n";
    $html .= '  <meta property="og:title" content="' . htmlspecialchars($seo['title']) . "\">\n";
    $html .= '  <meta property="og:description" content="' . htmlspecialchars($seo['description']) . "\">\n";
    $html .= '  <meta property="og:url" content="' . htmlspecialchars($seo['canonical']) . "\">\n";
    $html .= '  <meta property="og:image" content="' . htmlspecialchars($seo['og_image']) . "\">\n";
    $html .= '  <meta property="og:site_name" content="' . htmlspecialchars($seo['site_title']) . "\">\n";
    
    if ($seo['type'] === 'article' && !empty($seo['published_time'])) {
        $html .= '  <meta property="article:published_time" content="' . htmlspecialchars($seo['published_time']) . "\">\n";
        $html .= '  <meta property="article:author" content="إدارة التحرير' . "\">\n";
    }

    // Twitter
    $html .= "\n  <!-- Twitter -->\n";
    $html .= "  <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    $html .= '  <meta name="twitter:title" content="' . htmlspecialchars($seo['title']) . "\">\n";
    $html .= '  <meta name="twitter:description" content="' . htmlspecialchars($seo['description']) . "\">\n";
    $html .= '  <meta name="twitter:image" content="' . htmlspecialchars($seo['og_image']) . "\">\n";
    
    // JSON-LD Structured Data
    $html .= "\n  <!-- Structured Data JSON-LD -->\n";
    
    // WebSite schema for homepage
    if ($seo['type'] === 'home') {
        $website_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $seo['site_title'],
            'url' => $seo['site_url'] . '/'
        ];
        $html .= '  <script type="application/ld+json">' . json_encode($website_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
    
    // NewsArticle schema for articles
    if ($seo['type'] === 'article') {
        global $article;
        
        $article_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $seo['canonical']
            ],
            'headline' => $article['title'],
            'description' => $seo['description'],
            'image' => $seo['og_image'],
            'datePublished' => $seo['published_time'],
            'dateModified' => $seo['published_time'],
            'author' => [
                '@type' => 'Organization',
                'name' => 'إدارة التحرير'
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $seo['site_title'],
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $seo['site_logo']
                ]
            ]
        ];
        $html .= '  <script type="application/ld+json">' . json_encode($article_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
        
        // Breadcrumbs Schema for articles
        $breadcrumbs_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'الرئيسية',
                    'item' => $seo['site_url'] . '/'
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $article['category_name'] ?? 'عام',
                    'item' => $seo['site_url'] . '/' . ($article['category_slug'] ?? 'general')
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $article['title'],
                    'item' => $seo['canonical']
                ]
            ]
        ];
        $html .= '  <script type="application/ld+json">' . json_encode($breadcrumbs_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
    
    // Breadcrumbs Schema for category
    if ($seo['type'] === 'category') {
        global $category;
        
        $breadcrumbs_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'الرئيسية',
                    'item' => $seo['site_url'] . '/'
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $category['name'],
                    'item' => $seo['canonical']
                ]
            ]
        ];
        $html .= '  <script type="application/ld+json">' . json_encode($breadcrumbs_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
    
    return $html;
}
