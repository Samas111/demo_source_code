<?php
declare(strict_types=1);

header("Content-Type: text/html; charset=utf-8");

require __DIR__ . "/../secure/logic.php";

/* ───────── AUTH ───────── */

if (empty($_COOKIE['dancefy_token'])) {
    header("Location: /login.php");
    exit;
}

$rawToken = $_COOKIE['dancefy_token'];
$tokenHash = hash('sha256', $rawToken);

$db = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$db->set_charset("utf8mb4");

/* resolve user by token */
$stmt = $db->prepare("
    SELECT u.user_id, u.public_id
    FROM user_tokens t
    JOIN users u ON u.user_id = t.user_id
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

/* ───────── POST LOGIC ───────── */

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');

    if ($content === '') {
        $error = "Zpráva nemůže být prázdná";
    } elseif (mb_strlen($content) > 300) {
        $error = "Maximálně 300 znaků";
    } else {
        $safeContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        $insert = $db->prepare("
            INSERT INTO posts (public_id, content)
            VALUES (?, ?)
        ");
        $insert->bind_param("ss", $user['public_id'], $safeContent);
        $insert->execute();

        header("Location: done.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Přivítej své tanečníky</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="onboard.css">
</head>

<body>

<div class="onboarding">

  <div class="onboarding-top">
    <h1 class="onboarding-title">Přivítej své tanečníky</h1>
    <p class="onboarding-subtitle">
      Napiš krátkou zprávu a ukaž že jsi tady
    </p>
  </div>

  <div class="onboarding-middle">
    <form method="POST" id="postForm">

        <textarea
          name="content"
          class="onboarding-textarea"
          maxlength="300"
          placeholder="Přivítej své první sledující…"
          required
        ></textarea>

        <div class="onboarding-subtitle" style="text-align:right;margin-top:6px;">
            <span id="charCount">0</span> / 300
        </div>


        <?php if ($error): ?>
          <div class="onboarding-subtitle" style="margin-top:10px;color:#ff6a6a;">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

    </form>
  </div>

  <div class="onboarding-bottom">
    <button
      type="submit"
      form="postForm"
      class="onboarding-primary"
    >
      Pokračovat
    </button>
  </div>
  <p style="width: 100%; text-align: center; opacity: 40%; font-size: 0.7rem">Příspěvek se hned zobrazí ve feedu.</p>

  <div style="color: white; width: 100%; text-align: center; opacity: 60%; margin-top: 5px; text-decoration: underline; font-size: 0.8rem;" onclick="window.location='done.php'">Přeskočit příspěvek</div>
</div>

<script>
    const textarea = document.querySelector('.onboarding-textarea')
    const counter = document.getElementById('charCount')
    const max = textarea.maxLength

    const updateCount = () => {
    counter.textContent = textarea.value.length
    }

    textarea.addEventListener('input', updateCount)
    updateCount()
</script>

</body>
</html>
