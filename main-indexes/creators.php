<?php

error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
date_default_timezone_set('Europe/Prague');

$user_public_id = $GLOBALS['AUTH_USER']['public_id'];
$isCreator      = $GLOBALS['AUTH_USER']['is_creator'];
$backPath        = $isCreator ? '../app.php?tab=feed' : '../app-dancer.php';

$creators = [];

$stmt = $mysqli->prepare("
    SELECT 
        u.public_id,
        u.username,
        COALESCE(up.pfp_path, 'default.png') AS pfp_path,

        COUNT(DISTINCT uf.follower_public_id) AS followers_count,
        COUNT(DISTINCT oc.id) AS openclasses_count,

        (
            COUNT(DISTINCT uf.follower_public_id)
            + COUNT(DISTINCT oc.id) * 10
        ) AS ranking_score

    FROM users u

    LEFT JOIN user_profile up 
        ON up.public_id = u.public_id

    LEFT JOIN user_follows uf 
        ON uf.followed_public_id = u.public_id

    LEFT JOIN openclasses oc
        ON oc.public_id = u.public_id

    WHERE u.is_creator = 1
    AND u.public_id != 'VxJNAQXBZx'

    GROUP BY u.public_id, u.username, up.pfp_path

    ORDER BY ranking_score DESC
");

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $creators[] = $row;
}

$stmt->close();

?> 
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenClass Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="main-css/creators.css?v=<?= APP_VERSION ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>

<style>
    body{
        background-color: var(--background-color);
    }
    .creators-page {
        width: 92%;
        margin: 20px auto;
    }

    .creators-search {
        margin-bottom: 16px;
    }

    .creators-search input {
        width: 90%;
        padding: 12px 16px;
        border-radius: 14px;
        border: none;
        outline: none;

        background: #0f0f0f;
        color: #fff;
        font-family: 'Inter', sans-serif;
        font-size: 0.9rem;
    }

    .creators-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: 100%;
        margin: auto;
    }

    .creator-row-page {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;

        background: var(--card-color);
        border-radius: 14px;
        cursor: pointer;
    }

    .creator-row-page img {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        object-fit: cover;
    }

    .creator-info {
        display: flex;
        flex-direction: column;
        text-align: left;
    }

    .creator-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #fff;
    }

    .creator-followers {
        font-size: 0.7rem;
        opacity: 0.5;
    }
</style>

<nav class="nav">
    <a href="<?= $backPath ?>" class="nav-btn nav-left" aria-label="Back">
        <svg viewBox="0 0 24 24">
            <path d="M15 18l-6-6 6-6" />
        </svg>
    </a>

    <h1 class="nav-title">Tvůrci Dancefy</h1>
</nav>

<div class="creators-page">

    <div class="creators-search">
        <input 
            type="text" 
            id="creatorSearch"
            placeholder="Hledat tvůrce…"
            autocomplete="off"
        >
    </div>

    <div class="creators-list" id="creatorsList">
        <?php foreach ($creators as $c): ?>
            <div 
                class="creator-row-page"
                data-username="<?= strtolower(htmlspecialchars($c['username'])) ?>"
                onclick="window.location='view-profile.php?public_id=<?= htmlspecialchars($c['public_id']) ?>'"
            >
                <img 
                    src="../<?= htmlspecialchars($c['pfp_path']) ?>" 
                    alt="pfp"
                >

                <div class="creator-info">
                    <span class="creator-name">
                        <?= htmlspecialchars($c['username']) ?>
                    </span>

                    <?php
                    $parts = [];

                    if ((int)$c['openclasses_count'] > 0) {
                        $parts[] = (int)$c['openclasses_count'] . ' openclass' . ($c['openclasses_count'] == 1 ? '' : 'ů');
                    }

                    if ((int)$c['followers_count'] > 0) {
                        $parts[] = number_format($c['followers_count'], 0, ',', ' ') . ' sledujících';
                    }

                    $displayText = implode(' · ', $parts);
                    ?>

                    <?php if (!empty($displayText)): ?>
                        <span class="creator-followers">
                            <?= $displayText ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.getElementById('creatorSearch')?.addEventListener('input', e => {
    const value = e.target.value.toLowerCase();
    document.querySelectorAll('.creator-row-page').forEach(row => {
        row.style.display = row.dataset.username.includes(value)
            ? 'flex'
            : 'none';
    });
});
</script>

</body>
</html>
