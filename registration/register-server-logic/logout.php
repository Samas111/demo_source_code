<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../private/config.php';

$conn = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$conn->set_charset('utf8mb4');

/* DELETE TOKEN FROM DB */
if (!empty($_COOKIE['dancefy_token']) && preg_match('/^[a-f0-9]{64}$/', $_COOKIE['dancefy_token'])) {
    $tokenHash = hash('sha256', $_COOKIE['dancefy_token']);
    $stmt = $conn->prepare("DELETE FROM user_tokens WHERE token_hash = ?");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $stmt->close();
}

/* DELETE COOKIE (MUST MATCH CREATION) */
setcookie("dancefy_token", "", [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

/* DESTROY SESSION */
$_SESSION = [];
session_unset();
session_destroy();

$conn->close();
header("Location: /registration/register-indexes/login.php");
exit;
