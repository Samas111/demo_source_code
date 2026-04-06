<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';

$publicId  = $GLOBALS['AUTH_USER']['public_id'];
$isCreator = (int)$GLOBALS['AUTH_USER']['is_creator'];

$r = [
    'username' => $GLOBALS['AUTH_USER']['username'],
    'pfp_path' => $GLOBALS['AUTH_USER']['pfp'],
];

function safe($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

if ($isCreator === 1) {
    $back_path = '../app.php';
} else {
    $back_path = '../app-dancer.php';
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nastavení Profilu</title>
    <link rel="stylesheet" href="settings.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
</head>
<body>

<nav class="nav">
    <a href="<?php echo safe($back_path); ?>" class="nav-btn nav-left" aria-label="Back">
        <svg viewBox="0 0 24 24">
            <path d="M15 18l-6-6 6-6" />
        </svg>
    </a>
</nav>

<div class="profile-section">
    <div class="pfp-wrapper">
        <img src="../<?php echo safe($r['pfp_path']); ?>" class="pfp" loading="lazy">
    </div>
    <span><?php echo safe($r['username']); ?></span>
    <p><?= ($isCreator === 1) ? $T['d_creator'] : $T['dancer'] ?></p>
</div>

<section>
    <div class="title"><?= $T['p_details']; ?></div>
    <div class="row" onclick="window.location='profile.php'">
        <div class="icon-wrapper">
            <img src="../assets/settings/profile.png" alt="icon">
        </div>
        <span><?= $T['profile']; ?></span>
    </div>
    <div class="row" onclick="window.location='contact.php'">
        <div class="icon-wrapper">
            <img src="../assets/settings/contact.png" alt="icon">
        </div>
        <span><?= $T['contact']; ?></span>
    </div>
</section>
<section>
    <div class="title"><?= $T['app']; ?></div>
    <div class="row" onclick="window.location='language.php'">
        <div class="icon-wrapper">
            <img src="../assets/settings/language-icon.png" alt="icon">
        </div>
        <span><?= $T['language']; ?></span>
    </div>
<?php if ($isCreator === 1): ?>

    <div class="row" onclick="window.location='message.php'">
        <div class="icon-wrapper">
            <img src="../assets/settings/language.png" alt="icon">
        </div>
        <span><?= $T['auto_messages'] ?></span>
    </div>
    <div class="row" onclick="window.location='payments.php'">
        <div class="icon-wrapper">
            <img src="../assets/settings/pay.png" alt="icon">
        </div>
        <span><?= $T['qr_codes'] ?></span>
    </div>
<?php endif; ?>
</section>

<?php if ($isCreator === 0): ?>
<section>
    <div class="title"><?= $T['c_program']; ?></div>
    <div class="row" onclick="window.location='creator.php'">
        <div class="icon-wrapper">
            <img src="../assets/settings/creator.png" alt="icon">
        </div>
        <span><?= $T['request']; ?></span>
    </div>
</section>
<?php endif; ?>


<button class="logout" onclick="window.location='../registration/register-server-logic/logout.php'"><?= $T['logout']; ?></button>

<div class="links-settings">
    <a href="https://www.dancefy.cz/messages/chat.php?public_id=GBbzfrWmWD"><?= $T['contact_support'] ?></a>
</div>
<div class="termscons">
    <a href="https://www.dancefy.cz/onboard/terms-conditions.html"><?= $T['terms'] ?></a>
</div>
<div class="termscons">
    <a style="text-decoration: none;"><?= $T['app_version'] ?>: <?= APP_VERSION ?></a>
</div>


</body>
</html>