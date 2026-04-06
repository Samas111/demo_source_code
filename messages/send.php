<?php
require __DIR__ . '/../secure/logic.php';

$me = $_SESSION['public_id'] ?? null;
if (!$me) {
    http_response_code(401);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$to = $data['to'] ?? '';
$msg = trim($data['message'] ?? '');

if ($to === '' || $msg === '') {
    http_response_code(400);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO dms (sender_public_id, receiver_public_id, message)
    VALUES (?, ?, ?)
");
$stmt->bind_param('sss', $me, $to, $msg);
$stmt->execute();
$stmt->close();

echo json_encode(['ok'=>true]);
