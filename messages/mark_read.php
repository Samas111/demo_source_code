<?php
require __DIR__ . '/../secure/logic.php';
$me = $_SESSION['public_id'] ?? null;
$with = $_GET['with'] ?? '';

if ($me && $with) {
    $stmt = $conn->prepare("UPDATE dms SET is_read = 1 WHERE sender_public_id = ? AND receiver_public_id = ? AND is_read = 0");
    $stmt->bind_param('ss', $with, $me);
    $stmt->execute();
}