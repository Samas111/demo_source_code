<?php 

define("APP_VERSION", "2026-02-11-02");

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['public_id'])) {
    header("Location: " . APP_URL);
    exit;
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dancefy Solo</title>
<link rel="stylesheet" href="source-dancer/styles/main.css?version=2">
<link rel="stylesheet" href="source-dancer/styles/dashboard.css?version=4">
<link rel="stylesheet" href="source-dancer/styles/feed.css?version=2">
<link rel="stylesheet" href="source-dancer/styles/profile.css?version=2">
<link rel="stylesheet" href="source-dancer/styles/post.css?version=2">
<link rel="stylesheet" href="source-dancer/styles/social.css?version=2">
<link rel="stylesheet" href="source-dancer/styles/view-chats.css?version=2">
<link rel="stylesheet" href="source-dancer/styles/map.css?version=2">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
<link rel="icon" href="logo.png">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
</head>
<body>

<nav>
    <img style="visibility: hidden;" src="source/assets/bell.png">
    <span>Dancefy</span>
    <a href="settings.php">
        <img src="source/assets/profile.png" alt="">
    </a>
</nav>

<div id="content" class="fade"></div>

<nav class="bottom-nav">
    <div class="menu">
        <div class="nav-item" data-page="feed"><img src="source-dancer/assets/menu-social.png" alt="icon"></div>
        <div class="nav-item" data-page="openclasses"><img src="source-dancer/assets/menu-feed.png" alt="icon"></div>
        <div class="nav-item" data-page="profile"><img src="source-dancer/assets/menu-profile.png" alt="icon"></div>
        <div class="nav-item" data-page="chat"><img src="source-dancer/assets/menu-message_new.png" alt="icon"></div>
    </div>
</nav>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>


<script src="source-dancer/scripts/main.js?v=<?= APP_VERSION ?>"></script>
<script src="source-dancer/scripts/scroll-hint.js?v=<?= APP_VERSION ?>" defer></script>

<script src="source-dancer/scripts/feed.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source-dancer/scripts/feed-sections.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source-dancer/scripts/follow.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source-dancer/scripts/likes.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source-dancer/scripts/map.js?v=<?= APP_VERSION ?>" defer></script>


</body>
</html>