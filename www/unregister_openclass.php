<?php
require __DIR__ . '/secure/logic.php';

function sendConfirmationEmail(string $to, array $o, string $type): void
{
    $isCancel = $type === 'cancel';

    $subject = $isCancel
        ? 'Registrace zrušena — ' . $o['title']
        : 'Registrace potvrzena — ' . $o['title'];

    $headline = $isCancel ? 'Registrace zrušena' : 'Registrace potvrzena';
    $subtitle = $isCancel
        ? 'Tvé místo bylo uvolněno'
        : 'Máš své místo jisté 🔒';

    $status = $isCancel ? 'Zrušeno' : 'Rezervováno';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Dancefy <noreply@dancefy.cz>\r\n";

    $html = "
    <!DOCTYPE html>
    <html lang='cs'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='margin:0;background:#0D0314;font-family:Inter,Arial,sans-serif;color:#ffffff;'>
        <table width='100%' style='padding:36px 0;background:#0D0314;'>
            <tr>
                <td align='center'>
                    <table width='100%' style='max-width:460px;background:#180028;border-radius:20px;'>
                        <tr>
                            <td align='center' style='padding:24px;'>
                                <img src='https://dancefy.cz/logo.png' style='height:34px;'>
                            </td>
                        </tr>
                        <tr>
                            <td align='center' style='padding:0 24px 12px;'>
                                <h1 style='margin:0;font-size:24px;font-weight:900;'>$headline</h1>
                                <p style='margin:6px 0 0;font-size:14px;opacity:0.75;'>$subtitle</p>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding:20px;'>
                                <div style='background:#12001f;border-radius:16px;padding:16px;'>
                                    <div style='font-size:11px;font-weight:700;color:#FF1B73;margin-bottom:6px;'>$status</div>
                                    <h3 style='margin:0 0 6px;font-size:17px;font-weight:800;'>{$o['title']}</h3>
                                    <p style='margin:0;font-size:13px;opacity:0.8;line-height:1.5;'>
                                        📍 {$o['address']}<br>
                                        🕒 {$o['date']} · {$o['start_time']}–{$o['end_time']}
                                    </p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td align='center' style='padding:16px;font-size:12px;opacity:0.55;'>
                                Tento email slouží jako potvrzení změny registrace.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
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
    http_response_code(401);
    exit('Not authenticated');
}

$tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

$t = $mysqli->prepare("
    SELECT u.public_id, u.email
    FROM user_tokens ut
    JOIN users u ON u.user_id = ut.user_id
    WHERE ut.token_hash = ?
    LIMIT 1
");
$t->bind_param('s', $tokenHash);
$t->execute();
$t->bind_result($public_id, $userEmail);

if (!$t->fetch()) {
    http_response_code(401);
    exit('Invalid session');
}
$t->close();

$openclassId = $_POST['openclass_id'] ?? null;
if (!$openclassId) {
    http_response_code(400);
    exit('Missing openclass id');
}

$o = $mysqli->prepare("
    SELECT title, address, date, start_time, end_time, cover_image
    FROM openclasses
    WHERE openclass_id = ?
    LIMIT 1
");
$o->bind_param('s', $openclassId);
$o->execute();
$openclass = $o->get_result()->fetch_assoc();
$o->close();

if (!$openclass) {
    http_response_code(404);
    exit('Openclass not found');
}

$u = $mysqli->prepare("
    UPDATE openclass_registrations
    SET storno = 1,
        canceled_at = NOW()
    WHERE openclass_id = ?
      AND public_id = ?
      AND storno = 0
    LIMIT 1
");
$u->bind_param('ss', $openclassId, $public_id);
$u->execute();

if ($u->affected_rows === 0) {
    http_response_code(409);
    exit('Already canceled or not registered');
}
$u->close();

/* --------------------------------
   REPUTATION LOG APPEND (LAST 12 ONLY)
-------------------------------- */

$startTs = strtotime($openclass['date'] . ' ' . $openclass['start_time']);
$nowTs = time();
$diffSeconds = $startTs - $nowTs;

if ($diffSeconds > 0) {

    if ($diffSeconds >= 7 * 86400) {
        $delta = -10;
    } elseif ($diffSeconds >= 3 * 86400) {
        $delta = -20;
    } elseif ($diffSeconds >= 1 * 86400) {
        $delta = -30;
    } else {
        $delta = -50;
    }

    $deltaStr = ($delta > 0 ? '+' : '') . $delta;

    $rep = $mysqli->prepare("
        UPDATE user_profile
        SET reputation = (
            SELECT SUBSTRING_INDEX(
                CONCAT(
                    IFNULL(NULLIF(reputation, ''), ''),
                    IF(reputation IS NULL OR reputation = '', '', ','),
                    ?
                ),
                ',',
                -12
            )
        )
        WHERE public_id = ?
        LIMIT 1
    ");

    $rep->bind_param('ss', $deltaStr, $public_id);
    $rep->execute();
    $rep->close();
}


if (!empty($userEmail)) {
    sendConfirmationEmail($userEmail, $openclass, 'cancel');
}

echo 'OK';
