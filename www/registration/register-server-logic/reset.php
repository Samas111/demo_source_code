<?php

error_reporting(E_ALL);

require_once __DIR__ . '/../../secure/logic.php';

$conn = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    header("Location: ../register-indexes/reset.html?message=error");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$email = trim($_POST['email'] ?? '');
$redirectBase = "../register-indexes/reset.html";

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: $redirectBase?message=invalid_email");
    exit;
}

$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {

    $userId = (int)$row['user_id'];

    $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $del->bind_param("i", $userId);
    $del->execute();

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $ins = $conn->prepare(
        "INSERT INTO password_resets (user_id, token_hash) VALUES (?, ?)"
    );
    $ins->bind_param("is", $userId, $tokenHash);
    $ins->execute();

    $resetLink = "https://dancefy.cz/reset-password.php?token=" . $token;

    $headers  = "From: Dancefy <noreply@dancefy.cz>\r\n";
    $headers .= "Reply-To: noreply@dancefy.cz\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $emailBody = '
    <!DOCTYPE html>
    <html lang="cs">
    <head>
    <meta charset="UTF-8">
    <title>Reset hesla – Dancefy</title>
    </head>
    <body style="margin:0;padding:0;background:#0f0f12;font-family:Inter,Arial,sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f12;padding:40px 0;">
    <tr>
    <td align="center">

    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#141418;border-radius:16px;padding:32px;color:#ffffff;">
        
    <tr>
    <td align="center" style="padding-bottom:16px;">
        <img src="https://dancefy.cz/source/assets/logo.png" width="48" height="48" alt="Dancefy">
    </td>
    </tr>

    <tr>
    <td align="center" style="font-size:20px;font-weight:600;padding-bottom:8px;">
        Reset hesla
    </td>
    </tr>

    <tr>
    <td align="center" style="font-size:14px;color:#b3b3b3;padding-bottom:24px;">
        Požádal/a jsi o změnu hesla ke svému účtu na Dancefy
    </td>
    </tr>

    <tr>
    <td style="font-size:14px;color:#e0e0e0;line-height:1.6;padding-bottom:24px;">
        Pro nastavení nového hesla klikni na tlačítko níže.  
        Odkaz je platný <strong>30 minut</strong>.
    </td>
    </tr>

    <tr>
    <td align="center" style="padding-bottom:32px;">
        <a href="'.$resetLink.'" 
        style="display:inline-block;padding:14px 28px;background:#ffffff;color:#0f0f12;
                text-decoration:none;font-weight:600;border-radius:12px;">
            Nastavit nové heslo
        </a>
    </td>
    </tr>

    <tr>
    <td style="font-size:13px;color:#8c8c8c;line-height:1.6;">
        Pokud jsi o reset hesla nežádal/a, tento email můžeš ignorovat.  
        Tvé heslo zůstane beze změny.
    </td>
    </tr>

    <tr>
    <td style="font-size:12px;color:#666;padding-top:24px;">
        © '.date('Y').' Dancefy
    </td>
    </tr>

    </table>

    </td>
    </tr>
    </table>

    </body>
    </html>
    ';
    mail(
        $email,
        "Reset hesla – Dancefy",
        $emailBody,
        $headers
    );
}

header("Location: $redirectBase?message=sent");
exit;
