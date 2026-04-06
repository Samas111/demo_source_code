<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php'; // must contain $pdo

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

try {
    $stmt = $conn->prepare("
        INSERT INTO not_found_logs (public_id, path, referrer, user_agent)
        VALUES (?, ?, ?, ?)
    ");

    $publicId = null;

    if (!empty($_COOKIE['dancefy_token'])) {
        $tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

        $userStmt = $conn->prepare("
            SELECT u.public_id
            FROM user_tokens ut
            JOIN users u ON u.user_id = ut.user_id
            WHERE ut.token_hash = ?
            LIMIT 1
        ");

        $userStmt->bind_param('s', $tokenHash);
        $userStmt->execute();
        $userStmt->bind_result($publicId);
        $userStmt->fetch();
        $userStmt->close();
    }

    $path = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $cleanPath = parse_url($path, PHP_URL_PATH);

    /*
    |--------------------------------------------------------------------------
    | ONLY LOG .php REQUESTS
    |--------------------------------------------------------------------------
    */
    $ext = pathinfo($cleanPath, PATHINFO_EXTENSION);

    if ($ext !== 'php') {
        return; // skip everything that is not .php
    }

    if (
        str_contains($path, 'favicon') ||
        str_contains($path, '.well-known') ||
        str_contains($path, 'apple-touch-icon') ||
        str_contains($path, 'browserconfig') ||
        str_contains($path, '.json')
    ) {
        return; // skip logging
    }

    $referrer = $_SERVER['HTTP_REFERER'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt->bind_param("ssss", $publicId, $path, $referrer, $userAgent);
    $stmt->execute();
    $stmt->close();

} catch (Throwable $e) {
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b0b0f">
<title>Hmm… nic tady není · Dancefy</title>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    min-height: 100dvh;
    background: #0b0b0f;
    color: #eaeaf0;
    font-family: -apple-system, BlinkMacSystemFont, 'Inter', system-ui, sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 24px;
}

.card {
    width: 100%;
    max-width: 380px;
    text-align: center;
}

.icon {
    font-size: 42px;
    margin-bottom: 20px;
    opacity: .9;
}

h1 {
    font-size: 22px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 8px;
}

.subtitle {
    font-size: 14px;
    color: rgba(255,255,255,.5);
    margin-bottom: 28px;
    line-height: 1.5;
}

.btn-primary {
    width: 100%;
    padding: 15px;
    background: #FF1B73;
    color: #fff;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 10px;
    display: block;
}

.btn-secondary {
    width: 100%;
    padding: 15px;
    background: transparent;
    color: rgba(255,255,255,.5);
    border-radius: 14px;
    text-decoration: none;
    border: 1px dashed rgba(255,255,255,.08);
    font-size: 14px;
}

.footer {
    margin-top: 24px;
    font-size: 12px;
    color: rgba(255,255,255,.2);
}
</style>
</head>

<body>
<div class="card">

    <div class="icon">👀</div>

    <h1>Ups… tohle tu nemáme</h1>

    <p class="subtitle">
        Tenhle odkaz už tady není,<br>
        vrať se zpátky do Dancefy
    </p>

    <a href="/index.php" class="btn-primary">
        Zpět do Dancefy
    </a>

    <button class="btn-secondary" id="reportBtn" type="button">
        Nahlásit
    </button>

    <p class="footer">Dancefy &copy; <?= date('Y') ?></p>

</div>


<script>
const btn = document.getElementById('reportBtn');

btn.addEventListener('click', (e) => {
    e.preventDefault(); // no form / no navigation

    if (btn.dataset.done === "1") return;

    btn.dataset.done = "1";
    btn.innerText = 'Nahlášeno ✓';
    btn.style.opacity = '0.6';
    btn.style.borderStyle = 'solid';
    btn.disabled = true;

    // micro animation
    btn.style.transform = 'scale(0.96)';
    setTimeout(() => {
        btn.style.transform = 'scale(1)';
    }, 120);
});
</script>

</body>
</html>