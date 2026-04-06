<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/require_creator.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/languages/loader.php';

$public_id  = $GLOBALS['AUTH_USER']['public_id'];
$username   = $GLOBALS['AUTH_USER']['username'];
$is_creator = $GLOBALS['AUTH_USER']['is_creator'];

$pfp_raw = $GLOBALS['AUTH_USER']['pfp'];
$pfp = (!empty($pfp_raw) && $pfp_raw !== 'default.png')
    ? htmlspecialchars($pfp_raw, ENT_QUOTES, 'UTF-8')
    : '/uploads/profile-pictures/default.png';

/* =========================
   NEW: LOAD LAST PUBLISHED CLASS
========================= */
$last_class = null;
$lc_stmt = $conn->prepare("SELECT openclass_id, title, cover_image FROM openclasses WHERE public_id = ? ORDER BY id DESC LIMIT 1");
$lc_stmt->bind_param("s", $public_id);
$lc_stmt->execute();
$lc_res = $lc_stmt->get_result();
if ($row = $lc_res->fetch_assoc()) {
    $last_class = $row;
}
$lc_stmt->close();

/* =========================
   LOAD DRAFTS
========================= */
$drafts = [];
$d_stmt = $conn->prepare("SELECT openclass_id, title, cover_image FROM drafts WHERE public_id = ? ORDER BY id DESC");
$d_stmt->bind_param("s", $public_id);
$d_stmt->execute();
$d_res = $d_stmt->get_result();
while ($row = $d_res->fetch_assoc()) {
    $drafts[] = $row;
}
$d_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Dancefy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="post.css?version=2">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
    <link rel="icon" href="../logo.png">
</head>
<body>

<nav>
    <a href="../app.php?tab=feed">
        <img src="../source/assets/back.png" alt="Back">
    </a>
    <span>Dancefy</span>
    <a href="#" style="visibility: hidden;">
        <img src="#" alt="#">
    </a>
</nav>

<div class="container-post">
    <div class="hint">
        <p>Sdílej svou další OpenClass</p>
    </div>

    <div class="modal">
        <div class="avatar">
            <img style="border-radius: 50%;" src="<?= $pfp ?>" alt="Profile picture">
        </div>

        <span>Vytvoř  Openclass</span>
        <div class="under">Tvoji tanečníci čekjí na nový termín.</div>

        <a href="/post/listing.php">Připravit OpenClass</a>

        <p>Příprava Openclass zabere méně než 60 sekund</p>
    </div>

    <?php if (!empty($drafts)): ?>
        <div class="drafts-section">
            <h3>Čeká na publikování</h3>
            <?php foreach ($drafts as $draft): ?>
                <a href="/post/listing.php?id=<?= $draft['openclass_id'] ?>" class="draft-card">
                    <?php 
                        $img = (!empty($draft['cover_image']) && $draft['cover_image'] !== 'default-openclass.png') 
                                ? '/uploads/openclasses/'.$draft['cover_image'] 
                                : '/default-openclass.png';
                    ?>
                    <img src="<?= $img ?>" class="draft-img">
                    <div class="draft-info">
                        <span><?= htmlspecialchars($draft['title'] ?: 'Bez názvu') ?></span>
                        <p>Pokračovat v úpravách →</p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($last_class): ?>
        <div class="drafts-section">
            <h3>Zopakovat lekci</h3>
            <a href="/post/repeat.php?id=<?= $last_class['openclass_id'] ?>" class="draft-card">
                <?php 
                    $lc_img = (!empty($last_class['cover_image']) && $last_class['cover_image'] !== 'default-openclass.png') 
                            ? '/uploads/openclasses/'.$last_class['cover_image'] 
                            : '/default-openclass.png';
                ?>
                <img src="<?= $lc_img ?>" class="draft-img">
                <div class="draft-info">
                    <span><?= htmlspecialchars($last_class['title']) ?></span>
                    <p>Použít jako šablonu →</p>
                </div>
            </a>
        </div>
    <?php endif; ?>
</div><br><br><br><br><br>

</body>
</html>