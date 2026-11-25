<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$postId    = !empty($data['post_id']) ? (int)$data['post_id'] : null;
$shortCode = trim((string)($data['short_code'] ?? ''));
$siCode    = trim((string)($data['si_code'] ?? ''));
$platform  = strtolower(trim((string)($data['platform'] ?? 'share')));

if ($shortCode === '' || strlen($shortCode) > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid short_code']);
    exit;
}

if (!preg_match('/^[A-Za-z0-9]{5,16}$/', $siCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid si_code']);
    exit;
}

$allowedPlatforms = ['facebook', 'twitter', 'linkedin', 'telegram', 'whatsapp', 'copy', 'share'];
if (!in_array($platform, $allowedPlatforms, true)) {
    $platform = 'share';
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip = trim(explode(',', $ip)[0]);
$ipHash = $ip !== '' ? hash('sha256', $ip . '9sty_share_si_salt') : null;
$userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

try {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO post_share_links
            (post_id, short_code, si_code, platform, ip_hash, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$postId, $shortCode, $siCode, $platform, $ipHash, $userAgent]);

    echo json_encode(['success' => true, 'si_code' => $siCode]);
} catch (Throwable $e) {
    error_log('share-api error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
