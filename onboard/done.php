<?php
declare(strict_types=1);

header("Content-Type: text/html; charset=utf-8");

require __DIR__ . "/../secure/logic.php";

/* ───────── AUTH ───────── */

if (empty($_COOKIE['dancefy_token'])) {
    header("Location: /login.php");
    exit;
}

$rawToken  = $_COOKIE['dancefy_token'];
$tokenHash = hash('sha256', $rawToken);

$db = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$db->set_charset("utf8mb4");

/* resolve user + profile (CORRECT JOIN) */
$stmt = $db->prepare("
    SELECT 
        u.public_id,
        u.username,
        p.pfp_path
    FROM user_tokens t
    JOIN users u ON u.user_id = t.user_id
    LEFT JOIN user_profile p ON p.public_id = u.public_id
    WHERE t.token_hash = ?
    LIMIT 1
");
$stmt->bind_param("s", $tokenHash);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    setcookie('dancefy_token', '', time() - 3600, '/');
    header("Location: /login.php");
    exit;
}

$user = $res->fetch_assoc();

$username = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
$pfp = $user['pfp_path'] ?: 'default.png';
$pfp = htmlspecialchars($pfp, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vše je hotovo</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="onboard.css">
</head>

<body>

<div class="onboarding">

  <!-- TOP -->
  <div class="onboarding-top">
    <h1 class="onboarding-title">Vše je hotovo!</h1>
    <p class="onboarding-subtitle">
      Tímto uživatelským jménem se přihlásíte
    </p>
  </div>

  <!-- MIDDLE -->
  <div class="onboarding-middle">
    <div class="onboarding-center">

      <div class="onboarding-avatar">
        <img src="<?php echo $pfp; ?>" alt="Profilová fotka">
      </div>

      <div style="font-weight:600;font-size:16px;">
        <?php echo $username; ?>
      </div>

      <div class="onboarding-subtitle" style="margin-top:4px;">
        Uživatelské jméno
      </div>

    </div>
  </div>

  <!-- BOTTOM -->
  <div class="onboarding-bottom">
    <a
      href="download.php"
      class="onboarding-primary"
      style="display:block;text-align:center;text-decoration:none;"
    >
      Stáhnou Dancefy
    </a>
  </div>

</div>

</body>
</html>
