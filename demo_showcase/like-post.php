<?php
require __DIR__ . '/secure/logic.php';

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

if (
    empty($_COOKIE['dancefy_token']) ||
    !preg_match('/^[a-f0-9]{64}$/', $_COOKIE['dancefy_token']) ||
    empty($_POST['post_id'])
) {
    http_response_code(401);
    exit;
}

error_log("Script launched");

$postId = (int)$_POST['post_id'];
$action = $_POST['action'] ?? null;

$tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

$q = $mysqli->prepare("
    SELECT u.public_id
    FROM user_tokens t
    JOIN users u ON u.user_id = t.user_id
    WHERE t.token_hash = ?
    LIMIT 1
");
$q->bind_param('s', $tokenHash);
$q->execute();
$q->bind_result($publicId);
$q->fetch();
$q->close();

if (!$publicId || !in_array($action, ['like', 'unlike'], true)) {
    http_response_code(400);
    exit;
}

$mysqli->begin_transaction();

try {

    if ($action === 'like') {

        $stmt = $mysqli->prepare("
            INSERT IGNORE INTO post_likes (post_id, public_id)
            VALUES (?, ?)
        ");
        $stmt->bind_param('is', $postId, $publicId);
        $stmt->execute();

        if ($stmt->affected_rows === 1) {
            $mysqli->query("
                UPDATE posts
                SET likes = likes + 1
                WHERE id = {$postId}
            ");
        }

    } else {

        $stmt = $mysqli->prepare("
            DELETE FROM post_likes
            WHERE post_id = ? AND public_id = ?
        ");
        $stmt->bind_param('is', $postId, $publicId);
        $stmt->execute();

        if ($stmt->affected_rows === 1) {
            $mysqli->query("
                UPDATE posts
                SET likes = GREATEST(likes - 1, 0)
                WHERE id = {$postId}
            ");
        }
    }

    $mysqli->commit();
    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    $mysqli->rollback();
    http_response_code(500);
}
