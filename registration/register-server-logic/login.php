<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../private/config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ----------------------------
   INPUT
---------------------------- */
$username = mb_strtolower(trim($_POST['username'] ?? ''), 'UTF-8');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header("Location: ../register-indexes/login.php?e=Špatné přihlašovací údaje");
    exit;
}

$loginType = 'manual';

/* ----------------------------
   DB
---------------------------- */
$conn = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    header("Location: ../register-indexes/login.php?e=Chyba, zkuste to později");
    exit;
}
$conn->set_charset('utf8mb4');

/* ----------------------------
   USER CHECK (USERNAME!)
---------------------------- */
$q = $conn->prepare("
    SELECT user_id, public_id, username, password, is_creator
    FROM users
    WHERE username = ?
    LIMIT 1
");
$q->bind_param("s", $username);
$q->execute();
$q->store_result();

if ($q->num_rows !== 1) {
    header("Location: ../register-indexes/login.php?e=Špatné přihlašovací údaje");
    exit;
}

$q->bind_result($user_id, $public_id, $db_username, $db_password, $is_creator);
$q->fetch();
$q->close();


/* ----------------------------
   PASSWORD VERIFY
---------------------------- */


if (!password_verify($password, $db_password)) {
    header("Location: ../register-indexes/login.php?e=Špatné přihlašovací údaje");
    exit;
}

/* ----------------------------
   TOKEN GENERATION
---------------------------- */
$token = bin2hex(random_bytes(32)); // raw token (cookie)
$tokenHash = hash('sha256', $token);

$ins = $conn->prepare("
    INSERT INTO user_tokens (user_id, token_hash, created_at)
    VALUES (?, ?, NOW())
");
$ins->bind_param("is", $user_id, $tokenHash);
$ins->execute();
$ins->close();

$log = $conn->prepare("
    INSERT INTO user_logins (public_id, login_type)
    VALUES (?, ?)
");
$log->bind_param("ss", $public_id, $loginType);
$log->execute();
$log->close();

/* ----------------------------
   COOKIE
---------------------------- */
setcookie(
    'dancefy_token',
    $token,
    [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => '/',
        'domain'   => APP_PRODUCTION === 1 ? '.dancefy.cz' : '',
        'secure'   => APP_PRODUCTION === 1,
        'httponly' => true,
        'samesite' => APP_PRODUCTION === 1 ? 'None' : 'Lax'
    ]
);

/* ----------------------------
   SESSION
---------------------------- */
session_regenerate_id(true);
$_SESSION['logged_in'] = true;
$_SESSION['user_id']   = $user_id;
$_SESSION['public_id'] = $public_id;
$_SESSION['username']  = $db_username;

$conn->close();

/* ----------------------------
   REDIRECT
---------------------------- */
if ((int)$is_creator === 1) {
    header("Location: /app.php");
} else {
    header("Location: /app-dancer.php");
}
exit;
