<?php
require __DIR__ . '/../secure/logic.php';

$me = $_SESSION['public_id'] ?? null;
if (!$me) {
    http_response_code(401);
    exit;
}

$with = $_GET['with'] ?? '';
$after = (int)($_GET['after'] ?? 0);

$stmt = $conn->prepare("
    SELECT id, sender_public_id, message
    FROM dms
    WHERE id > ?
      AND (
        (sender_public_id = ? AND receiver_public_id = ?)
        OR
        (sender_public_id = ? AND receiver_public_id = ?)
      )
    ORDER BY id ASC
");
$stmt->bind_param('issss', $after, $me, $with, $with, $me);
$stmt->execute();
$res = $stmt->get_result();

echo json_encode($res->fetch_all(MYSQLI_ASSOC));
$stmt->close();
