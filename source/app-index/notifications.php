<?php

error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
date_default_timezone_set('Europe/Prague');

$public_id = $GLOBALS['AUTH_USER']['public_id'];

$stmt = $mysqli->prepare("
    SELECT 
        n.id,
        n.title,
        n.content,
        n.link,
        n.created_at,
        n.is_read,
        up.pfp_path AS actor_pfp
    FROM notifications n
    LEFT JOIN user_profile up 
        ON up.public_id = n.actor_public_id
    WHERE n.recipient_public_id = ?
      AND n.created_at >= (NOW() - INTERVAL 3 MONTH)
    ORDER BY n.created_at DESC
    LIMIT 30
");
$stmt->bind_param("s", $public_id);
$stmt->execute();
$result = $stmt->get_result();


$stmt->bind_param("s", $public_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
$unread_ids = [];

while ($row = $result->fetch_assoc()) {

    if (!empty($row['actor_pfp'])) {
        if (str_starts_with($row['actor_pfp'], '/')) {
            $row['actor_pfp'] = ltrim($row['actor_pfp'], '/');
        }
    } else {
        $row['actor_pfp'] = 'source/assets/default.png';
    }

    $notifications[] = $row;

    if ((int)$row['is_read'] === 0) {
        $unread_ids[] = (int)$row['id'];
    }
}

$stmt->close();

if (!empty($unread_ids)) {
    $placeholders = implode(',', array_fill(0, count($unread_ids), '?'));
    $types = str_repeat('i', count($unread_ids));

    $stmt = $mysqli->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$unread_ids);
    $stmt->execute();
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikace</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="/style-variables/global.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="../styles/app-index/notifications.css?v=<?= APP_VERSION ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>

<nav class="nav">
    <a href="javascript:void(0)" 
       class="nav-btn nav-left" 
       aria-label="Back" 
       onclick="window.history.back(); return false;">
        <svg viewBox="0 0 24 24">
            <path d="M15 18l-6-6 6-6" />
        </svg>
    </a>
    <h1 class="nav-title">Notifikace</h1>
</nav>

<div class="notifications">

    <?php if (empty($notifications)): ?>
        <div class="notification-empty">
            Zatím žádné notifikace
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>

            <?php
                $isClickable = !empty($n['link']) && $n['link'] !== 'none';
                $tagOpen  = $isClickable
                    ? '<a href="' . htmlspecialchars($n['link']) . '" class="notification-link">'
                    : '<div class="notification-wrapper">';
                $tagClose = $isClickable ? '</a>' : '</div>';
            ?>

            <?= $tagOpen ?>
                <div class="notification <?= $n['is_read'] ? 'read' : 'unread' ?>">
                    <div class="notification-avatar">
                        <img src="/../<?= htmlspecialchars($n['actor_pfp']) ?>" alt="">
                    </div>

                    <div class="notification-body">
                        <div class="notification-title">
                            <?= htmlspecialchars($n['title']) ?>
                        </div>
                        <div class="notification-content">
                            <?= htmlspecialchars($n['content']) ?>
                        </div>
                        <div class="notification-time">
                            <?= date('d.m.Y H:i', strtotime($n['created_at'])) ?>
                        </div>
                    </div>
                </div>
            <?= $tagClose ?>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>
