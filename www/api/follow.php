<?php

error_reporting(E_ALL);

$consoleLogs = [];
function console_log($msg) {
    global $consoleLogs;
    $consoleLogs[] = $msg;
}

error_log('FOLLOW.PHP: script started');
console_log('FOLLOW.PHP: script started');

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
error_log('FOLLOW.PHP: logic.php loaded');
console_log('FOLLOW.PHP: logic.php loaded');

if (empty($_COOKIE['dancefy_token'])) {
    console_log('FOLLOW.PHP: missing dancefy_token cookie');
    http_response_code(400);
    exit;
}

if (empty($_POST['followed'])) {
    console_log('FOLLOW.PHP: missing POST followed');
    http_response_code(400);
    exit;
}

console_log('FOLLOW.PHP: input validated');

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($mysqli->connect_error) {
    console_log('FOLLOW.PHP: DB connect error');
    http_response_code(500);
    exit;
}

$mysqli->set_charset('utf8mb4');
console_log('FOLLOW.PHP: DB connected');

$tokenHash = hash('sha256', $_COOKIE['dancefy_token']);
console_log('FOLLOW.PHP: token hashed');

$stmt = $mysqli->prepare("
    SELECT u.public_id
    FROM user_tokens ut
    JOIN users u ON u.user_id = ut.user_id
    WHERE ut.token_hash = ?
    LIMIT 1
");

if (!$stmt) {
    console_log('FOLLOW.PHP: prepare failed (select)');
    http_response_code(500);
    exit;
}

$stmt->bind_param('s', $tokenHash);

if (!$stmt->execute()) {
    console_log('FOLLOW.PHP: select execute failed');
    http_response_code(500);
    exit;
}

$stmt->bind_result($follower);
$stmt->fetch();
$stmt->close();

console_log('FOLLOW.PHP: follower = ' . ($follower ?? 'NULL'));

if (!$follower) {
    console_log('FOLLOW.PHP: invalid token');
    http_response_code(401);
    exit;
}

$followed = $_POST['followed'];
console_log('FOLLOW.PHP: followed = ' . $followed);

$insert = $mysqli->prepare("
    INSERT IGNORE INTO user_follows 
    (follower_public_id, followed_public_id)
    VALUES (?, ?)
");

if (!$insert) {
    console_log('FOLLOW.PHP: prepare failed (insert)');
    http_response_code(500);
    exit;
}

$insert->bind_param('ss', $follower, $followed);

if (!$insert->execute()) {
    console_log('FOLLOW.PHP: insert execute failed');
    http_response_code(500);
    exit;
}

$wasInserted = $insert->affected_rows === 1;
console_log('FOLLOW.PHP: follow inserted = ' . ($wasInserted ? 'YES' : 'NO'));

$insert->close();

if ($wasInserted && $follower !== $followed) {
    $username = null;

    $userStmt = $mysqli->prepare("
        SELECT username
        FROM users
        WHERE public_id = ?
        LIMIT 1
    ");

    if ($userStmt) {
        $userStmt->bind_param('s', $follower);
        $userStmt->execute();
        $userStmt->bind_result($username);
        $userStmt->fetch();
        $userStmt->close();
    }

    $title = 'Nový sledující';
    $displayName = $username ?: 'Někdo';
    $content = $displayName . ' vás začal sledovat';
    $link = 'none';

    $notif = $mysqli->prepare("
        INSERT INTO notifications
        (recipient_public_id, actor_public_id, title, content, link, created_at, is_read)
        VALUES (?, ?, ?, ?, ?, NOW(), 0)
    ");

    if (!$notif) {
        console_log('FOLLOW.PHP: notification prepare failed: ' . $mysqli->error);
    } else {
        $notif->bind_param(
            'sssss',
            $followed,
            $follower,
            $title,
            $content,
            $link
        );

        if (!$notif->execute()) {
            console_log('FOLLOW.PHP: notification execute failed: ' . $notif->error);
        } else {
            console_log('FOLLOW.PHP: notification inserted OK');
        }

        $notif->close();
    }
}

$mysqli->close();
console_log('FOLLOW.PHP: done');

echo '<script>';
foreach ($consoleLogs as $log) {
    echo 'console.log(' . json_encode($log) . ');';
}
echo '</script>';

echo 'ok';
exit;
