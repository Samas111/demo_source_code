<?php
error_reporting(E_ALL);

require_once __DIR__ . '/secure/logic.php';

$conn = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("DB error");
}

$message = null;
$messageType = null;

$token = $_GET['token'] ?? $_POST['token'] ?? null;
$row = null;

if (!$token) {
    $message = "Neplatný nebo chybějící reset odkaz.";
    $messageType = "error";
} else {
    $tokenHash = hash('sha256', $token);

    $stmt = $conn->prepare("
        SELECT user_id, created_at 
        FROM password_resets 
        WHERE token_hash = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        $message = "Reset odkaz je neplatný nebo již použitý.";
        $messageType = "error";
    } else {
        $createdAt = strtotime($row['created_at']);
        if ($createdAt < time() - 1800) {

            $del = $conn->prepare("DELETE FROM password_resets WHERE token_hash = ?");
            $del->bind_param("s", $tokenHash);
            $del->execute();

            $message = "Reset odkaz vypršel.";
            $messageType = "error";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$message && $row) {

    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 8) {
        $message = "Heslo musí mít alespoň 8 znaků.";
        $messageType = "error";
    } elseif ($password !== $password2) {
        $message = "Hesla se neshodují.";
        $messageType = "error";
    } else {

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $upd->bind_param("si", $hash, $row['user_id']);
        $upd->execute();

        $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $del->bind_param("i", $row['user_id']);
        $del->execute();

        $message = "Heslo bylo úspěšně změněno.";
        $messageType = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DANCEFY – Reset Hesla</title>
<link rel="stylesheet" href="/registration/register-styles/register.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php if ($message): ?>
<div id="message-box" class="message <?= $messageType ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="wrapper">

<div class="logo-block">
    <img src="logo.png" class="logo" alt="logo">
    <h1 class="title">DANCEFY</h1>
    <p class="subtitle">Nové heslo</p>
</div>

<?php if (!$message || $messageType !== 'success'): ?>
<form method="POST" novalidate>

    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

    <label>Nové heslo</label>
    <input type="password" name="password" required minlength="8">

    <label>Zopakuj heslo</label>
    <input type="password" name="password2" required minlength="8">

    <button type="submit" class="btn-primary">Uložit heslo</button>

</form>
<?php endif; ?>

</div>

</body>
</html>
