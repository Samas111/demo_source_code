<?php
declare(strict_types=1);

header("Content-Type: text/html; charset=utf-8");

require __DIR__ . "/../secure/logic.php";

if (!isset($_GET['id']) || !preg_match('/^[A-Za-z0-9]{6,20}$/', $_GET['id'])) {
    http_response_code(404);
    exit("Invalid link");
}

$alreadyActivated = false;

$publicId = $_GET['id'];

$db = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$db->set_charset("utf8mb4");

$stmt = $db->prepare("
    SELECT user_id, password 
    FROM users 
    WHERE public_id = ? 
    LIMIT 1
");
$stmt->bind_param("s", $publicId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(404);
    exit("Neplatný odkaz.");
}

$user = $res->fetch_assoc();

if (!empty($user['password'])) {
    $alreadyActivated = true;
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if (strlen($password) < 8) {
        $error = "Heslo musí mít alespoň 8 znaků";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $update = $db->prepare("
            UPDATE users 
            SET password = ? 
            WHERE user_id = ? 
              AND (password IS NULL OR password = '')
            LIMIT 1
        ");
        $update->bind_param("si", $hash, $user['user_id']);
        $update->execute();

        if ($update->affected_rows === 1) {

            // generate login token
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);

            // store token
            $insert = $db->prepare("
                INSERT INTO user_tokens (user_id, token_hash)
                VALUES (?, ?)
            ");
            $insert->bind_param("is", $user['user_id'], $tokenHash);
            $insert->execute();

            // set cookie (AUTHENTICATED SESSION)
            setcookie(
                'dancefy_token',
                $rawToken,
                [
                    'expires'  => time() + 60 * 60 * 24 * 30,
                    'path'     => '/',
                    'domain'   => APP_PRODUCTION === 1 ? '.dancefy.cz' : '',
                    'secure'   => APP_PRODUCTION === 1,
                    'httponly' => true,
                    'samesite' => APP_PRODUCTION === 1 ? 'None' : 'Lax'
                ]
            );

            header("Location: done.php");
            exit;


        } else {
            $alreadyActivated = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Zabezpečení účtu</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="onboard.css">

<style>

.onboarding-middle {
  margin-top: 24px;
  max-width: 420px;
  width: 100%;
}

.onboarding-info-box {
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  padding: 16px 18px;
  font-size: 14px;
  line-height: 1.6;
  color: rgba(255,255,255,0.85);
  backdrop-filter: blur(8px);
}

</style>

</head>

<body>


<div class="onboarding">
    <?php if ($alreadyActivated): ?>
    <div class="onboarding-top">
    <h1 class="onboarding-title">Účet je už aktivní</h1>
    <p class="onboarding-subtitle">
        Heslo už bylo nastaveno a účet je připravený k použití.
    </p>
    </div>

    <div class="onboarding-middle">
    <div class="onboarding-info-box">
        Pokud jsi onboarding nedokončil/a, můžeš se k němu vrátit po přihlášení.
    </div>
    </div>

    <div class="onboarding-bottom">
    <button onclick="window.location='download.php'" class="onboarding-primary">
        Stáhnout Dancefy
    </a>
    </div>

    <?php else: ?>

  <div class="onboarding-top">
    <h1 class="onboarding-title">Zabezpečení účtu</h1>
    <p class="onboarding-subtitle">Nastavte si heslo a jdeme dál</p>
  </div>

  <div class="onboarding-middle">
    <form method="POST" autocomplete="off" id="passwordForm">

        <div class="onboarding-field">
            <label class="onboarding-label">Heslo</label>
            <input
            type="password"
            name="password"
            class="onboarding-input"
            required
            minlength="8"
            >
        </div>

        <div class="onboarding-checkboxes">

            <label class="onboarding-checkbox">
            <input type="checkbox" name="terms" required>
            <span>
                Přečetl/a jsem si
                <a href="terms-conditions.html" target="_blank">Podmínky používání</a>
                a
                <a href="terms-conditions.html" target="_blank">Zásady ochrany osobních údajů</a>
                a souhlasím s nimi.
            </span>
            </label>

            <label class="onboarding-checkbox">
            <input type="checkbox" name="instagram_ack" required>
            <span>
                Beru na vědomí, že Dancefy může použít veřejně dostupné údaje z mého
                veřejného profilu na Instagramu (uživatelské jméno, profilovou fotografii
                a bio) za účelem vytvoření profilu a zlepšení funkčnosti služby.
                Tyto údaje mohu kdykoliv upravit nebo odstranit.
            </span>
            </label>

        </div>

        <?php if ($error): ?>
            <div class="onboarding-subtitle" style="margin-top:12px;color:#ff6a6a;">
            <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        </form>

  </div>

  <div class="onboarding-bottom">
    <button
      type="submit"
      form="passwordForm"
      class="onboarding-primary"
    >
      Pokračovat
    </button>
  </div>

</div>
<?php endif; ?>

</body>
</html>
