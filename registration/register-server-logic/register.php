<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../private/config.php';

$conn = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    header("Location: ../register-indexes/register.php?e=Server+error");
    exit;
}
$conn->set_charset('utf8mb4');

/* ---------------------------
   Security headers
----------------------------*/
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: same-origin");

function errorRedirect($msg) {
    header("Location: ../register-indexes/register.php?e=" . urlencode($msg));
    exit;
}

/* ---------------------------
   Honeypot
----------------------------*/
if (!empty($_POST['website'])) {
    exit;
}

/* ---------------------------
   Input
----------------------------*/
$username = trim($_POST['username'] ?? '');

// 1. Strip invisible characters
$username = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}]/u', '', $username);

// 2. FORCE LOWERCASE HERE (The critical fix)
$username = mb_strtolower($username, 'UTF-8'); 

$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// 3. Update Regex to ONLY allow lowercase (remove A-Z)
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

/* ---------------------------
   Rate limit by IP
----------------------------*/
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

/* ---------------------------
   Username uniqueness
----------------------------*/
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

/* ---------------------------
   Password hash
----------------------------*/
$hashed = password_hash($password, PASSWORD_BCRYPT);

/* ---------------------------
   Public ID generator
----------------------------*/
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

/* ---------------------------
   Create user (default = dancer)
----------------------------*/
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

/* ---------------------------
   Create profile
----------------------------*/
$stmt = $conn->prepare("
    INSERT INTO user_profile (user_id, public_id, bio, pfp_path, location, dance_group)
    VALUES (?, ?, '', 'uploads/profile-pictures/default.png', '', '')
");
$stmt->bind_param("is", $user_id, $public_id);
$stmt->execute();
$stmt->close();

/* ---------------------------
   Reset rate limit
----------------------------*/
$stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip=?");
$stmt->bind_param("s", $ip);
$stmt->execute();
$stmt->close();

/* ---------------------------
   Session (optional, UI use)
----------------------------*/
session_regenerate_id(true);
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = $user_id;
$_SESSION['public_id'] = $public_id;
$_SESSION['username'] = $username;

/* ---------------------------
   Token (auth_gate compatible)
----------------------------*/
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

/* ---------------------------
   Redirect (role-safe)
----------------------------*/
$conn->close();
header("Location: done.php");
exit;
