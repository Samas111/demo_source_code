<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

if (empty($_SESSION['public_id']) || empty($_COOKIE['dancefy_token'])) {
    http_response_code(401);
    exit('AUTH_FAILED');
}

$publicId = $_SESSION['public_id'];

require __DIR__ . '/../secure/logic.php';
require __DIR__ . '/../languages/loader.php';

/* =========================
   SAVE PROFILE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name         = trim($_POST['name'] ?? '');
    $auto_message = trim($_POST['auto_message'] ?? ''); 

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();

    try {
        /* UPDATE user_profile table (assuming other fields stay as they are) */
        $p = $conn->prepare("
            INSERT INTO user_profile (public_id, name, auto_message)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                auto_message = VALUES(auto_message)
        ");
        $p->bind_param("sss", $publicId, $name, $auto_message);
        $p->execute();
        $p->close();

        $conn->commit();
        header("Location: message.php?success=saved");
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        exit("Error: " . $e->getMessage());
    }
}

/* =========================
   LOAD PROFILE DATA
========================= */
$q = $conn->prepare("
    SELECT p.name, p.auto_message
    FROM user_profile p 
    WHERE p.public_id = ?
");
$q->bind_param("s", $publicId);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();

function safe($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Nastavení Profilu</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap">
</head>
<body>

<nav class="nav">
    <a href="../settings.php" class="nav-btn nav-left" aria-label="Back">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6" /></svg>
    </a>
</nav>

<form method="POST" enctype="multipart/form-data" id="profileForm">

<section>
    <div class="title">Jméno ve zprávách</div>
    <input type="text" name="name" value="<?php echo safe($r['name'] ?? ''); ?>">
</section>

<section>
    <div class="title">Automatická zpráva při registraci pro tanečníka</div>
    <textarea name="auto_message" maxlength="150" class="form-control"><?php echo safe($r['auto_message'] ?? ''); ?></textarea>
</section>

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="save-bar" id="saveBar" style="display: none;">
    <button type="submit" class="save-btn"><?= $T['save'] ?? 'Uložit'; ?></button>
</div>

</form>
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
<script>
    // Ensure this ID matches the <form id="...">
    const profileForm = document.getElementById('profileForm');
    const saveBar = document.getElementById('saveBar');

    if (profileForm) {
        // 'input' event covers text inputs, textareas, and even checkboxes/radios
        profileForm.addEventListener('input', (e) => {
            saveBar.style.display = 'block';
        });
    }
</script>

</body>
</html>