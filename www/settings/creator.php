<?php
if (!isset($_SESSION)) session_start();

if (empty($_SESSION['public_id']) || empty($_COOKIE['dancefy_token'])) {
    http_response_code(401);
    exit('AUTH_FAILED');
}

include __DIR__ . '/../secure/logic.php'; // gives you $conn

// ---------------- CREATOR PROGRAM FUNCTIONS ----------------

function cp_getRequest($conn, $publicId) {
    $stmt = $conn->prepare("SELECT * FROM creator_requests WHERE public_id = ? LIMIT 1");
    $stmt->bind_param("s", $publicId);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc() ?: null;
}

function cp_createOrUpdate($conn, $publicId) {
    $r = cp_getRequest($conn, $publicId);

    if ($r) {
        if ((int)$r['attempts'] >= 10) return "max_attempts";

        $attempts = (int)$r['attempts'] + 1;
        $stmt = $conn->prepare("UPDATE creator_requests SET status='pending', attempts=? WHERE public_id=?");
        $stmt->bind_param("is", $attempts, $publicId);
        $stmt->execute();
        return "updated";
    }

    $stmt = $conn->prepare("INSERT INTO creator_requests (public_id, status, attempts) VALUES (?, 'pending', 1)");
    $stmt->bind_param("s", $publicId);
    $stmt->execute();
    return "created";
}

// ------------------------------------------------------------------

$publicId = $_SESSION['public_id'] ?? null;
if (!$publicId) die("Missing public_id in session.");

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = cp_createOrUpdate($conn, $publicId);
    header("Location: creator.php");
    exit;
}

// Load status
$r = cp_getRequest($conn, $publicId);
$status = $r['status'] ?? 'none';
$attempts = $r['attempts'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="creator-program.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <title>Creator Program</title>
</head>
<body>

<img src="/source/assets/back.png" class="nav-back" onclick="window.location='../settings.php'">
<br><br>
<div class="nav">
    <h1>DANCEFY</h1>
    <span>CREATOR PROGRAM</span>
</div>

<div class="pfp" style="visibility: hidden; height: 30px;">
    <img src="logo.png">
</div>

<?php if ($status === 'none'): ?>

    <div class="message-box">
        <span>Buď vidět tam, kde tě ještě neznají.</span>
        <p>Požádej si o účet tvůrce, aby jsi mohl sdílet obsah a open classes s komunitou!</p>
        <p>Ujisti se, že máš vyplněný profil</p>
        <a href="https://www.dancefy.cz/onboard/terms-conditions.html">Podmínky Programu</a>
    </div>
    <br><br><br>

    <form method="POST" class="submit-button">
        <div class="top">BONUSY PRO PRVNÍ TVŮRCE</div>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <button class="button"><p>Požádat o účet Tvůrce</p></button>
    </form>

    <p class="lol">Staň se jedním z prvních ověřených tvůrců Dancefy.</p>

<?php elseif ($status === 'pending'): ?>

    <div class="message-box">
        <span>Požádal jsi!</span>
        <p>Tvoji žádost teď zkontrolujeme. Většinou odpovídáme do pár minut.<br> (Odhad 45 Minut)</p>
        <a href="https://www.dancefy.cz/onboard/terms-conditions.html">Podmínky Programu</a>
    </div>

    <div class="submit-button disabled">
        <div class="top">BONUSY PRO PRVNÍ TVŮRCE</div>
        <div class="button disabled"><p>Požádáno</p></div>
    </div>

    <p class="lol">Staň se jedním z prvních ověřených tvůrců Dancefy.</p>

<?php elseif ($status === 'rejected'): ?>

    <div class="message-box">
        <span>Žádost zamítnuta</span>
        <p>Bohužel, tvoje žádost byla zamítnuta. <br> Poslali jsme ti zpětnou vazbu!</p>
        <a href="../messages/chat.php?public_id=GBbzfrWmWD">Zobrazit</a>
    </div>

    <?php if ($attempts < 3): ?>
        <form method="POST" class="submit-button">
            <div class="top">BONUSY PRO PRVNÍ TVŮRCE</div>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button class="button"><p>Požádat znovu</p></button>
        </form>
    <?php else: ?>
        <div class="submit-button disabled">
            <div class="top">PŘÍLIŠ MNOHO ŽÁDOSTÍ</div>
            <div class="button disabled"><p>Kontaktuj podporu</p></div>
        </div>
    <?php endif; ?>

    <p class="lol">Staň se jedním z prvních ověřených tvůrců Dancefy.</p>

<?php elseif ($status === 'approved'): ?>

    <div class="message-box">
        <span>Žádost schválena</span>
        <p>Tvůj účet byl schválen! Připravujeme tvůj creator profil – potrvá to jen pár hodin.</p>
        <a href="https://www.dancefy.cz/onboard/terms-conditions.html">Podmínky Programu</a>
    </div>

    <div class="submit-button disabled">
        <div class="top">ZDARMA PRO PRVNÍCH 10 TVŮRCŮ</div>
        <div class="button disabled"><p>Úspěch</p></div>
    </div>

    <p class="lol">Staň se jedním z prvních ověřených tvůrců Dancefy.</p>

<?php endif; ?>

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
