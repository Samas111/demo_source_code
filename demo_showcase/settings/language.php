<?php 
require __DIR__ . '/../languages/loader.php';
$publicId = $_SESSION['public_id'] ?? null;

if ($publicId && isset($conn) && isset($_GET['set_lang'])) {
    $newLang = (int)$_GET['set_lang'];
    if (in_array($newLang, [1, 2])) {
        $update = $conn->prepare("UPDATE users SET language = ? WHERE public_id = ?");
        $update->bind_param("is", $newLang, $publicId);
        $update->execute();
        $update->close();
        
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

$lang = 1; 
if ($publicId && isset($conn)) {
    $stmt = $conn->prepare("SELECT language FROM users WHERE public_id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $publicId);
        $stmt->execute();
        $stmt->bind_result($dbLang);
        $stmt->fetch();
        $lang = (int) ($dbLang ?? 1);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= ($lang === 2) ? 'en' : 'cs'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Nastavení</title>
    <link rel="stylesheet" href="main.css">
    <style>
        .row {
            cursor: pointer;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            transition: background-color 0.2s ease;
        }

        .row:active {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .active-lang {
            pointer-events: none;
        }
    </style>
</head>
<body>

<nav class="nav">
    <a href="../settings.php" class="nav-btn nav-left">
        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" fill="none">
            <path d="M15 18l-6-6 6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </a>
</nav>

<section>
    <div class="title"><?= $T['active']; ?></div>
    <?php if ($lang === 1): ?>
        <div class="row active-lang" style="opacity: 1;">
            <div class="language-wrapper"><img src="flags/czech.png" alt=""></div>
            <span>Česky</span>
        </div>
    <?php else: ?>
        <div class="row active-lang" style="opacity: 1;">
            <div class="language-wrapper"><img src="flags/uk.png" alt=""></div>
            <span>English</span>
        </div>
    <?php endif; ?>
</section>

<section>
    <div class="title"><?= $T['other']; ?></div>
    
    <?php if ($lang !== 1): ?>
        <div class="row" onclick="this.style.opacity='0.5'; window.location.href='?set_lang=1'">
            <div class="language-wrapper"><img src="flags/czech.png" alt=""></div>
            <span>Česky</span>
        </div>
    <?php endif; ?>

    <?php if ($lang !== 2): ?>
        <div class="row" onclick="this.style.opacity='0.5'; window.location.href='?set_lang=2'">
            <div class="language-wrapper"><img src="flags/uk.png" alt=""></div>
            <span>English</span>
        </div>
    <?php endif; ?>
</section>

</body>
</html>