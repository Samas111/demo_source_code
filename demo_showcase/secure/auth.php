<?php

require_once __DIR__ . '/logic.php';
require_once __DIR__ . '/db.php';

if (empty($_COOKIE['dancefy_token'])) {
    header("Location: " . APP_URL);
    echo json_encode([
        'error' => 'AUTH_REQUIRED'
    ]);
    exit;
}

$token = hash('sha256', $_COOKIE['dancefy_token']);

$stmt = $mysqli->prepare("
    SELECT u.user_id, u.is_creator
    FROM user_tokens t
    JOIN users u ON u.user_id = t.user_id
    WHERE t.token_hash = ?
    LIMIT 1
");

if (!$stmt) {
    throw new RuntimeException('Database prepare failed');
}

$stmt->bind_param("s", $token);
$stmt->execute();

$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    header("Location: " . APP_URL);
    exit;
}

define('AUTH_USER_ID', (int) $user['user_id']);
define('IS_CREATOR', (int) $user['is_creator']);
