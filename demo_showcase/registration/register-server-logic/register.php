<?php
session_start();
require __DIR__ . '/../../secure/logic.php';

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: same-origin");

function errorRedirect($msg) {
    header("Location: ../register-indexes/register.html?e=" . urlencode($msg));
    exit;
}

if (!empty($_POST['website'])) {
    exit;
}

$username = trim($_POST['username'] ?? '');

$username = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}]/u', '', $username);

$username = mb_strtolower($username, 'UTF-8'); 

$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

if (!preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
    errorRedirect("Uživatelské jméno musí obsahovat pouze malá písmena, čísla a podtržítka.");
}

if ($password !== $password_confirm) {
    errorRedirect("Hesla se neshodují.");
}

if (
    strlen($password) < 8 ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[^a-zA-Z0-9]/', $password)
) {
    errorRedirect("Slabé heslo.");
}

$ip = $_SERVER['REMOTE_ADDR'];

$stmt = $conn->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip=?");
$stmt->bind_param("s", $ip);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($attempts, $last_attempt);
$stmt->fetch();

if ($stmt->num_rows > 0) {
    $cooldown = strtotime($last_attempt) + (60 * min($attempts, 10));
    if (time() < $cooldown) {
        errorRedirect("Zkus to později");
    }
}
$stmt->close();

$stmt = $conn->prepare("SELECT user_id FROM users WHERE username=? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO login_attempts (ip, attempts, last_attempt)
        VALUES (?, 1, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();

    errorRedirect("Uživatelské jméno existuje");
}
$stmt->close();

$hashed = password_hash($password, PASSWORD_BCRYPT);

function generatePublicID(mysqli $conn) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $len = 10;

    while (true) {
        $id = '';
        for ($i = 0; $i < $len; $i++) {
            $id .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $stmt = $conn->prepare("SELECT user_id FROM users WHERE public_id=? LIMIT 1");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $stmt->close();
            return $id;
        }
        $stmt->close();
    }
}

$public_id = generatePublicID($conn);

$stmt = $conn->prepare("
    INSERT INTO users (public_id, username, password, is_verified, is_creator)
    VALUES (?, ?, ?, 0, 0)
");
$stmt->bind_param("sss", $public_id, $username, $hashed);

if (!$stmt->execute()) {
    errorRedirect("Server error");
}

$user_id = $stmt->insert_id;
$stmt->close();

$stmt = $conn->prepare("
    INSERT INTO user_profile (user_id, public_id, bio, pfp_path, location, dance_group)
    VALUES (?, ?, '', 'uploads/profile-pictures/default.png', '', '')
");
$stmt->bind_param("is", $user_id, $public_id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip=?");
$stmt->bind_param("s", $ip);
$stmt->execute();
$stmt->close();

session_regenerate_id(true);
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = $user_id;
$_SESSION['public_id'] = $public_id;
$_SESSION['username'] = $username;

$rawToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);

$stmt = $conn->prepare("
    INSERT INTO user_tokens (user_id, token_hash)
    VALUES (?, ?)
");
$stmt->bind_param("is", $user_id, $tokenHash);
$stmt->execute();
$stmt->close();

setcookie(
    "dancefy_token",
    $rawToken,
    time() + 60 * 60 * 24 * 30,
    "/",
    "",
    true,
    true
);

$conn->close();
header("Location: done.php");
exit;
