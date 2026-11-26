<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/errors.php';
require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Accept');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function api_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_extract_key(): string
{
    $headers = [];
    if (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) {
            $headers[strtolower((string) $k)] = (string) $v;
        }
    }
    $auth = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? ($headers['authorization'] ?? '');
    if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', (string) $auth, $m)) {
        return trim($m[1]);
    }
    $xKey = $_SERVER['HTTP_X_API_KEY'] ?? ($headers['x-api-key'] ?? '');
    return trim((string) $xKey);
}

function api_client_ip(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    return substr(trim(explode(',', (string) $ip)[0]), 0, 45);
}

function api_authenticate(PDO $pdo): array
{
    $rawKey = api_extract_key();
    if ($rawKey === '' || strlen($rawKey) < 16) {
        api_json(401, ['success' => false, 'error' => 'Unauthorized: Missing or invalid API key.']);
    }
    $hash = hash('sha256', $rawKey);
    $stmt = $pdo->prepare("
        SELECT id, name, is_active, revoked_at
        FROM api_keys
        WHERE key_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $keyRow = $stmt->fetch();
    if (!$keyRow || (int) $keyRow['is_active'] !== 1 || $keyRow['revoked_at'] !== null) {
        api_json(401, ['success' => false, 'error' => 'Unauthorized: API key is invalid, inactive, or revoked.']);
    }
    try {
        $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?")->execute([(int) $keyRow['id']]);
    } catch (Throwable $e) {
    }
    return $keyRow;
}

function api_log_action(
    PDO $pdo,
    array $apiKey,
    string $method,
    string $endpoint,
    string $resource,
    ?string $recordId,
    string $actionType,
    int $statusCode,
    array $payload = []
): void {
    try {
        $sanitized = $payload;
        foreach (['password', 'api_key', 'token'] as $secretField) {
            if (isset($sanitized[$secretField])) {
                $sanitized[$secretField] = '***';
            }
        }
        $summary = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($summary !== false && strlen($summary) > 1500) {
            $summary = substr($summary, 0, 1497) . '...';
        }
        $stmt = $pdo->prepare("
            INSERT INTO api_logs
                (api_key_id, api_key_name, method, endpoint, resource, record_id, action_type, status_code, payload_summary, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int) $apiKey['id'],
            (string) $apiKey['name'],
            strtoupper($method),
            $endpoint,
            $resource,
            $recordId,
            $actionType,
            $statusCode,
            $summary ?: null,
            api_client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('[api_logs] ' . $e->getMessage());
    }
}

function api_read_input(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return array_merge($_GET, $_POST);
}

function api_slugify(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text) ?? '';
    $text = preg_replace('/[\s-]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? mb_substr($text, 0, 180) : ('item-' . bin2hex(random_bytes(4)));
}

function api_random_code(int $len = 6): string
{
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

try {
    $pdo = get_db();
} catch (Throwable $e) {
    api_json(500, ['success' => false, 'error' => 'Database connection error.']);
}

$apiKey = api_authenticate($pdo);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$route = trim((string) ($_GET['route'] ?? ''), '/');
$parts = $route !== '' ? explode('/', $route) : [];

$resource = strtolower($parts[0] ?? '');
$actionOrId = strtolower($parts[1] ?? '');

$methodToAction = [
    'GET' => 'read',
    'POST' => 'create',
    'PUT' => 'update',
    'PATCH' => 'update',
    'DELETE' => 'delete',
];

$action = '';
$routeId = null;
if (in_array($actionOrId, ['read', 'create', 'update', 'delete'], true)) {
    $action = $actionOrId;
} elseif ($actionOrId !== '' && ctype_digit($actionOrId)) {
    $routeId = (int) $actionOrId;
    $action = $methodToAction[$method] ?? 'read';
} elseif ($actionOrId === '') {
    $action = $methodToAction[$method] ?? 'read';
} else {
    api_json(404, ['success' => false, 'error' => 'Endpoint not found.']);
}

$input = api_read_input();
if ($routeId !== null && !isset($input['id'])) {
    $input['id'] = $routeId;
}

$endpoint = '/api/' . $resource . '/' . $action;
$actionTypeMap = ['read' => 'READ', 'create' => 'CREATE', 'update' => 'UPDATE', 'delete' => 'DELETE'];
$actionType = $actionTypeMap[$action];

$respond = function (int $status, array $body, ?string $recordId = null) use ($pdo, $apiKey, $method, $endpoint, $resource, $actionType, $input): void {
    api_log_action($pdo, $apiKey, $method, $endpoint, $resource, $recordId, $actionType, $status, $input);
    api_json($status, $body);
};

try {
    switch ($resource) {
        // ==================== POSTS ====================
        case 'posts':
            if ($action === 'read') {
                $id = isset($input['id']) ? (int) $input['id'] : null;
                $slug = isset($input['slug']) ? trim((string) $input['slug']) : null;
                if ($id) {
                    $stmt = $pdo->prepare("SELECT * FROM news_posts WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch();
                    if (!$row) $respond(404, ['success' => false, 'error' => 'Post not found.'], (string) $id);
                    $respond(200, ['success' => true, 'data' => $row], (string) $id);
                }
                if ($slug !== null && $slug !== '') {
                    $stmt = $pdo->prepare("SELECT * FROM news_posts WHERE slug = ? AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$slug]);
                    $row = $stmt->fetch();
                    if (!$row) $respond(404, ['success' => false, 'error' => 'Post not found.']);
                    $respond(200, ['success' => true, 'data' => $row], (string) $row['id']);
                }

                $page = max(1, (int) ($input['page'] ?? 1));
                $perPage = max(1, min(100, (int) ($input['per_page'] ?? 20)));
                $offset = ($page - 1) * $perPage;
                $where = ['deleted_at IS NULL'];
                $params = [];
                if (!empty($input['status'])) {
                    $where[] = 'status = ?';
                    $params[] = trim((string) $input['status']);
                }
                if (!empty($input['category_id'])) {
                    $where[] = 'category_id = ?';
                    $params[] = (int) $input['category_id'];
                }
                if (!empty($input['post_type'])) {
                    $where[] = 'post_type = ?';
                    $params[] = trim((string) $input['post_type']);
                }
                if (!empty($input['search'])) {
                    $where[] = '(title LIKE ? OR slug LIKE ? OR short_code LIKE ?)';
                    $q = '%' . trim((string) $input['search']) . '%';
                    array_push($params, $q, $q, $q);
                }
                $wSql = implode(' AND ', $where);
                $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM news_posts WHERE $wSql");
                $cntStmt->execute($params);
                $total = (int) $cntStmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT id, post_id, category_id, short_code, title, slug, excerpt, image, status, post_type, views, created_at FROM news_posts WHERE $wSql ORDER BY id DESC LIMIT $perPage OFFSET $offset");
                $stmt->execute($params);
                $respond(200, [
                    'success' => true,
                    'data' => $stmt->fetchAll(),
                    'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => max(1, (int) ceil($total / $perPage))],
                ]);
            } elseif ($action === 'create') {
                $title = trim((string) ($input['title'] ?? ''));
                if ($title === '') $respond(400, ['success' => false, 'error' => 'Field "title" is required.']);
                $content = (string) ($input['content'] ?? '');
                $excerpt = trim((string) ($input['excerpt'] ?? ''));
                $categoryId = (int) ($input['category_id'] ?? 1);
                $image = trim((string) ($input['image'] ?? ''));
                $status = in_array(($input['status'] ?? 'published'), ['published', 'draft', 'archived'], true) ? $input['status'] : 'published';
                $postType = in_array(($input['post_type'] ?? 'article'), ['article', 'short'], true) ? $input['post_type'] : 'article';
                $slug = !empty($input['slug']) ? api_slugify((string) $input['slug']) : api_slugify($title);

                // Ensure unique slug and short_code
                $chk = $pdo->prepare("SELECT id FROM news_posts WHERE slug = ? LIMIT 1");
                $chk->execute([$slug]);
                if ($chk->fetch()) $slug .= '-' . api_random_code(4);

                do {
                    $shortCode = api_random_code(5);
                    $scChk = $pdo->prepare("SELECT id FROM news_posts WHERE short_code = ? LIMIT 1");
                    $scChk->execute([$shortCode]);
                } while ($scChk->fetch());

                $postIdStr = 'api_' . bin2hex(random_bytes(5));
                $stmt = $pdo->prepare("
                    INSERT INTO news_posts (post_id, auther_id, category_id, short_code, title, slug, excerpt, content, image, status, post_type)
                    VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$postIdStr, $categoryId, $shortCode, $title, $slug, $excerpt, $content, $image, $status, $postType]);
                $newId = (string) $pdo->lastInsertId();
                $respond(201, [
                    'success' => true,
                    'message' => 'Post created.',
                    'data' => ['id' => (int) $newId, 'slug' => $slug, 'short_code' => $shortCode, 'short_url' => 'https://9sty.com/s/' . $shortCode],
                ], $newId);
            } elseif ($action === 'update') {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) $respond(400, ['success' => false, 'error' => 'Field "id" is required.']);
                $exists = $pdo->prepare("SELECT id FROM news_posts WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                $exists->execute([$id]);
                if (!$exists->fetch()) $respond(404, ['success' => false, 'error' => 'Post not found.'], (string) $id);

                $allowed = ['title', 'slug', 'excerpt', 'content', 'image', 'category_id', 'status', 'post_type'];
                $sets = [];
                $params = [];
                foreach ($allowed as $col) {
                    if (array_key_exists($col, $input)) {
                        $sets[] = "`$col` = ?";
                        $params[] = $col === 'category_id' ? (int) $input[$col] : (string) $input[$col];
                    }
                }
                if (!$sets) $respond(400, ['success' => false, 'error' => 'No valid fields provided to update.'], (string) $id);
                $params[] = $id;
                $pdo->prepare("UPDATE news_posts SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
                $respond(200, ['success' => true, 'message' => 'Post updated.', 'id' => $id], (string) $id);
            } elseif ($action === 'delete') {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) $respond(400, ['success' => false, 'error' => 'Field "id" is required.']);
                $stmt = $pdo->prepare("UPDATE news_posts SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) $respond(404, ['success' => false, 'error' => 'Post not found or already deleted.'], (string) $id);
                $respond(200, ['success' => true, 'message' => 'Post soft-deleted.', 'id' => $id], (string) $id);
            }
            break;

        // ==================== CATEGORIES ====================
        case 'categories':
            if ($action === 'read') {
                $id = isset($input['id']) ? (int) $input['id'] : null;
                if ($id) {
                    $stmt = $pdo->prepare("SELECT * FROM news_categories WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch();
                    if (!$row) $respond(404, ['success' => false, 'error' => 'Category not found.'], (string) $id);
                    $respond(200, ['success' => true, 'data' => $row], (string) $id);
                }
                $rows = $pdo->query("SELECT * FROM news_categories WHERE deleted_at IS NULL ORDER BY id ASC")->fetchAll();
                $respond(200, ['success' => true, 'data' => $rows]);
            } elseif ($action === 'create') {
                $name = trim((string) ($input['name'] ?? ''));
                if ($name === '') $respond(400, ['success' => false, 'error' => 'Field "name" is required.']);
                $slug = !empty($input['slug']) ? api_slugify((string) $input['slug']) : api_slugify($name);
                $status = in_array(($input['status'] ?? 'active'), ['active', 'inactive'], true) ? $input['status'] : 'active';
                $showInHeader = isset($input['show_in_header']) ? (int) $input['show_in_header'] : 1;
                $stmt = $pdo->prepare("INSERT INTO news_categories (name, slug, status, show_in_header, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $status, $showInHeader, trim((string) ($input['meta_title'] ?? '')), trim((string) ($input['meta_description'] ?? ''))]);
                $newId = (string) $pdo->lastInsertId();
                $respond(201, ['success' => true, 'message' => 'Category created.', 'data' => ['id' => (int) $newId, 'slug' => $slug]], $newId);
            } elseif ($action === 'update') {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) $respond(400, ['success' => false, 'error' => 'Field "id" is required.']);
                $sets = [];
                $params = [];
                foreach (['name', 'slug', 'status', 'show_in_header', 'meta_title', 'meta_description'] as $col) {
                    if (array_key_exists($col, $input)) {
                        $sets[] = "`$col` = ?";
                        $params[] = $col === 'show_in_header' ? (int) $input[$col] : (string) $input[$col];
                    }
                }
                if (!$sets) $respond(400, ['success' => false, 'error' => 'No valid fields provided.'], (string) $id);
                $params[] = $id;
                $pdo->prepare("UPDATE news_categories SET " . implode(', ', $sets) . " WHERE id = ? AND deleted_at IS NULL")->execute($params);
                $respond(200, ['success' => true, 'message' => 'Category updated.', 'id' => $id], (string) $id);
            } elseif ($action === 'delete') {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) $respond(400, ['success' => false, 'error' => 'Field "id" is required.']);
                $stmt = $pdo->prepare("UPDATE news_categories SET deleted_at = NOW(), status = 'inactive' WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) $respond(404, ['success' => false, 'error' => 'Category not found.'], (string) $id);
                $respond(200, ['success' => true, 'message' => 'Category soft-deleted.', 'id' => $id], (string) $id);
            }
            break;

        // ==================== BANNERS ====================
        case 'banners':
            if ($action === 'read') {
                $id = isset($input['id']) ? (int) $input['id'] : null;
                if ($id) {
                    $stmt = $pdo->prepare("SELECT * FROM 9s_custom_banners WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch();
                    if (!$row) $respond(404, ['success' => false, 'error' => 'Banner not found.'], (string) $id);
                    $respond(200, ['success' => true, 'data' => $row], (string) $id);
                }
                $rows = $pdo->query("SELECT * FROM 9s_custom_banners WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll();
                $respond(200, ['success' => true, 'data' => $rows]);
            } elseif ($action === 'create') {
                $name = trim((string) ($input['name'] ?? ''));
                $url = trim((string) ($input['url'] ?? ''));
                $image = trim((string) ($input['image'] ?? ''));
                if ($name === '' || $url === '' || $image === '') {
                    $respond(400, ['success' => false, 'error' => 'Fields "name", "url", and "image" are required.']);
                }
                $slug = api_random_code(10);
                $stmt = $pdo->prepare("INSERT INTO 9s_custom_banners (slug, name, image, url, price, promo_price, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$slug, $name, $image, $url, $input['price'] ?? null, $input['promo_price'] ?? null, isset($input['is_active']) ? (int) $input['is_active'] : 1]);
                $newId = (string) $pdo->lastInsertId();
                $respond(201, ['success' => true, 'message' => 'Banner created.', 'data' => ['id' => (int) $newId, 'slug' => $slug]], $newId);
            } elseif ($action === 'update') {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) $respond(400, ['success' => false, 'error' => 'Field "id" is required.']);
                $sets = [];
                $params = [];
                foreach (['name', 'image', 'url', 'price', 'promo_price', 'is_active'] as $col) {
                    if (array_key_exists($col, $input)) {
                        $sets[] = "`$col` = ?";
                        $params[] = $col === 'is_active' ? (int) $input[$col] : $input[$col];
                    }
                }
                if (!$sets) $respond(400, ['success' => false, 'error' => 'No valid fields provided.'], (string) $id);
                $params[] = $id;
                $pdo->prepare("UPDATE 9s_custom_banners SET " . implode(', ', $sets) . " WHERE id = ? AND deleted_at IS NULL")->execute($params);
                $respond(200, ['success' => true, 'message' => 'Banner updated.', 'id' => $id], (string) $id);
            } elseif ($action === 'delete') {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) $respond(400, ['success' => false, 'error' => 'Field "id" is required.']);
                $stmt = $pdo->prepare("UPDATE 9s_custom_banners SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) $respond(404, ['success' => false, 'error' => 'Banner not found.'], (string) $id);
                $respond(200, ['success' => true, 'message' => 'Banner soft-deleted.', 'id' => $id], (string) $id);
            }
            break;

        // ==================== SHORT LINKS ====================
        case 'shortlinks':
            if ($action === 'read') {
                $id = isset($input['id']) ? (int) $input['id'] : null;
                if ($id) {
                    $stmt = $pdo->prepare("SELECT * FROM short_links WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch();
                    if (!$row) $respond(404, ['success' => false, 'error' => 'Short link not found.'], (string) $id);
                    $respond(200, ['success' => true, 'data' => $row], (string) $id);
                }
                $rows = $pdo->query("SELECT * FROM short_links WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 100")->fetchAll();
                $respond(200, ['success' => true, 'data' => $rows]);
            } elseif ($action === 'create') {
                $dest = trim((string) ($input['destination_url'] ?? ''));
                if ($dest === '') $respond(400, ['success' => false, 'error' => 'Field "destination_url" is required.']);
                $code = !empty($input['short_code']) ? trim((string) $input['short_code']) : api_random_code(5);
                $stmt = $pdo->prepare("INSERT INTO short_links (short_code, destination_url, clicks) VALUES (?, ?, 0)");
                $stmt->execute([$code, $dest]);
                $newId = (string) $pdo->lastInsertId();
                $respond(201, ['success' => true, 'message' => 'Short link created.', 'data' => ['id' => (int) $newId, 'short_code' => $code, 'short_url' => 'https://9sty.com/s/' . $code]], $newId);
            } elseif ($action === 'update') {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) $respond(400, ['success' => false, 'error' => 'Field "id" is required.']);
                $sets = [];
                $params = [];
                foreach (['short_code', 'destination_url'] as $col) {
                    if (array_key_exists($col, $input)) {
                        $sets[] = "`$col` = ?";
                        $params[] = trim((string) $input[$col]);
                    }
                }
                if (!$sets) $respond(400, ['success' => false, 'error' => 'No valid fields provided.'], (string) $id);
                $params[] = $id;
                $pdo->prepare("UPDATE short_links SET " . implode(', ', $sets) . " WHERE id = ? AND deleted_at IS NULL")->execute($params);
                $respond(200, ['success' => true, 'message' => 'Short link updated.', 'id' => $id], (string) $id);
            } elseif ($action === 'delete') {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) $respond(400, ['success' => false, 'error' => 'Field "id" is required.']);
                $stmt = $pdo->prepare("UPDATE short_links SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) $respond(404, ['success' => false, 'error' => 'Short link not found.'], (string) $id);
                $respond(200, ['success' => true, 'message' => 'Short link soft-deleted.', 'id' => $id], (string) $id);
            }
            break;

        // ==================== COMMENTS ====================
        case 'comments':
            if ($action === 'read') {
                $id = isset($input['id']) ? (int) $input['id'] : null;
                if ($id) {
                    $stmt = $pdo->prepare("SELECT * FROM news_comments WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch();
                    if (!$row) $respond(404, ['success' => false, 'error' => 'Comment not found.'], (string) $id);
                    $respond(200, ['success' => true, 'data' => $row], (string) $id);
                }
                $where = ['deleted_at IS NULL'];
                $params = [];
                if (!empty($input['post_id'])) {
                    $where[] = 'post_id = ?';
                    $params[] = (int) $input['post_id'];
                }
                $stmt = $pdo->prepare("SELECT * FROM news_comments WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 100");
                $stmt->execute($params);
                $respond(200, ['success' => true, 'data' => $stmt->fetchAll()]);
            } elseif ($action === 'create') {
                $postId = (int) ($input['post_id'] ?? 0);
                $name = trim((string) ($input['name'] ?? ''));
                $comment = trim((string) ($input['comment'] ?? ''));
                if ($postId <= 0 || $name === '' || $comment === '') {
                    $respond(400, ['success' => false, 'error' => 'Fields "post_id", "name", and "comment" are required.']);
                }
                $status = in_array(($input['status'] ?? 'approved'), ['approved', 'hidden'], true) ? $input['status'] : 'approved';
                $stmt = $pdo->prepare("INSERT INTO news_comments (post_id, parent_id, name, email, comment, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$postId, !empty($input['parent_id']) ? (int) $input['parent_id'] : null, $name, trim((string) ($input['email'] ?? '')), $comment, $status]);
                $newId = (string) $pdo->lastInsertId();
                $respond(201, ['success' => true, 'message' => 'Comment created.', 'data' => ['id' => (int) $newId]], $newId);
            } elseif ($action === 'update') {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) $respond(400, ['success' => false, 'error' => 'Field "id" is required.']);
                $sets = [];
                $params = [];
                foreach (['name', 'email', 'comment', 'status'] as $col) {
                    if (array_key_exists($col, $input)) {
                        $sets[] = "`$col` = ?";
                        $params[] = (string) $input[$col];
                    }
                }
                if (!$sets) $respond(400, ['success' => false, 'error' => 'No valid fields provided.'], (string) $id);
                $params[] = $id;
                $pdo->prepare("UPDATE news_comments SET " . implode(', ', $sets) . " WHERE id = ? AND deleted_at IS NULL")->execute($params);
                $respond(200, ['success' => true, 'message' => 'Comment updated.', 'id' => $id], (string) $id);
            } elseif ($action === 'delete') {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) $respond(400, ['success' => false, 'error' => 'Field "id" is required.']);
                $stmt = $pdo->prepare("UPDATE news_comments SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) $respond(404, ['success' => false, 'error' => 'Comment not found.'], (string) $id);
                $respond(200, ['success' => true, 'message' => 'Comment soft-deleted.', 'id' => $id], (string) $id);
            }
            break;

        default:
            api_json(404, ['success' => false, 'error' => 'Unknown API resource.']);
    }
} catch (Throwable $e) {
    error_log('[REST API] ' . $e->getMessage());
    $respond(500, ['success' => false, 'error' => 'Internal server error.']);
}
