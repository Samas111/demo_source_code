<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../private/config.php';

$conn = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Database connection failed.");
}
$conn->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['website'])) exit;
    
    $raw_username = $_POST['username'] ?? '';
    $full_name    = trim($_POST['full_name'] ?? '');
    $password     = $_POST['password'] ?? '';
    $pass_confirm = $_POST['password_confirm'] ?? '';

    $username = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}]/u', '', $raw_username);
    $username = mb_strtolower(trim($username), 'UTF-8');

    if (!preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
        die("Invalid username format.");
    }
    if ($password !== $pass_confirm || strlen($password) < 8) {
        die("Password error.");
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) throw new Exception("Username taken");

        $public_id = bin2hex(random_bytes(5)); 
        $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
        $is_creator = 0; 
        
        $stmt = $conn->prepare("INSERT INTO users (public_id, username, password, is_creator) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $public_id, $username, $hashed_pass, $is_creator);
        $stmt->execute();
        $user_id = $stmt->insert_id;

        $pfp_path = '/uploads/profile-pictures/default.png';
        if (isset($_FILES['pfp']) && $_FILES['pfp']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['pfp']['tmp_name'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            if (in_array($finfo->file($tmp), ['image/jpeg', 'image/png', 'image/webp'])) {
                $fileName = 'pfp_' . $public_id . '_' . time() . '.jpg';
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profile-pictures/' . $fileName;
                
                $img = new Imagick($tmp);
                $img->autoOrient();
                $img->stripImage();
                $img->resizeImage(1200, 1200, Imagick::FILTER_LANCZOS, 1, true);
                $img->setImageFormat('jpeg');
                $img->writeImage($fullPath);
                $pfp_path = '/uploads/profile-pictures/' . $fileName;
            }
        }

        $stmt = $conn->prepare("INSERT INTO user_profile (user_id, public_id, name, pfp_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $public_id, $full_name, $pfp_path);
        $stmt->execute();

        $tokenRaw = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenRaw);
        
        $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token_hash) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $tokenHash);
        $stmt->execute();

        $loginType = 'registration';
        $log = $conn->prepare("INSERT INTO user_logins (public_id, login_type) VALUES (?, ?)");
        $log->bind_param("ss", $public_id, $loginType);
        $log->execute();

        setcookie("dancefy_token", $tokenRaw, time() + (86400 * 30), "/", "", true, true);

        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id']   = $user_id;
        $_SESSION['public_id'] = $public_id;
        $_SESSION['username']  = $username;

        $conn->commit();

        if ($is_creator === 1) {
            header("Location: /app.php");
        } else {
            header("Location: /app-dancer.php");
        }
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}