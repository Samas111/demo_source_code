<?php
require_once __DIR__ . '/CENZUROVANO_V_DEMU/config.php';
require_once __DIR__ . '/../error-handle.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    renderError('Cannot connect', 'We are having trouble reaching the database. Please try again later.');
}

$conn->set_charset("utf8mb4");

$result = $conn->query("SELECT status FROM system_status LIMIT 1");

if ($result && $row = $result->fetch_assoc()) {
    if ((int)$row['status'] === 1) {

        $allowedPublicId = 'CENZUROVANO_V_DEMU';
        $userPublicId = null;

        if (!empty($_COOKIE['dancefy_token'])) {

            $tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

            $stmt = $conn->prepare("
                SELECT u.public_id
                FROM user_tokens ut
                JOIN users u ON u.user_id = ut.user_id
                WHERE ut.token_hash = ?
                LIMIT 1
            ");

            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();
            $stmt->bind_result($userPublicId);
            $stmt->fetch();
            $stmt->close();
        }

        $isAllowed = $userPublicId === $allowedPublicId;
        $current = $_SERVER['REQUEST_URI'] ?? '';

        if (!$isAllowed && !str_contains($current, 'break.php')) {
            header("Location: /CENZUROVANO_V_DEMU/break.php");
            exit;
        }
    }
}
?>
