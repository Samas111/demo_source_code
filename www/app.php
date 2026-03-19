<?php
define("APP_VERSION", "2026-01-19-01");

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once __DIR__ . '/secure/auth.php';
require_once __DIR__ . '/secure/require_creator.php';


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['public_id'])) {
    header("Location: " . APP_URL);
    exit;
}

$has_unread_notifications = false;

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

$public_id = $_SESSION['public_id'];

$n = $mysqli->prepare("
    SELECT 1
    FROM notifications
    WHERE recipient_public_id = ?
      AND is_read = 0
    LIMIT 1
");
$n->bind_param("s", $public_id);
$n->execute();
$n->store_result();

$has_unread_notifications = $n->num_rows === 1;

$n->close();
$mysqli->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Dancefy Solo</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
<link rel="stylesheet" href="source/styles/main.css">
<link rel="stylesheet" href="source/styles/dashboard.css?version=3">
<link rel="stylesheet" href="source/styles/feed.css">
<link rel="stylesheet" href="source/styles/profile.css">
<link rel="stylesheet" href="source/styles/post.css">
<link rel="stylesheet" href="source/styles/messages.css">
<link rel="stylesheet" href="source/styles/social.css">
<link rel="stylesheet" href="/source/styles/view-chats.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
<link rel="icon" href="logo.png">
</head>
<body>

<nav>
    <a href="notifications.php">
        <img src="source/assets/<?= $has_unread_notifications ? 'bell-active.png' : 'bell.png' ?>" alt="Notifications" style="opacity: <?= $has_unread_notifications ? '1' : '0.45' ?>;">
    </a>
    <span>Dancefy</span>
    <a href="settings.php">
        <img src="source/assets/settings.png" alt="Profile">
    </a>
</nav>


<div id="content" class="fade"></div>

<nav class="bottom-nav">
    <div class="menu">
        <div class="nav-item" data-page="social"><img src="source/assets/menu-social.png" alt="icon"></div>
        <div class="nav-item" data-page="feed"><img src="source/assets/menu-feed.png" alt="icon"></div>
        <div class="nav-item" data-page="chat"><img src="source/assets/messages.png" alt="icon"></div>
        <div class="nav-item" data-page="dashboard"><img src="source/assets/menu-dashboard.png" alt="icon"></div>
        <div class="nav-item" data-page="profile"><img src="source/assets/menu-profile.png" alt="icon"></div>
    </div>
</nav>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
<script src="source/scripts/load-scripts.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source/scripts/main.js?v=<?= APP_VERSION ?>"></script>
<script src="source/scripts/search.js?v=<?= APP_VERSION ?>"></script>


</body>
</html>
