<?php
require __DIR__ . '/secure/logic.php';

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header("Location: " . APP_URL);
    exit;
}
require_once __DIR__ . '/languages/loader.php';

$userId = (int)$_SESSION['user_id'];

$pub = $conn->prepare("SELECT public_id FROM users WHERE user_id = ?");
$pub->bind_param("i", $userId);
$pub->execute();
$res = $pub->get_result()->fetch_assoc();
$pub->close();

if (!$res) {
    exit('User not found');
}

$publicId = $res['public_id'];

$q = $conn->prepare("
    SELECT 
        u.username, 
        u.email, 
        u.telephone, 
        u.is_creator, 
        p.bio, 
        p.location, 
        p.dance_group, 
        p.pfp_path
    FROM users u
    LEFT JOIN user_profile p ON p.public_id = u.public_id
    WHERE u.public_id = ?
");
$q->bind_param("s", $publicId);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();

if (!$r) {
    exit('Profile load failed');
}

function safe($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$isCreator = (int)($r['is_creator'] ?? 0);

if ($isCreator === 1) {
    $back_path = 'app.php';
} else {
    $back_path = 'app-dancer.php';
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nastavení Profilu</title>
    <link rel="stylesheet" href="settings.css">
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
        <img src="<?php echo safe($r['pfp_path']); ?>" class="pfp" loading="lazy">
    </div>
    <span><?php echo safe($r['username']); ?></span>
    <p><?= ($isCreator === 1) ? $T['d_creator'] : 'Tanečník' ?></p>
</div>

<section>
    <div class="title"><?= $T['p_details']; ?></div>
    <div class="row" onclick="window.location='settings/profile.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/profile.png" alt="icon">
        </div>
        <span><?= $T['profile']; ?></span>
    </div>
    <div class="row" onclick="window.location='settings/contact.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/contact.png" alt="icon">
        </div>
        <span><?= $T['contact']; ?></span>
    </div>
</section>
<?php if ($isCreator === 1): ?>
<section>
    <div class="title"><?= $T['app']; ?></div>
    <div class="row" onclick="window.location='settings/language.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/language-icon.png" alt="icon">
        </div>
        <span><?= $T['language']; ?></span>
    </div>

    <div class="row" onclick="window.location='settings/message.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/language.png" alt="icon">
        </div>
        <span>Automatické zprávy</span>
    </div>
    <div class="row" onclick="window.location='settings/payments.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/pay.png" alt="icon">
        </div>
        <span>QR Kódy</span>
    </div>
</section>
<?php endif; ?>

<?php if ($isCreator === 0): ?>
<section>
    <div class="title"><?= $T['c_program']; ?></div>
    <div class="row" onclick="window.location='settings/creator.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/creator.png" alt="icon">
        </div>
        <span><?= $T['request']; ?></span>
    </div>
</section>
<?php endif; ?>


<button class="logout" onclick="window.location='registration/register-server-logic/logout.php'"><?= $T['logout']; ?></button>

<div class="links-settings">
    <a href="https://www.dancefy.cz/messages/chat.php?public_id=GBbzfrWmWD">Kontaktovat podporu</a> | <a href="https://www.dancefy.cz/view-profile.php?public_id=VxJNAQXBZx"> Tvůrce Dancefy</a>
</div>
<div class="termscons">
    <a href="https://www.dancefy.cz/onboard/terms-conditions.html">Podmínky používání a Ochrana osobních údajů</a>
</div>

<?php if ($publicId === "GBbzfrWmWD"): ?>
<div class="termscons">
    <a href="https://www.dancefy.cz/admin/dashboard.php">Dancefy Dashboard</a>
</div>
<?php endif; ?>

</body>
</html>