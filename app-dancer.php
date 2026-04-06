<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';

$pfp_path    = $GLOBALS['AUTH_USER']['pfp'];
$username    = $GLOBALS['AUTH_USER']['username'];
$display_name = $GLOBALS['AUTH_USER']['name'];

$show_profile_popup = (
    stripos($display_name, 'Dancefy') !== false ||
    str_contains($pfp_path, 'default')
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dancefy Solo</title>
<link rel="stylesheet" href="source-dancer/styles/main.css?version=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source-dancer/styles/dashboard.css?version=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source-dancer/styles/feed.css?version=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source-dancer/styles/profile.css?version=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source-dancer/styles/post.css?version=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source-dancer/styles/social.css?version=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source-dancer/styles/portfolio.css?version=<?= APP_VERSION ?>">
<link rel="stylesheet" href="source-dancer/styles/view-chats.css?version=<?= APP_VERSION ?>">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
<link rel="icon" href="/assets/logo.png">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
</head>
<body>

<nav class="top-nav">
    <div class="nav-left">
        <img class="pfp-nav" src="<?= htmlspecialchars($pfp_path) ?>" alt="pfp">

        <div class="nav-text">
            <span class="nav-sub"><?= $T['welcome'] ?></span>
            <span class="nav-name"><?= htmlspecialchars($username) ?></span>
        </div>
    </div>

    <a href="settings/settings.php" class="nav-right">
        <img src="source/assets/settings.png" alt="settings">
    </a>
</nav>

<div id="content" class="fade"></div>

<nav class="bottom-nav">
    <div class="menu">
        <div class="nav-item" data-page="portfolio"><img src="source-dancer/assets/menu-social.png" alt="icon"></div>
        <div class="nav-item" data-page="openclasses"><img src="source-dancer/assets/menu-feed.png" alt="icon"></div>
        <div class="nav-item" data-page="profile"><img src="source-dancer/assets/menu-profile.png" alt="icon"></div>
        <div class="nav-item" data-page="chat"><img src="source-dancer/assets/menu-message.png" alt="icon"></div>
    </div>
</nav>

<?php if ($show_profile_popup): ?>
    <div id="profile-nudge-overlay">
        <div id="profile-nudge-card">
            <button class="nudge-close" onclick="dismissProfileNudge()" aria-label="Close">&#x2715;</button>
            <p class="nudge-label"><?= $T['nudge_label'] ?></p>
            <p class="nudge-title"><?= $T['nudge_title'] ?></p>
            <p class="nudge-sub"><?= $T['nudge_sub'] ?></p>
            <div class="nudge-actions">
                <a href="settings/profile.php" class="nudge-btn-primary"><?= $T['edit_profile'] ?></a>
                <button onclick="dismissProfileNudge()" class="nudge-btn-skip"><?= $T['skip'] ?></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
<script src="source-dancer/scripts/main.js?v=<?= APP_VERSION ?>"></script>
<script src="source-dancer/scripts/scroll-hint.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source-dancer/scripts/feed.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source-dancer/scripts/feed-sections.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source-dancer/scripts/follow.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source-dancer/scripts/likes.js?v=<?= APP_VERSION ?>" defer></script>
<script src="source-dancer/scripts/nudge.js?v=<?= APP_VERSION ?>" defer></script>

</body>
</html>