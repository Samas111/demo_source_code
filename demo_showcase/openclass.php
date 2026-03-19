<?php
error_reporting(E_ALL);

require __DIR__ . '/secure/logic.php';

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

$openclassId = $_GET['id'] ?? null;
if (!$openclassId) {
    http_response_code(404);
    exit('OpenClass not found');
}

$stmt = $mysqli->prepare("
    SELECT
        o.title,
        o.description,
        o.address,
        o.date,
        o.start_time,
        o.end_time,
        o.price,
        o.capacity,
        o.led_by,
        o.cover_image,
        u.username AS creator_name,
        u.is_venue,
        up.pfp_path AS creator_pfp
    FROM openclasses o
    JOIN users u
        ON u.public_id = o.public_id
    LEFT JOIN user_profile up
        ON up.public_id = u.public_id
    WHERE o.openclass_id = ?
    LIMIT 1
");
$stmt->bind_param('s', $openclassId);
$stmt->execute();
$res = $stmt->get_result();
$openclass = $res->fetch_assoc();
$stmt->close();

if (!$openclass) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Openclass nenalezena</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap">
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #0D0314;
                font-family: Inter, sans-serif;
                color: #fff;
            }
            .box {
                text-align: center;
                max-width: 420px;
                padding: 40px;
            }
            h1 {
                font-size: 1.2rem;
                margin-bottom: 12px;
            }
            p {
                opacity: 0.7;
                margin-bottom: 28px;
                line-height: 1.4;
                font-size: 0.8rem
            }
            button {
                padding: 14px 22px;
                background: linear-gradient(to right, #FF5143, #FF1B73);;
                border: none;
                border-radius: 10px;
                color: #fff;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.15s ease, opacity 0.15s ease;
            }
            button:hover {
                transform: translateY(-1px);
                opacity: 0.9;
            }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Openclass neexistuje</h1>
            <p>
                Odkaz je neplatný nebo byla openclass odstraněna.
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/* --------------------------------
   VIEW LOG (GUEST, 20 MIN COOLDOWN)
-------------------------------- */
$cooldown = 1200;
$now = time();

$viewCookie = 'df_view_' . $openclassId;
$lastView = isset($_COOKIE[$viewCookie]) ? (int)$_COOKIE[$viewCookie] : 0;

if (($now - $lastView) >= $cooldown) {

    $null = null;

    $log = $mysqli->prepare("
        INSERT INTO openclass_views (openclass_id, viewer_public_id)
        VALUES (?, ?)
    ");
    $log->bind_param('ss', $openclassId, $null);
    $log->execute();
    $log->close();

    setcookie(
        $viewCookie,
        (string)$now,
        time() + 86400,
        '/'
    );
}

/* --------------------------------
   CAPACITY CHECK (APP + WEB)
-------------------------------- */

// APP registrations (storno != 1)
$appStmt = $mysqli->prepare("
    SELECT COUNT(*) 
    FROM openclass_registrations
    WHERE openclass_id = ?
      AND storno != 1
");
$appStmt->bind_param('s', $openclassId);
$appStmt->execute();
$appStmt->bind_result($appCount);
$appStmt->fetch();
$appStmt->close();

// WEB registrations (verified = 1)
$webStmt = $mysqli->prepare("
    SELECT COUNT(*) 
    FROM openclass_registrations_web
    WHERE openclass_id = ?
      AND verified = 1
");
$webStmt->bind_param('s', $openclassId);
$webStmt->execute();
$webStmt->bind_result($webCount);
$webStmt->fetch();
$webStmt->close();

$totalTaken = (int)$appCount + (int)$webCount;
$remainingCapacity = max(0, (int)$openclass['capacity'] - $totalTaken);
$isSoldOut = $remainingCapacity <= 0;

/* --------------------------------
   META
-------------------------------- */
$creatorName = $openclass['creator_name'];
$creatorPfp = $openclass['creator_pfp']
    ? $openclass['creator_pfp']
    : '/uploads/profile-pictures/default.png';
$led_by = $openclass['led_by'] ?? 'Unknown';
$is_venue = (int)($openclass['is_venue'] ?? 0);


$startTs = strtotime($openclass['date'] . ' ' . $openclass['start_time']);
$diff = max(0, $startTs - time());
$days = floor($diff / 86400);
$hours = floor(($diff % 86400) / 3600);

$durationMinutes = (strtotime($openclass['end_time']) - strtotime($openclass['start_time'])) / 60;
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($openclass['title']); ?></title>
    <link rel="stylesheet" href="openclass-web.css">
    <link rel="stylesheet" href="register.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
    <link rel="icon" href="logo.png">
</head>
<body>
<div class="app-shell">
    <nav style="font-size: 0.9rem; font-weight: 400;">
        Pro rychlejší registraci si stáhni Aplikaci!<br>
        <strong style="font-size: 0.7rem; font-weight: 800;" style="display: none;">Doporučeno • nejrychlejší způsob</strong>
    </nav>

    <div class="her-img">
        <img src="<?php echo '/uploads/openclasses/' . htmlspecialchars($openclass['cover_image']); ?>" alt="">
    </div>

    <div class="main-content">

        <div class="workshop-card">

            <div class="workshop-header">
                <div class="workshop-title">
                    <?php echo htmlspecialchars($openclass['title']); ?>
                </div>
                <div class="workshop-timeleft">
                </div>
            </div>

            <div class="workshop-location">
                <?php echo htmlspecialchars($openclass['address']); ?>
            </div>

            <div class="workshop-tags">
                <div class="tag">
                    <?php echo date('d.m.Y', strtotime($openclass['date'])); ?>
                </div>
                <div class="tag">
                    <?php echo substr($openclass['start_time'], 0, 5); ?> – <?php echo substr($openclass['end_time'], 0, 5); ?>
                </div>
                <?php if($led_by != "NONE"){ ?>
                    <div class="tag" >
                    Vede @<?php echo htmlspecialchars($led_by);?>
                    </div>
                <?php } ?>

                <p style="padding: 0; margin: 0; opacity: 0.2; font-size: 0.6rem; font-style: italic; padding-top: 4px;">+ 3 v Aplikaci</p>
            </div>
            <div class="workshop-desc">
                <?php echo nl2br(htmlspecialchars($openclass['description'])); ?>
            </div>

            <div class="workshop-actions">
                <div class="btn-group">
                    <button onclick="window.location='download.php'" class="share-btn">Zobrazit v Aplikaci</button>
                </div>

                <div class="price">
                    Cena<br><span><?php echo (int)$openclass['price']; ?> Kč</span>
                </div>
            </div>
        </div>

        <!-- CREATOR CARD – STATIC FOR NOW -->
        <div class="creator-card">

            <div class="creator-left">
                <img src="<?php echo htmlspecialchars($creatorPfp); ?>" style="border-radius: 50%;" class="creator-avatar">

                <div class="creator-info">
                    <div class="creator-top">
                        <span class="creator-name">
                            <?php echo htmlspecialchars($creatorName); ?>
                        </span>
                        <div class="trusted-badge">
                            <img src="source/assets/verify_badge.png" alt="">
                            <?php if ($is_venue == 1): ?>
                                <span>HOSTING VENUE</span>
                            <?php else: ?>
                                <span>TRUSTED BY DANCEFY</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <span class="creator-followers">Oblíbený tvůrce na Dancefy</span>
                </div>
            </div>

        <div class="creator-rating" style="visibility: hidden;">
                <p>Pouze v aplikaci</p>
                <div class="stars">
                    ★ ★ ★ ★ ★ 
                </div>
            </div>
        </div>
    </div>
    <div class="bum">
        <div class="guest-register">
            <span>Web Registrace<span>
            <p class="subtitle">
                Neuloží se ti do portfolia a nedostaneš přístup ke všem funkcím!
            </p>

            <form id="guestRegisterForm">
                <input type="hidden" name="openclass_id" value="<?= htmlspecialchars($openclassId) ?>">

                <div class="form-group">
                    <label>Vaše Jméno</label>
                    <input type="text" name="name" id="guest-name" required>
                </div>

                <div class="form-group">
                    <label>Váš Email</label>
                    <input type="email" name="email" id="guest-email" required>
                </div>

                <button
                    type="submit"
                    class="guest-btn"
                    <?php if ($isSoldOut): ?>
                        disabled
                        style="opacity:0.4; cursor:not-allowed;"
                    <?php endif; ?>
                >
                    <?php echo $isSoldOut ? 'Vyprodáno' : 'Registrovat bez Přihlášení'; ?>
                </button>

            </form>

            <p class="login-hint" style="visibility: hidden;">
                Registruj se jedním kliknutím a získej slevy s
                <span onclick="window.location='download.php'" style="text-decoration: underline;">Dancefy</span>
            </p>
        </div>
    <div id="successPopup" class="popup-overlay" style="display:none;">
        <div class="popup-box success">
            <div class="popup-icon">✓</div>
            <h3>Registrace odeslána</h3>
            <p>
                Pro dokončení registrace potvrď účast v emailu.
                <br>
                Odkaz je platný 15 minut.
            </p>
            <button id="popupClose" class="popup-btn">
                Rozumím
            </button>
        </div>
    </div>
</div>

<script>
const form = document.getElementById('guestRegisterForm');
const popup = document.getElementById('successPopup');
const popupClose = document.getElementById('popupClose');

const isSoldOut = <?= $isSoldOut ? 'true' : 'false' ?>;

if (isSoldOut) {
    form.addEventListener('submit', e => e.preventDefault());
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = form.querySelector('button');
    btn.disabled = true;

    const formData = new URLSearchParams(new FormData(form));

    try {
        const res = await fetch('/register_guest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });

        const data = await res.json();

        if (res.ok && data.success) {
            popup.style.display = 'flex';
            form.reset();
        } else {
            alert(data.error || 'Registrace se nepodařila');
        }
    } catch (err) {
        alert('Chyba připojení k serveru');
    }

    btn.disabled = false;
});

popupClose.onclick = () => {
    popup.style.display = 'none';
};
</script>

</div>
</body>
</html>
