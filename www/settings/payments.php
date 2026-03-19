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

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $iban = strtoupper(str_replace(' ', '', trim($_POST['iban'] ?? '')));
    $paymentCheck = isset($_POST['payment_check']) ? 1 : 0;

    if (!$iban) {
        $errorMessage = "IBAN nebyl vyplněn.";
    }
    elseif (!preg_match('/^[A-Z0-9]{15,34}$/', $iban)) {
        $errorMessage = "Špatný IBAN formát.";
    }

    if (!$errorMessage) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("
                UPDATE user_profile
                SET iban = ?, payment_check = ?
                WHERE public_id = ?
            ");
            $stmt->bind_param("sis", $iban, $paymentCheck, $publicId);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                $stmt->close();
                $stmt = $conn->prepare("
                    INSERT INTO user_profile (public_id, iban, payment_check)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("ssi", $publicId, $iban, $paymentCheck);
                $stmt->execute();
            }

            $stmt->close();
            $conn->commit();
            $successMessage = "IBAN úspěšně uložen.";

        } catch (Throwable $e) {
            $conn->rollback();
            $errorMessage = "Chyba, kontaktujte podporu přes chat.";
        }
    }
}

// Fetch current data
$stmt = $conn->prepare("
    SELECT iban, payment_check
    FROM user_profile
    WHERE public_id = ?
");
$stmt->bind_param("s", $publicId);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

function safe($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// --- CONFIGURATION LOGIC ---
$dbIban = $r['iban'] ?? '';
$dbPayment = (int)($r['payment_check'] ?? 0);

// If value is "none" or empty, treat as UNSET
if (strtolower($dbIban) === 'none' || empty($dbIban)) {
    $ibanValue = '';         // Blank for the UI
    $isSet = false;          // User needs to set it up
} else {
    $ibanValue = $dbIban;    // Show existing IBAN
    $isSet = true;           // Already exists
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nastavení IBAN</title>
<link rel="stylesheet" href="main.css">
</head>
<body>

<nav class="nav">
    <a href="../settings.php" class="nav-btn nav-left" aria-label="Back">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6" /></svg>
    </a>
</nav>

<?php if ($errorMessage): ?>
<div class="alert error"><?= safe($errorMessage) ?></div>
<?php endif; ?>

<?php if ($successMessage): ?>
<div class="alert success"><?= safe($successMessage) ?></div>
<?php endif; ?>


<form method="POST" id="profileForm">

<section>
    <span class="infos">IBAN slouží pro generování QR kódů pro tanečníky. 100% jde vám na účet!</span><br><br><br>
    <div>IBAN</div>
    <input 
        type="text"
        name="iban"
        value="<?= safe($ibanValue) ?>"
        <?= $isSet ? 'disabled' : '' ?>
        placeholder="<?= $isSet ? '' : 'Vložte váš IBAN' ?>"
        required
    >
    <?php if ($isSet): ?>
        <input type="hidden" name="iban" value="<?= safe($ibanValue) ?>">
    <?php endif; ?>
</section>

<?php if (!$isSet): ?>
<section>
    <div>Souhlasím s podmínkami o platbách</div>
    <div class="flex">
        <a href="legal-payment.php" target="_blank">Podmínky o platbách</a>
        <input type="checkbox" name="payment_check" value="1" required>
    </div>
</section>
<?php endif; ?>

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="save-bar" id="saveBar" style="display: <?= $isSet ? 'none' : 'block'; ?>;">
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
    const profileForm = document.getElementById('profileForm');
    const saveBar = document.getElementById('saveBar');

    if (profileForm) {
        profileForm.addEventListener('input', (e) => {
            saveBar.style.display = 'block';
        });
    }
</script>

</body>
</html>