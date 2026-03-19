<?php

require $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';

header('Content-Type: application/json');

try {
    $stmt = $conn->prepare("SELECT status, message, estimated_end, updated_at FROM system_status WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        echo json_encode(['status' => 0]);
        exit;
    }

    echo json_encode([
        'status'        => (int)$result['status'],
        'message'       => $result['message'],
        'estimated_end' => date('c', strtotime($result['estimated_end'])),
        'updated_at'    => date('c', strtotime($result['updated_at'])),
        'server_time'   => date('c')
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error', 'status' => 1]);
}
exit; 