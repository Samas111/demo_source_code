<?php
session_start();
require __DIR__ . '/../../CENZUROVANO_V_DEMU/logic.php';

if (!empty($_COOKIE['dancefy_token'])) {

    $tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

    $stmt = $conn->prepare("
        DELETE FROM user_tokens
        WHERE token_hash = ?
    ");

    if ($stmt) {
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $stmt->close();
    }

    setcookie("dancefy_token", "", time() - 3600, "/");
}

$_SESSION = [];
session_destroy();

header("Location: /");
exit;