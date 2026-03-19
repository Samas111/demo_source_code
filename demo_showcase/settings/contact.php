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

$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;

/* =========================
   ACTION: SEND VERIFICATION
========================= */
if (isset($_POST['send_verification'])) {
    // 1. Generate secure token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // 2. Save to email_verification_tokens table
    $stmt = $conn->prepare("INSERT INTO email_verification_tokens (public_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $publicId, $tokenHash, $expires);
    $stmt->execute();

    // 3. Get user email to send the message
    $userQ = $conn->prepare("SELECT email FROM users WHERE public_id = ?");
    $userQ->bind_param("s", $publicId);
    $userQ->execute();
    $userData = $userQ->get_result()->fetch_assoc();
    
    if ($userData && !empty($userData['email'])) {
        $verifyLink = "https://dancefy.cz/verify.php?id=" . $rawToken;
        $to = $userData['email'];
        
        $subject = "Potvrď svůj účet na Dancefy 🔥";

        $message = '
        <html>
        <head>
        <meta charset="UTF-8">
        </head>
        <body style="margin:0;padding:0;background:#0f0f13;font-family:Inter,Arial,sans-serif;color:#ffffff;">
            <div style="max-width:600px;margin:40px auto;padding:40px;background:#16161d;border-radius:16px;text-align:center;">
                
                <h1 style="margin-bottom:10px;font-size:28px;">Jsi skoro tam 👀</h1>
                
                <p style="color:#aaa;font-size:16px;line-height:1.6;">
                    Aby mohl tvůj profil naplno fungovat, potřebujeme jen potvrdit tvůj email.
                </p>
                
                <div style="margin:30px 0;">
                    <a href="'.$verifyLink.'" 
                    style="background:#ff2e63;
                            color:#fff;
                            padding:14px 28px;
                            text-decoration:none;
                            border-radius:12px;
                            font-weight:600;
                            display:inline-block;">
                        Ověřit email 🔥
                    </a>
                </div>
                
                <p style="color:#666;font-size:13px;">
                    Odkaz je platný 24 hodin.<br>
                    Pokud jsi to nebyl ty, můžeš tento email ignorovat.
                </p>

                <div style="margin-top:40px;color:#444;font-size:12px;">
                    © '.date("Y").' Dancefy.cz
                </div>

            </div>
        </body>
        </html>
        ';

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Dancefy <noreply@dancefy.cz>\r\n";
        $headers .= "Reply-To: noreply@dancefy.cz\r\n";

        mail($to, $subject, $message, $headers);
        header("Location: contact.php?success=email_sent");
        exit;
    }
}

/* =========================
   SAVE CONTACT INFO
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['send_verification'])) {

    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');

    $email = $email === '' ? null : $email;
    $telephone = $telephone === '' ? null : preg_replace('/\D+/', '', $telephone);

    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: contact.php?error=invalid_email");
        exit;
    }

    if ($telephone !== null && strlen($telephone) < 6) {
        header("Location: contact.php?error=invalid_phone");
        exit;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {

        if ($email !== null) {
            $checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND public_id != ?");
            $checkEmail->bind_param("ss", $email, $publicId);
            $checkEmail->execute();
            if ($checkEmail->get_result()->num_rows > 0) {
                header("Location: contact.php?error=email_exists");
                exit;
            }
        }

        if ($telephone !== null) {
            $checkPhone = $conn->prepare("SELECT user_id FROM users WHERE telephone = ? AND public_id != ?");
            $checkPhone->bind_param("ss", $telephone, $publicId);
            $checkPhone->execute();
            if ($checkPhone->get_result()->num_rows > 0) {
                header("Location: contact.php?error=phone_exists");
                exit;
            }
        }

        $conn->begin_transaction();

        $u = $conn->prepare("UPDATE users SET email = ?, telephone = ? WHERE public_id = ?");
        $u->bind_param("sss", $email, $telephone, $publicId);
        $u->execute();

        $conn->commit();

        header("Location: contact.php?success=saved");
        exit;

    } catch (Throwable $e) {

        $conn->rollback();
        error_log("Contact update error for {$publicId}: " . $e->getMessage());
        header("Location: contact.php?error=save_failed");
        exit;
    }
}

/* =========================
   LOAD CURRENT DATA
========================= */
// UPDATED: Using 'is_verified' instead of 'email_verified'
$q = $conn->prepare("SELECT email, telephone, is_verified FROM users WHERE public_id = ?");
$q->bind_param("s", $publicId);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();

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
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap">
</head>
<body>

<nav class="nav">
    <a href="../settings.php" class="nav-btn nav-left">
        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" /></svg>
    </a>
</nav>

<?php if ($error === 'email_exists'): ?>
    <div class="alert error">Tento email už existuje.</div>
<?php endif; ?>

<?php if ($error === 'phone_exists'): ?>
    <div class="alert error">Toto telefonní číslo už existuje.</div>
<?php endif; ?>

<?php if ($error === 'invalid_email'): ?>
    <div class="alert error">Neplatný email.</div>
<?php endif; ?>

<?php if ($error === 'invalid_phone'): ?>
    <div class="alert error">Neplatné telefonní číslo.</div>
<?php endif; ?>

<?php if ($error === 'save_failed'): ?>
    <div class="alert error">Něco se pokazilo. Zkuste to znovu.</div>
<?php endif; ?>


<?php if ($success === 'email_sent'): ?>
    <div class="success-msg">Email s odkazem byl odeslán na vaši adresu!</div>
<?php endif; ?>

<?php if (!empty($r['email']) && ($r['is_verified'] == 0)): ?>
<div class="verify-banner">
    <div style="font-size: 1.1rem;"><strong>Ověřte svůj email</strong></div>
    <form method="POST">
        <button type="submit" name="send_verification" class="verify-btn">Ověřit</button>
    </form>
</div>
<?php endif; ?>

<form method="POST" id="contactForm">
    <section>
        <div class="title"><?= $T['email']; ?></div>
        <input type="email" name="email" value="<?php echo safe($r['email']); ?>">
    </section>

    <section>
        <div class="title"><?= $T['tel']; ?></div>
        <input type="tel" name="telephone" value="<?php echo safe($r['telephone']); ?>">
    </section>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="save-bar" id="saveBar" style="display:none;">
        <button type="submit" class="save-btn"><?= $T['save']; ?></button>
    </div>
</form>

<script>
    const form = document.getElementById('contactForm');
    const saveBar = document.getElementById('saveBar');
    const inputs = form.querySelectorAll('input');

    inputs.forEach(el => {
        el.addEventListener('input', () => {
            saveBar.style.display = 'block';
        });
    });
</script>

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