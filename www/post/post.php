<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header("Location: " . APP_URL);
    exit;
}

require __DIR__ . '/../secure/logic.php';
require_once __DIR__ . '/../secure/auth.php';
require_once __DIR__ . '/../secure/require_creator.php';
require_once __DIR__ . '/../languages/loader.php';

$userId = (int) $_SESSION['user_id'];

/* =========================
   LOAD USER CORE DATA
========================= */
$u = $conn->prepare("
    SELECT username, public_id, is_creator
    FROM users
    WHERE user_id = ?
    LIMIT 1
");
$u->bind_param("i", $userId);
$u->execute();
$u->bind_result($username, $public_id, $is_creator);

if (!$u->fetch()) {
    $u->close();
    $conn->close();
    exit('User not found');
}
$u->close();

/* =========================
   LOAD PROFILE PICTURE
========================= */
$pfp = '/uploads/profile-pictures/default.png';

$p = $conn->prepare("
    SELECT pfp_path
    FROM user_profile
    WHERE public_id = ?
    LIMIT 1
");
$p->bind_param("s", $public_id);
$p->execute();
$p->bind_result($pfp_path);

if ($p->fetch() && !empty($pfp_path)) {
    $pfp = htmlspecialchars($pfp_path, ENT_QUOTES, 'UTF-8');
}
$p->close();

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
        <p>Nemáš žádné aktivní OpenClass</p>
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
</div>

</body>
</html>