<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/web-logic.php';
header('Content-Type: application/json');

$username = mb_strtolower(trim($_GET['username'] ?? ''), 'UTF-8');

if (!preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
    echo json_encode(['available' => false, 'reason' => 'format']);
    exit;
}

$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
$taken = $stmt->num_rows > 0;
$stmt->close();
$conn->close();

echo json_encode(['available' => !$taken]);
