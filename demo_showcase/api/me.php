<?php
require __DIR__ . '/../secure/logic.php';

header('Content-Type: application/json');

if (
    empty($_COOKIE['dancefy_token']) ||
    !preg_match('/^[a-f0-9]{64}$/', $_COOKIE['dancefy_token'])
) {
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
    exit;
}

$tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

$q = $conn->prepare("
    SELECT u.public_id, u.is_creator
    FROM user_tokens t
    JOIN users u ON u.user_id = t.user_id
    WHERE t.token_hash = ?
    LIMIT 1
");
$q->bind_param("s", $tokenHash);
$q->execute();
$q->store_result();

if ($q->num_rows !== 1) {
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
    exit;
}

$q->bind_result($public_id, $is_creator);
$q->fetch();
$q->close();

echo json_encode([
    'authenticated' => true,
    'public_id' => $public_id,
    'is_creator' => (int)$is_creator
]);
