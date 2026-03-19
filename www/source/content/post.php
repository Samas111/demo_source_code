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

require __DIR__ . '/../../secure/logic.php';
require_once __DIR__ . '/../../secure/auth.php';
require_once __DIR__ . '/../../secure/require_creator.php';
require_once __DIR__ . '/../../languages/loader.php';



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
   (PUBLIC_ID IS THE REAL FK)
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
$conn->close();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Dancefy</title>
</head>
<body>

<div class="modal">
    <div class="avatar">
        <img style="border-radius: 50%;" src="<?= $pfp ?>" alt="Profile picture">
    </div>

    <span><?= $T['to_add'] ?></span>

    <a href="/post/openclass.php"><?= $T['openclass'] ?></a>
    <a href="/post/tweet.php"><?= $T['post'] ?></a>


    <p><?= $T['with_flw'] ?></p>
</div>

</body>
</html>
