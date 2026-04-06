<?php
declare(strict_types=1);

header("Content-Type: text/html; charset=utf-8");

require __DIR__ . "/../secure/logic.php";

if (!isset($_GET['id']) || !preg_match('/^[A-Za-z0-9]{6,20}$/', $_GET['id'])) {
    http_response_code(404);
    exit("Invalid link");
}

$publicId = $_GET['id'];

$db = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$db->set_charset("utf8mb4");

$stmt = $db->prepare("
    SELECT 
        u.username,
        p.pfp_path
    FROM users u
    LEFT JOIN user_profile p ON p.public_id = u.public_id
    WHERE u.public_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $publicId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(404);
    exit("User not found");
}

$user = $res->fetch_assoc();

$pfp = $user['pfp_path'] ?: "default.png";
$name = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aktivujte si účet Dancefy</title>
<meta property="og:description" content="Nová aplikace pro taneční komunitu v ČR." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="onboard.css">
</head>

<body>

<div class="onboarding">

  <div class="onboarding-top">
    <h1 class="onboarding-title">Vítejte na Dancefy</h1>
    <p class="onboarding-subtitle">
      Účet jsme pro vás rovnou připravili na míru
    </p>
  </div>

  <div class="onboarding-middle">
    <div class="onboarding-center">

      <div class="onboarding-avatar">
        <img src="<?php echo htmlspecialchars($pfp); ?>" alt="Profilová fotka">
      </div>

      <div style="font-weight:600;font-size:16px;">
        <?php echo $name; ?>
      </div>

      <div class="onboarding-subtitle" style="margin-top:4px;">
        Taneční tvůrce
      </div>

    </div>
  </div>

  <div class="onboarding-bottom">
    <a
      href="security.php?id=<?php echo $publicId; ?>"
      class="onboarding-primary"
      style="text-decoration:none;text-align:center;display:block;"
    >
      Vstoupit do Dancefy
    </a>
  </div>

</div>

</body>
</html>
