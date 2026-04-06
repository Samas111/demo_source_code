<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../private/config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    header("Location: ../register-indexes/login.php");
    exit;
}
$conn->set_charset('utf8mb4');

/* ----------------------------
   TOKEN CHECK
---------------------------- */
if (empty($_COOKIE['dancefy_token']) || !is_string($_COOKIE['dancefy_token'])) {
    header("Location: ../register-indexes/login.php");
    exit;
}

$tokenHash = hash('sha256', $_COOKIE['dancefy_token']);
$loginType = 'automatic';

/* ----------------------------
   TOKEN VERIFY
---------------------------- */
$q = $conn->prepare("
    SELECT u.user_id, u.public_id, u.username, u.is_creator
    FROM user_tokens t
    JOIN users u ON u.user_id = t.user_id
    WHERE t.token_hash = ?
    LIMIT 1
");

if (!$q) {
    die($conn->error); // DEBUG
}

$q->bind_param("s", $tokenHash);
$q->execute();
$q->store_result();

if ($q->num_rows !== 1) {
    setcookie("dancefy_token", "", time() - 3600, "/");
    header("Location: ../register-indexes/login.php");
    exit;
}

$q->bind_result($user_id, $public_id, $username, $is_creator);
$q->fetch();
$q->close();

/* ----------------------------
   LOGIN LOG
---------------------------- */
$log = $conn->prepare("
    INSERT INTO user_logins (public_id, login_type)
    VALUES (?, ?)
");
$log->bind_param("ss", $public_id, $loginType);
$log->execute();
$log->close();

/* ----------------------------
   SESSION
---------------------------- */
session_regenerate_id(true);
$_SESSION['logged_in'] = true;
$_SESSION['user_id']   = $user_id;
$_SESSION['public_id'] = $public_id;
$_SESSION['username']  = $username;

/* ----------------------------
   REDIRECT
---------------------------- */
if ((int)$is_creator === 1) {
    header("Location: /app.php");
} else {
    header("Location: /app-dancer.php");
}

exit;
