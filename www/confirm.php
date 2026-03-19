<?php
require __DIR__ . '/secure/logic.php';

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

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
                    <tr><td align='center' style='padding:24px;'>
                        <img src='https://dancefy.cz/logo.png' style='height:34px;'>
                    </td></tr>
                    <tr><td align='center'>
                        <h1 style='margin:0;'>Registrace potvrzena</h1>
                        <p>Máš své místo jisté 🔒</p>
                    </td></tr>
                    <tr><td style='padding:20px;'>
                        <strong>{$o['title']}</strong><br>
                        📍 {$o['address']}<br>
                        🕒 {$o['date']} · {$o['start_time']}–{$o['end_time']}
                    </td></tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>
    ";

    mail($to, $subject, $html, $headers);
}

$token = $_GET['token'] ?? null;
$state = 'non-valid';

if ($token && preg_match('/^[a-f0-9]{64}$/', $token)) {

    $sel = $mysqli->prepare("
        SELECT id, openclass_id, email
        FROM openclass_registrations_web
        WHERE confirm_token = ?
          AND verified = 0
          AND expires_at > NOW()
        LIMIT 1
    ");
    $sel->bind_param('s', $token);
    $sel->execute();
    $reg = $sel->get_result()->fetch_assoc();
    $sel->close();

    if (!$reg) {
        $state = 'expired';
    } else {
        $cap = $mysqli->prepare("
            SELECT
                o.capacity,
                (
                    SELECT COUNT(*) FROM openclass_registrations
                    WHERE openclass_id = o.openclass_id AND storno != 1
                ) +
                (
                    SELECT COUNT(*) FROM openclass_registrations_web
                    WHERE openclass_id = o.openclass_id AND verified = 1
                ) AS taken
            FROM openclasses o
            WHERE o.openclass_id = ?
            LIMIT 1
        ");
        $cap->bind_param('s', $reg['openclass_id']);
        $cap->execute();
        $data = $cap->get_result()->fetch_assoc();
        $cap->close();

        if (!$data || $data['taken'] >= $data['capacity']) {
            $state = 'capacity_full';
        } else {
            $upd = $mysqli->prepare("
                UPDATE openclass_registrations_web
                SET verified = 1,
                    confirm_token = NULL,
                    expires_at = NULL
                WHERE id = ?
                  AND verified = 0
                LIMIT 1
            ");
            $upd->bind_param('i', $reg['id']);
            $upd->execute();

            if ($upd->affected_rows !== 1) {
                $state = 'expired';
                $upd->close();
            } else {
                $info = $mysqli->prepare("
                    SELECT public_id, title, date, start_time, end_time, address, cover_image
                    FROM openclasses
                    WHERE openclass_id = ?
                    LIMIT 1
                ");
                $info->bind_param('s', $reg['openclass_id']);
                $info->execute();
                $openclass = $info->get_result()->fetch_assoc();
                $info->close();

                if ($openclass && filter_var($reg['email'], FILTER_VALIDATE_EMAIL)) {
                    sendConfirmationEmail($reg['email'], $openclass);
                }

                if (!empty($openclass['public_id'])) {
                    $notif = $mysqli->prepare("
                        INSERT INTO notifications (
                            recipient_public_id,
                            actor_public_id,
                            title,
                            content,
                            link,
                            is_read
                        ) VALUES (?, NULL, ?, ?, ?, 0)
                    ");

                    $title = 'Nová registrace';
                    $content = 'Nová registrace z webu: ' . $openclass['title'];
                    $link = 'attendance.php?id=' . $reg['openclass_id'];

                    $notif->bind_param('ssss', $openclass['public_id'], $title, $content, $link);
                    $notif->execute();
                    $notif->close();
                }

                $state = 'confirmed';
                $upd->close();
            }
        }
    }
}

$contentMap = [
    'confirmed' => [
        'title' => 'Rezervováno!',
        'desc'  => 'V emailu máš potvrzení.',
        'icon'  => ''
    ],
    'capacity_full' => [
        'title' => 'Kapacita plná',
        'desc'  => 'Bohužel někdo obsadil poslední místo.',
        'icon'  => ''
    ],
    'expired' => [
        'title' => 'Platnost vypršela',
        'desc'  => 'Tento odkaz již není platný.',
        'icon'  => ''
    ],
    'non-valid' => [
        'title' => 'Neplatný odkaz',
        'desc'  => 'Odkaz pro potvrzení není správný.',
        'icon'  => ''
    ]
];

$ui = $contentMap[$state] ?? $contentMap['non-valid'];
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potvrzení registrace | Dancefy</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap">
    <link rel="stylesheet" href="cancel.css">
    <style>
        .status-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 20px;
            width: 100%;
            max-width: 400px;
        }

        .status-icon {
            font-size: 64px;
            margin-bottom: 24px;
            filter: drop-shadow(0 0 20px rgba(255, 81, 67, 0.3));
        }

        .status-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            margin: 0 0 10px 0;
            background: #fff;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .status-desc {
            font-size: 1.1rem;
            opacity: 0.7;
            margin-bottom: 40px;
            font-weight: 400;
            line-height: 1.4;
        }

        .download-btn {
            width: 100%;
            text-decoration: none;
            color: white;
            padding: 18px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            background: var(--gradient-color);
            transition: transform 0.2s;
            display: block;
        }

        .download-btn:active {
            transform: scale(0.97);
        }

        .notice {
            min-height: calc(100vh - 150px);
            padding-bottom: 20px;
        }
    </style>
</head>
<body>

<nav class="nav">
    <div class="nav-btn nav-left" style="visibility: hidden;"></div>
    <h1 class="nav-title">Dancefy OpenClass</h1>
    <div class="nav-btn" style="visibility: hidden;"></div>
</nav>

<div class="notice">
    <div class="status-container">
        <div class="status-icon"><?= $ui['icon'] ?></div>
        <h1 class="status-title"><?= htmlspecialchars($ui['title']) ?></h1>
        <p class="status-desc"><?= htmlspecialchars($ui['desc']) ?></p>
        
        <a href="https://dancefy.cz/download" class="download-btn">
            Stáhnout Dancefy
        </a>
    </div>
</div>

</body>
</html>