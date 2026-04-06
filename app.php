<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/require_creator.php';

$user = $GLOBALS['AUTH_USER'];

$has_unread_notifications = false;
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
<link rel="stylesheet" href="source/styles/main-app/main.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source/styles/main-app/dashboard.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source/styles/main-app/feed.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source/styles/main-app/profile.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source/styles/main-app/messages.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source/styles/main-app/openclasses.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source/styles/main-app/social.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/source/styles/main-app/view-chats.css?v=<?= APP_VERSION ?>">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
<link rel="icon" href="/assets/logo.png">
</head>
<body>

<nav class="top-nav">
    <div class="nav-left">
        <img class="pfp-nav" src="<?= htmlspecialchars($user['pfp']) ?>" alt="pfp">

        <div class="nav-text">
            <span class="nav-sub"><?= $T['welcome'] ?></span>
            <div class="flex-nav">
                <span class="nav-name"><?= htmlspecialchars($user['username']) ?></span>
                <img class="badge-nav" src="/source/assets/verify_badge.png">
            </div>
        </div>
    </div>

    <a href="settings/settings.php" class="nav-right">
        <img src="source/assets/settings.png" alt="settings">
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
<script src="source-dancer/scripts/follow.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source/scripts/stats-charts.js?v=<?= APP_VERSION ?>" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    
function setMode(mode, el) {
    window.currentMode = mode;

    // 1. Toggle Button Classes
    document.querySelectorAll('.stats-toggle button')
        .forEach(btn => btn.classList.remove('active'));

    if (el) el.classList.add('active');

    // 2. Toggle Stat Cards Visibility
    const s30 = document.getElementById('stats-30');
    const sAll = document.getElementById('stats-all');

    if (mode === '30') {
        s30.style.display = 'flex'; // or 'grid' depending on your CSS
        sAll.style.display = 'none';
    } else {
        s30.style.display = 'none';
        sAll.style.display = 'flex';
    }

    // 3. Update the Chart
    if (typeof window.updateChart === 'function') {
        window.updateChart();
    }
}

</script>

</body>
</html>
