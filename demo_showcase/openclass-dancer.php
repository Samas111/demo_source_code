<?php
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;

require __DIR__ . '/secure/logic.php';

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

$openclassId = $_GET['id'] ?? null;
if (!$openclassId) {
    http_response_code(404);
    exit('OpenClass not found');
}

$triggerAnimation = $_GET['animation'] ?? null;

$stmt = $mysqli->prepare("
    SELECT
        o.title,
        o.description,
        o.address,
        o.latitude,  
        o.longitude,
        o.level,
        o.level,
        o.date,
        o.start_time,
        o.end_time,
        o.price,
        o.capacity,
        o.led_by,
        o.is_qr,
        u.public_id AS creator_public_id,
        o.cover_image,
        u.user_id AS creator_user_id,
        u.username,
        u.is_verified,
        u.is_venue,
        up.pfp_path,
        up.bio,
        up.iban
    FROM openclasses o
    JOIN users u ON u.public_id = o.public_id
    LEFT JOIN user_profile up ON up.public_id = o.public_id
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
            <button onclick="window.location='app.php'">
                Zpět do aplikace
            </button>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$creatorFollowers = 0;

if (!empty($openclass['creator_public_id'])) {
    $fs = $mysqli->prepare("
        SELECT COUNT(*)
        FROM user_follows
        WHERE followed_public_id = ?
    ");
    $fs->bind_param('s', $openclass['creator_public_id']);
    $fs->execute();
    $fs->bind_result($creatorFollowers);
    $fs->fetch();
    $fs->close();
}

$creatorFollowers = (int)$creatorFollowers;

$appCountStmt = $mysqli->prepare("
    SELECT COUNT(*) 
    FROM openclass_registrations
    WHERE openclass_id = ?
      AND storno != 1
");
$appCountStmt->bind_param('s', $openclassId);
$appCountStmt->execute();
$appCountStmt->bind_result($appCount);
$appCountStmt->fetch();
$appCountStmt->close();

$webCountStmt = $mysqli->prepare("
    SELECT COUNT(*) 
    FROM openclass_registrations_web
    WHERE openclass_id = ?
      AND verified = 1
");
$webCountStmt->bind_param('s', $openclassId);
$webCountStmt->execute();
$webCountStmt->bind_result($webCount);
$webCountStmt->fetch();
$webCountStmt->close();

$totalTaken = (int)$appCount + (int)$webCount;
$remainingCapacity = max(0, (int)$openclass['capacity'] - $totalTaken);

$creatorName = $openclass['username'] ?? 'Unknown';
$creatorPfp = !empty($openclass['pfp_path']) ? $openclass['pfp_path'] : 'default.png';
$creatorVerified = (int)$openclass['is_verified'] === 1;
$led_by = $openclass['led_by'] ?? 'Unknown';

$is_venue = (int)($openclass['is_venue'] ?? 0);
$startTs = strtotime($openclass['date'] . ' ' . $openclass['start_time']);
$diff = max(0, $startTs - time());
$days = floor($diff / 86400);
$hours = floor(($diff % 86400) / 3600);

$durationMinutes = (strtotime($openclass['end_time']) - strtotime($openclass['start_time'])) / 60;

$isRegistered = false;
$hasCanceled = false;

if (
    !empty($_COOKIE['dancefy_token']) &&
    preg_match('/^[a-f0-9]{64}$/', $_COOKIE['dancefy_token'])
) {
    $tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

    $u = $mysqli->prepare("
        SELECT u.public_id, u.is_creator, u.username
        FROM user_tokens ut
        JOIN users u ON u.user_id = ut.user_id
        WHERE ut.token_hash = ?
        LIMIT 1
    ");

    $u->bind_param('s', $tokenHash);
    $u->execute();

    $u->bind_result($viewer_public_id, $viewer_is_creator, $viewer_username);

    if ($u->fetch()) {
        $u->close();

        $backUrl = 'app.php?tab=feed';

        if (isset($viewer_is_creator)) {
            if ((int)$viewer_is_creator === 0) {
                $backUrl = 'app-dancer.php?tab=feed.php';
            } else {
                $backUrl = 'app.php?tab=feed';
            }
        }

        $check = $mysqli->prepare("
            SELECT viewed_at
            FROM openclass_views
            WHERE openclass_id = ?
              AND viewer_public_id = ?
            ORDER BY viewed_at DESC
            LIMIT 1
        ");
        $check->bind_param('ss', $openclassId, $viewer_public_id);
        $check->execute();
        $check->bind_result($lastViewedAt);

        $shouldLog = true;
        if ($check->fetch()) {
            if (time() - strtotime($lastViewedAt) < 1200) {
                $shouldLog = false;
            }
        }
        $check->close();

        if ($shouldLog) {
            $log = $mysqli->prepare("
                INSERT INTO openclass_views (openclass_id, viewer_public_id)
                VALUES (?, ?)
            ");
            $log->bind_param('ss', $openclassId, $viewer_public_id);
            $log->execute();
            $log->close();
        }

        $r = $mysqli->prepare("
            SELECT storno
            FROM openclass_registrations
            WHERE openclass_id = ?
              AND public_id = ?
            LIMIT 1
        ");
        $r->bind_param('ss', $openclassId, $viewer_public_id);
        $r->execute();
        $r->bind_result($storno);

        if ($r->fetch()) {
            if ((int)$storno === 0) {
                $isRegistered = true;
            } else {
                $hasCanceled = true;
            }
        }
        $r->close();
    } else {
        $u->close();
    }
}

$nowTs = time();
$isPast = $startTs <= $nowTs; 

$levelMap = [
    'beginner' => 'Začátečník',
    'intermediate' => 'Pokročilý',
    'pro' => 'Profesionál',
];


require_once __DIR__ . '/phpqrcode/qrlib.php';

$qrImageBase64 = null;

if ($isRegistered && !$isPast && (int)($openclass['is_qr'] ?? 0) === 1) {
    
    $price = (int)$openclass['price'];
    $ibanRaw = $openclass['iban'] ?? '';
    $ibanRaw = trim($ibanRaw);

    $iban = '';

    if ($ibanRaw !== '' && strtolower($ibanRaw) !== 'none') {
        $iban = preg_replace('/\s+/', '', $ibanRaw);
    }


    if ($price > 0 && !empty($iban)) {

        $usernameForPayment = $viewer_username ?? 'DancefyUser';
        $variableSymbol = crc32($openclassId . $viewer_public_id);

        $spayd = "SPD*1.0";
        $spayd .= "*ACC:" . $iban;
        $spayd .= "*AM:" . number_format($price, 2, '.', '');
        $spayd .= "*CC:CZK";
        $cleanTitle = preg_replace('/[^a-zA-Z0-9 \-\_]/u', '', $openclass['title']);
        $cleanUser = preg_replace('/[^a-zA-Z0-9_\-]/u', '', $usernameForPayment);

        $spayd .= "*MSG:DF {$cleanUser} | {$cleanTitle}";

        $spayd .= "*X-VS:" . $variableSymbol;

        $qrFile = 'temp/qr_' . md5($openclassId . $viewer_public_id) . '.png';

        if (!is_dir(__DIR__ . '/temp')) {
            mkdir(__DIR__ . '/temp', 0755, true);
        }

        QRcode::png($spayd, __DIR__ . '/' . $qrFile, QR_ECLEVEL_Q, 6);
    }
}


?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($openclass['title']); ?></title>
    <link rel="stylesheet" href="source/styles/openclass.css?version=2">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <style>
        #map {
            height: 400px;
            width: 100%;
            z-index: 1;
            background: #0D0314;
        }

        .custom-pin {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .pin-main {
            width: 24px;
            height: 24px;
            background: #ff4757;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-40deg);
            border: 3px solid #fff;
        }
        .pin-main::after {
            content: '';
            width: 8px;
            height: 8px;
            background: #fff;
            position: absolute;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            margin-left: -4px;
            margin-top: -4px;
        }
        .publish-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 3, 20, 0.8);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.4s ease-out;
        }

        .publish-badge {
            text-align: center;
            background: linear-gradient(135deg, #FF5143, #FF1B73);
            padding: 30px 50px;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(255, 27, 115, 0.3);
            transform: scale(0.9);
            animation: popIn 0.5s cubic-bezier(0.17, 0.89, 0.32, 1.27) forwards;
        }

        .publish-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .publish-text {
            font-weight: 800;
            letter-spacing: 2px;
            font-size: 1.2rem;
            color: #fff;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes popIn {
            to { transform: scale(1); }
        }

        .fade-out {
            opacity: 0;
            transition: opacity 0.5s ease;
            pointer-events: none;
        }
    </style>
</head>
<body>

<div id="publishOverlay" class="publish-overlay" style="display: none;">
    <div class="publish-badge">
        <div class="publish-icon">🏆</div>
        <div class="publish-text">OPENCLASS ZVEŘEJNĚNA</div>
    </div>
</div>

<?php
$percentFull = $openclass['capacity'] > 0
    ? round(($totalTaken / $openclass['capacity']) * 100)
    : 0;
?>

<nav class="
    <?php
        if ($isPast) echo 'nav-disabled';
        elseif ($isRegistered) echo 'nav-success';
    ?>
    ">
    <?php if ($isPast): ?>
        Tato openclass již proběhla

    <?php elseif ($isRegistered): ?>
        Jsi registrován! Těšíme se na tebe!

    <?php elseif ($hasCanceled): ?>
        Registrace byla zrušena

    <?php elseif ($remainingCapacity <= 0): ?>
        Vyprodáno! Žádná volná místa!

    <?php elseif ($totalTaken <= 2): ?>
        Registrace právě otevřena

    <?php elseif ($remainingCapacity <= 5): ?>
        🚨 Posledních <strong><?= $remainingCapacity ?></strong> míst!

    <?php elseif ($percentFull >= 85): ?>
        🔥 <?= $percentFull ?>% kapacity obsazeno

    <?php elseif ($percentFull >= 60): ?>
        Více než polovina míst je pryč (<?= $percentFull ?>%)

    <?php else: ?>
        Registrace otevřena · <?= $totalTaken ?> přihlášeno
    <?php endif; ?>
</nav>

<img src="/source/assets/back.png"
     class="nav-back"
     id="navBack"
     onclick="window.history.back();">
<div class="her-img" loading="lazy" decoding="async">
    <img src="<?php echo '/uploads/openclasses/' . htmlspecialchars($openclass['cover_image']); ?>" alt="">
</div>

<div class="main-content">
    <div class="workshop-card">
        <?php
        $percentFull = $openclass['capacity'] > 0
            ? round(($totalTaken / $openclass['capacity']) * 100)
            : 0;
        ?>
        <?php if ($openclass['capacity'] > 0 && $percentFull >= 10): ?>
            <div class="capacity-block">
                <div class="capacity-info">
                    <div class="time-left">
                        <?php
                            if ($isPast) {
                                echo "Proběhlo";
                            } elseif ($days == 0 && $hours <= 6) {
                                echo "🚨 Dnes večer";
                            } elseif ($days == 0) {
                                echo "🚨 Dnes";
                            } elseif ($days <= 2) {
                                echo "🔥 Za $days dny";
                            } else {
                                echo "Za $days dní $hours hodin";
                            }
                        ?>
                    </div>
                    <div class="capacity-right">
                        <?= $totalTaken ?> / <?= (int)$openclass['capacity'] ?> obsazeno
                    </div>
                </div>
                <div class="capacity-bar">
                    <div class="capacity-fill" style="width: <?= $percentFull ?>%;"></div>
                </div>
            </div>
        <?php endif; ?>
        <div class="workshop-header">
            <div class="workshop-title">
                <?php echo htmlspecialchars($openclass['title']); ?>
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
            <?php if($led_by == "NONE"){ ?>
                <div class="tag">
                    <?php echo (int)$durationMinutes; ?> Minut
                </div>
            <?php } ?>
            <div class="tag">
                <?php
                    $levelKey = $openclass['level'];
                    echo htmlspecialchars($levelMap[$levelKey] ?? $levelKey);
                ?>
            </div>
            <?php if($led_by != "NONE"){ ?>
                <div class="tag" >
                Vede @<?php echo htmlspecialchars($led_by);?>
                </div>
            <?php } ?>
        </div>


        <div class="workshop-desc">
            <?php echo nl2br(htmlspecialchars($openclass['description'])); ?>
        </div>

        <div class="workshop-actions">
            <div class="btn-group">
                
                <?php if (isset($viewer_public_id) && $viewer_public_id !== $openclass['creator_public_id']): ?>
                    <button
                        class="share-btn"
                        id="registerBtn"
                        type="button"
                        <?php if ($isPast || $hasCanceled || ($remainingCapacity <= 0 && !$isRegistered)): ?>
                            disabled
                            style="opacity:0.35; cursor:not-allowed;"
                        <?php elseif ($isRegistered): ?>
                            style="opacity:0.5;"
                        <?php endif; ?>
                    >
                        <?php
                        if ($isPast) {
                            echo 'Proběhlo';
                        } elseif ($hasCanceled) {
                            echo 'Zrušeno';
                        } elseif ($isRegistered) {
                            echo 'Zrušit registraci';
                        } elseif ($remainingCapacity <= 0) {
                            echo 'Vyprodáno';
                        } else {
                            echo 'Rezervovat místo';
                        }
                        ?>
                    </button>
                <?php endif; ?>

                <button class="promote-btn" id="shareBtn">Sdílet</button>
            </div>

            <div class="price">
                Vstupné<br><span><?php echo (int)$openclass['price']; ?> Kč</span>
            </div>
        </div>
        <div class="secure-note">
            Rychlá registrace · Můžeš zrušit
        </div>
        <?php
        $recentStmt = $mysqli->prepare("
            SELECT COUNT(*)
            FROM openclass_registrations
            WHERE openclass_id = ?
            AND storno != 1
            AND created_at >= NOW() - INTERVAL 24 HOUR
        ");
        $recentStmt->bind_param('s', $openclassId);
        $recentStmt->execute();
        $recentStmt->bind_result($last24h);
        $recentStmt->fetch();
        $recentStmt->close();

        if ($last24h >= 2): ?>
            <div class="momentum">
                🔥 <?= $last24h ?> lidí se přihlásilo dnes
            </div>
        <?php endif; ?>
    </div>

    <div class="creator-card">

        <div class="creator-left">
            <img src="<?php echo htmlspecialchars($creatorPfp); ?>" style="border-radius: 50%;" class="creator-avatar">

            <div class="creator-info">
                <div class="creator-top">
                    <span class="creator-name" onclick="window.location.href='view-profile.php?public_id=<?= htmlspecialchars($openclass['creator_public_id'], ENT_QUOTES, 'UTF-8') ?>'">
                        <?= htmlspecialchars($creatorName) ?>
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

                <span class="creator-followers">
                    <?= number_format($creatorFollowers, 0, ',', ' ') ?> Sledujicích
                </span>
            </div>
        </div>

        <!-- <div class="creator-rating" style="visibility: hidden;">
            <div class="stars" style="visibility: hidden;">
                ★ ★ ★ ★ ☆
            </div>
            <span class="rating-number" style="visibility: hidden;">4.6</span>
        </div>
    </div> -->
</div>

<?php if (!empty($openclass['latitude']) && !empty($openclass['longitude'])): ?>
    <div id="map"></div>
<?php endif; ?>

<div id="resultPopup" class="popup-overlay" style="display:none;">
    <div class="popup-box danger">

        <div class="popup-header">
            <div class="popup-title">Zrušit registraci?</div>
            <div class="popup-subtitle">
                Toto rozhodnutí má trvalé následky
            </div>
        </div>

        <div class="popup-section">
            <ul class="popup-consequences">
                <li style="color: #ff5778; padding-bottom: 5px;">
                    Po zrušení se <strong style="color: #ff5778;">nebudeš</strong> moci znovu přihlásit
                </li>
                <li>
                    Tvůj status <strong>spolehlivého</strong> tanečníka se sníží.
                </li>
                <li>
                    Tvé místo se <strong>okamžitě uvolní</strong> jinému tanečníkovi
                </li>
            </ul>
        </div>

        <div class="popup-note">
            Používej pouze v případě <strong>nouzové situace</strong>.
        </div>

        <div class="popup-actions">
            <button id="cancelConfirm" class="popup-btn secondary">
                Zrušit registraci
            </button>
            <button id="cancelAbort" class="popup-btn danger">
                Zůstat přihlášen
            </button>
        </div>

    </div>
</div>

<div id="shareSheet" class="share-sheet hidden">
    <div class="share-backdrop"></div>

    <div class="share-panel">
        <div class="share-handle"></div>

        <div class="share-title">Sdílet openclass</div>

        <div class="share-text">
            Pošli ostatním odkaz, ať se taky zaregistrují!
        </div>

        <div class="share-link-row">
            <input id="shareUrl" readonly>
            <button id="copyBtn">Kopírovat</button>
        </div>

        <div class="share-actions">
            <button id="waShare">
                <img src="/source/assets/whatsapp.png">
                <span>WhatsApp</span>
            </button>
        </div>
    </div>
</div>

<div id="infoPopup" class="popup-overlay" style="display:none;">
    <div class="popup-box">

        <div class="popup-header">
            <div class="popup-title" id="infoTitle">Upozornění</div>
            <div class="popup-subtitle" id="infoText"></div>
        </div>

        <div class="popup-actions">
            <button id="infoClose" class="popup-btn secondary">
                Rozumím
            </button>
        </div>

    </div>
</div>

<?php
    $shareUrl = 'https://dancefy.cz/openclass.php?id=' . urlencode($openclassId);
?>

<?php if (!empty($qrFile)): ?>
<div id="qrPopup" class="popup-overlay" style="display:none;">
    <div class="popup-box">
        <div class="popup-header">
            <div class="popup-title">Potvrzení registrace</div>
            <div class="popup-subtitle">
                Tvůrce zvolil platbu předem. Pro potvrzení své registrace naskenuj QR kód ve své bankovní aplikaci a dokonči platbu.
            </div>
        </div>

        <div style="text-align:center; padding:20px;">
            <img src="/<?= $qrFile ?>?t=<?= time() ?>" style="width:220px;">
        </div>
        <div class="popup-legal">
            Platba je odesílána přímo na bankovní účet tvůrce. Dancefy nevystupuje jako poskytovatel platebních služeb, nepřijímá ani nezpracovává finanční prostředky a nenese odpovědnost za platební vztah mezi uživateli. Odesláním platby souhlasíte s <a href="payment-terms.php">Platebními podmínkami</a>.
        </div>
        <div class="popup-actions">
            <button onclick="document.getElementById('qrPopup').style.display='none';" class="popup-btn secondary">
                Zavřít
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    const btn = document.getElementById('registerBtn');
    const popup = document.getElementById('resultPopup');
    const confirmBtn = document.getElementById('cancelConfirm');
    const abortBtn = document.getElementById('cancelAbort');

    if (btn && !btn.disabled) {
        btn.addEventListener('click', async () => {
            const isRegistered = <?php echo $isRegistered ? 'true' : 'false'; ?>;

            if (!isRegistered) {
        const res = await fetch('/register_openclass.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'openclass_id=<?php echo htmlspecialchars($openclassId); ?>'
        });

        const data = await res.json();

        if (data.status === 'error') {
            window.location.href =
                `/openclass-listing.php?id=<?php echo htmlspecialchars($openclassId); ?>&error=${encodeURIComponent(data.code)}`;
            return;
        }

        window.location.href =
            `/openclass-listing.php?id=<?php echo htmlspecialchars($openclassId); ?>&success=registered`;
        return;
    }


        popup.style.display = 'flex';
    });
}

confirmBtn.onclick = async () => {
    const res = await fetch('/unregister_openclass.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'openclass_id=<?php echo htmlspecialchars($openclassId); ?>'
    });

    if (!res.ok) {
        alert(await res.text());
        return;
    }

    window.location.reload();
};

abortBtn.onclick = () => {
    popup.style.display = 'none';
};
</script>


<script>
const shareBtn = document.querySelector('.promote-btn');
const sheet = document.getElementById('shareSheet');
const backdrop = document.querySelector('.share-backdrop');
const urlInput = document.getElementById('shareUrl');
const copyBtn = document.getElementById('copyBtn');

const message = 'Zaregistruj se na tuto openclass v Dancefy 👇';
const url = <?php echo json_encode($shareUrl); ?>;
const fullText = `${message}\n${url}`;

shareBtn.onclick = () => {
    urlInput.value = url;
    sheet.classList.remove('hidden');
};

backdrop.onclick = () => {
    sheet.classList.add('hidden');
};

copyBtn.onclick = () => {
    navigator.clipboard.writeText(fullText);
    copyBtn.textContent = 'Zkopírováno';
    setTimeout(() => copyBtn.textContent = 'Kopírovat', 1200);
};

document.getElementById('waShare').onclick = () => {
    const waUrl = `whatsapp://send?text=${encodeURIComponent(fullText)}`;
    const waFallback = `https://wa.me/?text=${encodeURIComponent(fullText)}`;
    openApp(waUrl, waFallback);
};


function openApp(deepLink, fallback) {
    const start = Date.now();
    window.location.href = deepLink;

    setTimeout(() => {
        if (Date.now() - start < 1500) {
            window.open(fallback, '_blank');
        }
    }, 800);
}
</script>

<script>
const error = <?php echo json_encode($error ?? null); ?>;
const success = <?php echo json_encode($success ?? null); ?>;

const infoPopup = document.getElementById('infoPopup');
const infoTitle = document.getElementById('infoTitle');
const infoText = document.getElementById('infoText');
const infoClose = document.getElementById('infoClose');

function showInfo(title, text) {
    if (!infoPopup) return;

    infoTitle.textContent = title;
    infoText.textContent = text;
    infoPopup.style.display = 'flex';

    const url = new URL(window.location.href);
    url.searchParams.delete('error');
    url.searchParams.delete('success');
    window.history.replaceState({}, document.title, url.toString());
}

if (typeof error === 'string') {
    switch (error) {
        case 'missing_email':
            showInfo(
                'Přidej a ověřte email',
                'Pro registraci na openclass si musíš přidat email v nastavení pod svůj profil a ověřit ho pomoicí odkazu. Ujisti se, že je to tvůj správný email.'
            );
            break;

        case 'already_registered':
            showInfo(
                'Už jsi přihlášen',
                'Na tuto openclass už máš aktivní registraci.'
            );
            break;

        case 'registration_failed':
            showInfo(
                'Chyba registrace',
                'Registraci se nepodařilo dokončit. Zkus to prosím znovu.'
            );
            break;
    }
}

if (success === 'registered') {
    showInfo(
        'Hotovo',
        'Registrace proběhla úspěšně. Potvrzení jsme poslali na email.'
    );
}

infoClose?.addEventListener('click', () => {
    infoPopup.style.display = 'none';
});
</script>

<?php if (!empty($qrFile)): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const qrPopup = document.getElementById("qrPopup");
    if (qrPopup) {
        qrPopup.style.display = "flex";
    }
});
</script>
<?php endif; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const lat = <?= $openclass['latitude'] ?? 0 ?>;
    const lng = <?= $openclass['longitude'] ?? 0 ?>;

    if (lat !== 0 && lng !== 0) {
        const map = L.map('map', {
            center: [lat, lng],
            zoom: 15,
            zoomControl: false,
            boxZoom: false,
            attributionControl: false,
            doubleClickZoom: false,
            boxZoom: false
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 20
        }).addTo(map);

        const dancefyIcon = L.divIcon({
            className: 'custom-pin', 
            html: '<div class="pin-main"></div>',
            iconSize: [24, 24],
            iconAnchor: [12, 24] 
        });

        L.marker([lat, lng], { icon: dancefyIcon }).addTo(map);
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const triggerAnim = <?php echo json_encode($triggerAnimation); ?>;
    
    if (triggerAnim === "1") {
        confetti({
            particleCount: 150,
            spread: 70,
            origin: { y: 0.6 },
            colors: ['#FF5143', '#FF1B73', '#ffffff']
        });

        var duration = 3 * 1000;
        var end = Date.now() + duration;

        (function frame() {
            confetti({
                particleCount: 3,
                angle: 60,
                spread: 55,
                origin: { x: 0 },
                colors: ['#FF5143', '#FF1B73'],
                zIndex: 10000
            });
            confetti({
                particleCount: 3,
                angle: 120,
                spread: 55,
                origin: { x: 1 },
                colors: ['#FF5143', '#FF1B73'],
                zIndex: 10000
            });

            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        }());

        const url = new URL(window.location.href);
        url.searchParams.delete('animation');
        window.history.replaceState({}, document.title, url.toString());
    }
});

document.addEventListener("DOMContentLoaded", function() {
    const triggerAnim = <?php echo json_encode($triggerAnimation); ?>;
    
    if (triggerAnim === "1") {
        const overlay = document.getElementById('publishOverlay');
        overlay.style.display = 'flex';

        setTimeout(() => {
            overlay.classList.add('fade-out');
            setTimeout(() => { overlay.style.display = 'none'; }, 500);
        }, 2500);

        confetti({
            particleCount: 150,
            spread: 70,
            origin: { y: 0.6 },
            colors: ['#FF5143', '#FF1B73', '#ffffff']
        });


    }
});
</script>

</body>
</html>
