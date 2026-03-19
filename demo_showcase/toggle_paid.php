<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once __DIR__ . '/secure/auth.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['public_id'], $data['openclass_id'], $data['status'])) {
    http_response_code(400);
    exit;
}

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$status = (int)$data['status'];

$stmt = $mysqli->prepare("
    UPDATE openclass_registrations 
    SET is_paid = ? 
    WHERE public_id = ? AND openclass_id = ?
");
$stmt->bind_param('iss', $status, $data['public_id'], $data['openclass_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
}