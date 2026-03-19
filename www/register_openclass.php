<?php
require __DIR__ . '/secure/logic.php';

header('Content-Type: application/json');

function sendConfirmationEmail(string $to, array $o): void
{
    $subject = 'Registrace potvrzena — ' . $o['title'];

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Dancefy <noreply@dancefy.cz>\r\n";

    $html = "
    <!DOCTYPE html>
    <html lang='cs'>
    <body style='margin:0;background:#0D0314;font-family:Inter,Arial,sans-serif;color:#ffffff;'>
        <table width='100%' style='padding:36px 0;background:#0D0314;'>
            <tr><td align='center'>
                <table width='100%' style='max-width:460px;background:#180028;border-radius:20px;'>
                    <tr>
                        <td align='center' style='padding:24px;'>
                            <img src='https://dancefy.cz/logo.png' style='height:34px;'>
                        </td>
                    </tr>
                    <tr>
                        <td align='center'>
                            <h1 style='margin:0;font-size:24px;font-weight:900;'>Registrace potvrzena</h1>
                            <p style='opacity:.75;'>Máš své místo jisté 🔒</p>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding:20px;'>
                            <div style='background:#12001f;border-radius:16px;padding:16px;'>
                                <strong>{$o['title']}</strong><br>
                                📍 {$o['address']}<br>
                                🕒 {$o['date']} · {$o['start_time']}–{$o['end_time']}
                            </div>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>
    ";

    mail($to, $subject, $html, $headers);
}

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

if (
    empty($_COOKIE['dancefy_token']) ||
    !preg_match('/^[a-f0-9]{64}$/', $_COOKIE['dancefy_token'])
) {
    echo json_encode(['status' => 'error', 'code' => 'unauthenticated']);
    exit;
}

$tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

// UPDATED: Added is_verified to the SELECT
$t = $mysqli->prepare("
    SELECT u.public_id, u.email, u.is_verified
    FROM user_tokens ut
    JOIN users u ON u.user_id = ut.user_id
    WHERE ut.token_hash = ?
    LIMIT 1
");
$t->bind_param('s', $tokenHash);
$t->execute();
$t->bind_result($public_id, $userEmail, $isVerified);

if (!$t->fetch()) {
    echo json_encode(['status' => 'error', 'code' => 'invalid_session']);
    exit;
}
$t->close();

$openclassId = $_POST['openclass_id'] ?? null;
if (!$openclassId) {
    echo json_encode(['status' => 'error', 'code' => 'missing_openclass']);
    exit;
}

if ($isVerified == 0) {
    echo json_encode(['status' => 'error', 'code' => 'missing_email']);
    exit;
}

if (empty($userEmail)) {
    echo json_encode(['status' => 'error', 'code' => 'missing_email']);
    exit;
}

$o = $mysqli->prepare("
    SELECT title, address, date, start_time, end_time
    FROM openclasses
    WHERE openclass_id = ?
    LIMIT 1
");
$o->bind_param('s', $openclassId);
$o->execute();
$openclass = $o->get_result()->fetch_assoc();
$o->close();

if (!$openclass) {
    echo json_encode(['status' => 'error', 'code' => 'openclass_not_found']);
    exit;
}

$ins = $mysqli->prepare("
    INSERT INTO openclass_registrations (openclass_id, public_id)
    VALUES (?, ?)
");
$ins->bind_param('ss', $openclassId, $public_id);

if (!$ins->execute()) {
    if ($mysqli->errno === 1062) {
        echo json_encode(['status' => 'error', 'code' => 'already_registered']);
        exit;
    }

    echo json_encode(['status' => 'error', 'code' => 'registration_failed']);
    exit;
}
$ins->close();

$owner = $mysqli->prepare("
    SELECT o.public_id, up.auto_message 
    FROM openclasses o
    LEFT JOIN user_profile up ON o.public_id = up.public_id
    WHERE o.openclass_id = ? LIMIT 1
");
$owner->bind_param('s', $openclassId);
$owner->execute();
$owner->bind_result($creator_public_id, $autoMessage);
$owner->fetch();
$owner->close();

if (!empty($creator_public_id) && $creator_public_id !== $public_id) {
    $notif = $mysqli->prepare("
        INSERT INTO notifications (recipient_public_id, actor_public_id, title, content, link) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $nTitle = 'Nová registrace';
    $nContent = 'Nová registrace na OpenClass: ' . $openclass['title'];
    $nLink = 'attendance.php?id=' . $openclassId;
    $notif->bind_param('sssss', $creator_public_id, $public_id, $nTitle, $nContent, $nLink);
    $notif->execute();
    $notif->close();

    if (!empty($autoMessage)) {
        $dm = $mysqli->prepare("
            INSERT INTO dms (sender_public_id, receiver_public_id, message, is_read) 
            VALUES (?, ?, ?, 0)
        ");
        $dm->bind_param('sss', $creator_public_id, $public_id, $autoMessage);
        $dm->execute();
        $dm->close();
    }
}

sendConfirmationEmail($userEmail, $openclass);

echo json_encode(['status' => 'ok']);
exit;