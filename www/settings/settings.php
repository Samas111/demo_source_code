<?php
if (!isset($_SESSION)) session_start();
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header("Location: /register-indexes/login.html");
    exit;
}

require __DIR__ . '/secure/logic.php';

$userId = (int)$_SESSION['user_id'];

/* =========================
   LOAD PUBLIC ID
========================= */
$pub = $conn->prepare("SELECT public_id FROM users WHERE user_id = ?");
$pub->bind_param("i", $userId);
$pub->execute();
$res = $pub->get_result()->fetch_assoc();
$pub->close();

if (!$res) {
    exit('User not found');
}

$publicId = $res['public_id'];

/* =========================
   LOAD PROFILE DATA
========================= */
$q = $conn->prepare("
    SELECT
        u.username,
        u.email,
        u.telephone,
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
    <a href="app.php" class="nav-btn nav-left" aria-label="Back">
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
    <p>Taneční Tvůrce</p>
</div>

<section>
    <div class="title">Základní Údaje</div>
    <div class="row" onclick="window.location='settings/profile.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/profile.png" alt="icon">
        </div>
        <span>Profil</span>
    </div>
    <div class="row" onclick="window.location='settings/contact.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/contact.png" alt="icon">
        </div>
        <span>Kontaktní údaje</span>
    </div>
</section>

<section>
    <div class="title">Aplikace</div>
    <div class="row" onclick="window.location='settings/language.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/language.png" alt="icon">
        </div>
        <span>Jazyk</span>
    </div>
    <div class="row" onclick="window.location='settings/theme.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/theme.png" alt="icon">
        </div>
        <span>Vzhled</span>
    </div>
</section>

<section>
    <div class="title">Program Tvůrce</div>
    <div class="row" onclick="window.location='settings/creator.php'">
        <div class="icon-wrapper">
            <img src="assets/settings/creator.png" alt="icon">
        </div>
        <span>Žádost</span>
    </div>
</section>

<button class="logout" onclick="window.location='registration/register-server-logic/logout.php'">Odhlásit se</button>

<script>
    
    window.CSRF_TOKEN = "<?= $_SESSION['csrf_token'] ?>";

    const originalFetch = window.fetch;

    window.fetch = function (url, options = {}) {

        options.headers = options.headers || {};

        if (!options.headers['X-CSRF-Token']) {
            options.headers['X-CSRF-Token'] = window.CSRF_TOKEN;
        }

        return originalFetch(url, options);
    };
</script>

</body>
</html>
