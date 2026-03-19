<?php 
require __DIR__ . '/../languages/loader.php';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nastavení Profilu</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
</head>
<body>

<nav class="nav">
    <a href="../settings.php" class="nav-btn nav-left" aria-label="Back">
        <svg viewBox="0 0 24 24">
            <path d="M15 18l-6-6 6-6" />
        </svg>
    </a>
</nav>

<section>
    <div class="title"><?= $T['active']; ?></title>
    <div class="row" style="opacity: 100% !important;">
        <div class="tema dancefy" style="opacity: 100% !important;"></div>
        <span style="opacity: 100% !important;">Dancefy</span>
    </div>
</section>
<section>
    <div class="title"><?= $T['other']; ?></title>
    <div class="row">
        <div class="tema light"></div>
        <span>Světlý (Brzy)</span>
    </div>
</section>

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
