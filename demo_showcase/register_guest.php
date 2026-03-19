<?php
require __DIR__ . '/secure/logic.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

if (empty($_SERVER['HTTP_USER_AGENT'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid client']));
}

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

$cleanup = $mysqli->prepare("
    DELETE FROM openclass_registrations_web
    WHERE verified = 0
      AND expires_at < NOW()
");
$cleanup->execute();
$cleanup->close();

$openclassId = $_POST['openclass_id'] ?? null;
$name  = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');

if (!$openclassId || !$name || !$email) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing fields']));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid email']));
}

$ip        = inet_pton($_SERVER['REMOTE_ADDR']);
$userAgent = substr($_SERVER['HTTP_USER_AGENT'], 0, 255);

$cookieId = $_COOKIE['df_reg'] ?? null;
if (!$cookieId) {
    $cookieId = bin2hex(random_bytes(16));
    setcookie('df_reg', $cookieId, time() + 31536000, '/', '', true, true);
}

$fingerprint = hash(
    'sha256',
    $openclassId . '|' . $userAgent . '|' . $cookieId
);

$oc = $mysqli->prepare("
    SELECT
        title,
        address,
        date,
        start_time,
        end_time,
        price,
        level,
        cover_image,
        capacity
    FROM openclasses
    WHERE openclass_id = ?
    LIMIT 1
");
$oc->bind_param('s', $openclassId);
$oc->execute();
$res = $oc->get_result();
$openclass = $res->fetch_assoc();
$oc->close();

if (!$openclass) {
    http_response_code(404);
    exit(json_encode(['error' => 'OpenClass not found']));
}

$cap = $mysqli->prepare("
    SELECT COUNT(*)
    FROM openclass_registrations_web
    WHERE openclass_id = ?
      AND (
        verified = 1
        OR expires_at > NOW()
      )
");
$cap->bind_param('s', $openclassId);
$cap->execute();
$cap->bind_result($used);
$cap->fetch();
$cap->close();

if ($used >= (int)$openclass['capacity']) {
    http_response_code(409);
    exit(json_encode(['error' => 'Capacity full']));
}

$fp = $mysqli->prepare("
    SELECT COUNT(*) 
    FROM openclass_registrations_web
    WHERE openclass_id = ?
      AND fingerprint = ?
      AND (
        verified = 1
        OR expires_at > NOW()
      )
");
$fp->bind_param('ss', $openclassId, $fingerprint);
$fp->execute();
$fp->bind_result($attempts);
$fp->fetch();
$fp->close();

if ($attempts >= 3) {
    http_response_code(409);
    exit(json_encode([
        'success' => false,
        'error' => 'Limit 3 registrací z jednoho zařízení'
    ]));
}

$em = $mysqli->prepare("
    SELECT 1
    FROM openclass_registrations_web
    WHERE openclass_id = ?
      AND email = ?
      AND (
        verified = 1
        OR expires_at > NOW()
      )
    LIMIT 1
");
$em->bind_param('ss', $openclassId, $email);
$em->execute();
$em->store_result();

if ($em->num_rows > 0) {
    http_response_code(409);
    exit(json_encode(['error' => 'Email už byl registrován']));
}
$em->close();

$confirmToken = bin2hex(random_bytes(32));
$expiresAt    = date('Y-m-d H:i:s', time() + 900);

$ins = $mysqli->prepare("
    INSERT INTO openclass_registrations_web
        (openclass_id, name, email, ip_address, user_agent, fingerprint,
         verified, confirm_token, expires_at)
    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)
");

$ins->bind_param(
    'ssssssss',
    $openclassId,
    $name,
    $email,
    $ip,
    $userAgent,
    $fingerprint,
    $confirmToken,
    $expiresAt
);

if (!$ins->execute()) {
    http_response_code(500);
    exit(json_encode(['error' => 'Registration failed']));
}

$confirmUrl = 'https://dancefy.cz/confirm.php?token=' . $confirmToken;

$subject = 'Potvrď registraci – bez potvrzení místo neplatí';

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-type:text/html;charset=UTF-8\r\n";
$headers .= "From: Dancefy <noreply@dancefy.cz>\r\n";

$message = "
<!DOCTYPE html>
<html lang='cs'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>

<body style='margin:0;padding:0;background:#0D0314;font-family:Inter,Arial,sans-serif;color:#ffffff;'>

<table width='100%' cellpadding='0' cellspacing='0' style='background:#0D0314;padding:32px 0;'>
<tr>
<td align='center'>

<table width='100%' cellpadding='0' cellspacing='0'
style='max-width:440px;background:#180028;border-radius:20px;overflow:hidden;
box-shadow:0 20px 60px rgba(0,0,0,0.6);'>

<tr>
<td align='center' style='padding:22px 24px 18px;'>
<img src='https://dancefy.cz/logo.png'
alt='Dancefy'
style='height:34px;display:block;'>
</td>
</tr>

<tr>
<td style='padding:0 24px 16px;text-align:center;'>
<h2 style='margin:0;font-size:22px;font-weight:800;'>
Potvrď svou účast
</h2>
<p style='margin:6px 0 0;font-size:13px;opacity:0.7;'>
Bez potvrzení místo propadá
</p>
</td>
</tr>

<tr>
<td style='padding:12px 16px 4px;'>

<table width='100%' cellpadding='0' cellspacing='0'
style='background:#12001f;border-radius:16px;
border:1px solid rgba(255,255,255,0.06);'>

<tr>

<td width='96' style='padding:12px;'>
<img src='https://dancefy.cz/uploads/openclasses/{$openclass['cover_image']}'
alt=''
style='width:96px;height:96px;border-radius:12px;
object-fit:cover;display:block;
background:linear-gradient(135deg,#FF5143,#FF1B73);'>
</td>

<td style='padding:12px 12px 12px 4px;vertical-align:top;'>

<div style='font-size:11px;
display:inline-block;
padding:4px 10px;
border-radius:999px;
background:rgba(255,81,67,0.15);
color:#FF5143;
font-weight:700;
margin-bottom:6px; display: none;'>
{$openclass['level']}
</div>

<h3 style='margin:0 0 6px;font-size:16px;font-weight:800;'>
{$openclass['title']}
</h3>

<p style='margin:0;font-size:13px;opacity:0.75;line-height:1.4;'>
📍 {$openclass['address']}<br>
🕒 {$openclass['date']} · {$openclass['start_time']}–{$openclass['end_time']}
</p>

<p style='margin:6px 0 0;font-size:14px;font-weight:700;color:#FF1B73;'>
{$openclass['price']} Kč
</p>

</td>
</tr>
</table>

</td>
</tr>

<tr>
<td align='center' style='padding:22px 24px 18px;'>

<a href='{$confirmUrl}'
style='display:block;
width:100%;
text-align:center;
background:linear-gradient(135deg,#FF5143,#FF1B73);
color:#ffffff;
text-decoration:none;
padding:16px 0;
border-radius:16px;
font-size:15px;
font-weight:800;
box-shadow:0 0 0 rgba(255,27,115,0.6);'>
Potvrdit účast
</a>

</td>
</tr>

<tr>
<td align='center'
style='padding:0 24px 24px;
font-size:12px;
opacity:0.55;'>
⏱️ Odkaz je platný 15 minut<br>
Poté se místo automaticky uvolní
</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>
";

@mail($email, $subject, $message, $headers);

echo json_encode(['success' => true]);
