<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/web-logic.php';
header('Content-Type: application/json');

try {
    $mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
    $mysqli->set_charset('utf8mb4');

    $openclassId = $_POST['openclass_id'] ?? null;
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$openclassId || !$name || !$email) {
        throw new Exception('Vyplňte jméno i email.');
    }

    // Client info for your specific table columns
    $ip = inet_pton($_SERVER['REMOTE_ADDR']);
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    // Generating a dummy fingerprint since your DB requires it (NOT NULL)
    $fingerprint = hash('sha256', $email . $openclassId);

    // 1. Get Class Info
    $ocStmt = $mysqli->prepare("SELECT title, address, date, start_time, end_time, price, cover_image FROM openclasses WHERE openclass_id = ? LIMIT 1");
    $ocStmt->bind_param('s', $openclassId);
    $ocStmt->execute();
    $openclass = $ocStmt->get_result()->fetch_assoc();
    $ocStmt->close();

    if (!$openclass) throw new Exception('Lekce nenalezena.');

    // 2. Check existing using YOUR column names
    $em = $mysqli->prepare("SELECT id, verified FROM openclass_registrations_web WHERE openclass_id = ? AND email = ? LIMIT 1");
    $em->bind_param('ss', $openclassId, $email);
    $em->execute();
    $existing = $em->get_result()->fetch_assoc();
    $em->close();

    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + 900);
    $isResend = false;

    if ($existing) {
        if ((int)$existing['verified'] === 1) {
            http_response_code(409);
            exit(json_encode(['success' => false, 'error' => 'Tento email je již potvrzený.']));
        }
        // Use 'id' instead of 'registration_id'
        $upd = $mysqli->prepare("UPDATE openclass_registrations_web SET confirm_token = ?, expires_at = ? WHERE id = ?");
        $upd->bind_param('ssi', $token, $expiry, $existing['id']);
        $upd->execute();
        $isResend = true;
    } else {
        // Match YOUR table structure exactly
        $ins = $mysqli->prepare("INSERT INTO openclass_registrations_web (openclass_id, name, email, ip_address, user_agent, fingerprint, verified, confirm_token, expires_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)");
        $ins->bind_param('ssssssss', $openclassId, $name, $email, $ip, $ua, $fingerprint, $token, $expiry);
        $ins->execute();
    }

    // 3. Send Email (Your Original Branded HTML)
    $confirmUrl = "https://dancefy.cz/logic/confirm.php?token=$token";
    $subject = 'Potvrď registraci – Dancefy';
    $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Dancefy <noreply@dancefy.cz>\r\n";
    
    $message = "
    <!DOCTYPE html>
    <html lang='cs'>
    <head><meta charset='UTF-8'></head>
    <body style='margin:0;padding:0;background:#0D0314;font-family:Inter,Arial,sans-serif;color:#ffffff;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background:#0D0314;padding:32px 0;'>
    <tr><td align='center'>
    <table width='100%' cellpadding='0' cellspacing='0' style='max-width:440px;background:#180028;border-radius:20px;overflow:hidden;'>
    <tr><td align='center' style='padding:22px 24px 18px;'><img src='https://dancefy.cz/logo.png' alt='Dancefy' style='height:34px;'></td></tr>
    <tr><td style='padding:0 24px 16px;text-align:center;'>
    <h2 style='margin:0;font-size:22px;font-weight:800;'>Potvrď svou účast</h2>
    <p style='margin:6px 0 0;font-size:13px;opacity:0.7;'>Bez potvrzení místo propadá</p>
    </td></tr>
    <tr><td style='padding:12px 16px 4px;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background:#12001f;border-radius:16px;border:1px solid rgba(255,255,255,0.06);'>
    <tr><td width='96' style='padding:12px;'><img src='https://dancefy.cz/uploads/openclasses/{$openclass['cover_image']}' style='width:96px;height:96px;border-radius:12px;object-fit:cover;'></td>
    <td style='padding:12px 12px 12px 4px;vertical-align:top;'>
    <h3 style='margin:0 0 6px;font-size:16px;font-weight:800;'>{$openclass['title']}</h3>
    <p style='margin:0;font-size:13px;opacity:0.75;'>📍 {$openclass['address']}<br>🕒 {$openclass['date']} · {$openclass['start_time']}</p>
    <p style='margin:6px 0 0;font-size:14px;font-weight:700;color:#FF1B73;'>{$openclass['price']} Kč</p>
    </td></tr></table></td></tr>
    <tr><td align='center' style='padding:22px 24px 18px;'><a href='{$confirmUrl}' style='display:block;background:linear-gradient(135deg,#FF5143,#FF1B73);color:#ffffff;text-decoration:none;padding:16px 0;border-radius:16px;font-size:15px;font-weight:800;'>Potvrdit účast</a></td></tr>
    <tr><td align='center' style='padding:0 24px 24px;font-size:12px;opacity:0.55;'>⏱️ Odkaz je platný 15 minut</td></tr>
    </table></td></tr></table></body></html>";

    if (@mail($email, $subject, $message, $headers)) {
        echo json_encode(['success' => true, 'resend' => $isResend]);
    } else {
        throw new Exception('Chyba při odesílání emailu.');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}