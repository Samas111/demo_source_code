<?php
require __DIR__ . '/../secure/logic.php';

// Ensure we return JSON for the JavaScript fetch
header('Content-Type: application/json');

$me = $_SESSION['public_id'] ?? null;
$with = $_GET['with'] ?? '';
$last_id = (int)($_GET['last_id'] ?? 0);

// Basic security check
if (!$me || !$with || !$last_id) {
    echo json_encode(['is_read' => false]);
    exit;
}

/**
 * We check the specific last message ID that YOU sent 
 * to see if the status in the database has changed to 1 (seen).
 */
$stmt = $conn->prepare("
    SELECT is_read 
    FROM dms 
    WHERE id = ? 
    AND sender_public_id = ? 
    AND receiver_public_id = ? 
    LIMIT 1
");

$stmt->bind_param('iss', $last_id, $me, $with);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'is_read' => (bool)($res['is_read'] ?? false)
]);

$stmt->close();