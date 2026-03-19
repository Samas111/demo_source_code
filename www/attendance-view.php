<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
date_default_timezone_set('Europe/Prague');

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    exit;
}
$mysqli->set_charset('utf8mb4');

function timeAgoCz(string $datetime): string
{
    $ts = strtotime($datetime);
    if (!$ts) return '';

    $diff = time() - $ts;
    if ($diff < 60) return 'Před chvílí';
    if ($diff < 3600) return 'Před ' . floor($diff / 60) . ' minutami';
    if ($diff < 86400) return 'Před ' . floor($diff / 3600) . ' hodinami';
    return 'Před ' . floor($diff / 86400) . ' dny';
}

function reputationClass(int $rep): string
{
    if ($rep > 80) return 'green';
    if ($rep > 60) return 'yellow';
    return 'red';
}

function calculateReputation(?string $log): int
{
    if (!$log) return 80;

    $parts = array_filter(explode(',', $log), 'strlen');
    if (count($parts) === 0) return 100;

    $values = [];

    foreach ($parts as $p) {
        if (!preg_match('/^[+-]?\d+$/', trim($p))) {
            continue;
        }
        $values[] = (int)$p;
    }

    if (count($values) === 0) return 100;

    $avg = array_sum($values) / count($values);

    return max(0, min(100, (int)round($avg)));
}


function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    if (!$parts || count($parts) === 0) return '??';
    if (count($parts) === 1) return mb_strtoupper(mb_substr($parts[0], 0, 2));
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
}

$openclassId = $_GET['id'] ?? null;
if (!$openclassId) {
    http_response_code(404);
    exit('Missing OpenClass ID');
}

$oc = $mysqli->prepare("
    SELECT title, capacity, price
    FROM openclasses
    WHERE openclass_id = ?
    LIMIT 1
");
$oc->bind_param('s', $openclassId);
$oc->execute();
$oc->bind_result($oc_title, $oc_capacity, $oc_price);

if (!$oc->fetch()) {
    http_response_code(404);
    exit('OpenClass not found');
}
$oc->close();


$reporterPublicId = null;

$reportedMap = [];

if ($reporterPublicId) {
    $q = $mysqli->prepare("
        SELECT reported_public_id
        FROM user_reports
        WHERE reporter_public_id = ?
          AND openclass_id = ?
    ");
    $q->bind_param('ss', $reporterPublicId, $openclassId);
    $q->execute();
    $q->bind_result($reportedPid);

    while ($q->fetch()) {
        $reportedMap[$reportedPid] = true;
    }

    $q->close();
}

$regs = [];

$r = $mysqli->prepare("
    SELECT
        u.username,
        up.pfp_path,
        up.reputation,
        u.public_id,
        r.created_at
    FROM openclass_registrations r
    JOIN users u ON u.public_id = r.public_id
    LEFT JOIN user_profile up ON up.public_id = u.public_id
    WHERE r.openclass_id = ?
      AND r.storno = 0
    ORDER BY r.created_at ASC
");
$r->bind_param('s', $openclassId);
$r->execute();
$r->bind_result(
    $username,
    $pfp_path,
    $dbReputation,
    $public_id,
    $created_at
);

while ($r->fetch()) {

    $rep = calculateReputation($dbReputation);

    $regs[] = [
        'username'   => $username,
        'pfp'        => $pfp_path ?: 'pfp.png',
        'public_id'  => $public_id,
        'joined'     => $created_at,
        'reputation' => $rep,
        'rep_class'  => reputationClass($rep)
    ];
}

$r->close();

usort($regs, function ($a, $b) {
    return $b['reputation'] <=> $a['reputation'];
});

$webRegs = [];

$wr = $mysqli->prepare("
    SELECT
        name,
        email,
        created_at
    FROM openclass_registrations_web
    WHERE openclass_id = ?
      AND verified = 1
    ORDER BY created_at ASC
");
$wr->bind_param('s', $openclassId);
$wr->execute();
$wr->bind_result($w_name, $w_email, $w_created);

while ($wr->fetch()) {
    $webRegs[] = [
        'name'     => $w_name,
        'email'    => $w_email,
        'initials' => initials($w_name),
        'joined'   => $w_created
    ];
}
$wr->close();

$cancelled = [];

$rCancelled = $mysqli->prepare("
    SELECT
        u.username,
        r.canceled_at
    FROM openclass_registrations r
    JOIN users u ON u.public_id = r.public_id
    WHERE r.openclass_id = ?
      AND r.storno = 1
    ORDER BY r.canceled_at DESC
");
$rCancelled->bind_param('s', $openclassId);
$rCancelled->execute();
$rCancelled->bind_result($c_username, $canceled_at);

while ($rCancelled->fetch()) {
    $cancelled[] = [
        'username'    => $c_username,
        'canceled_at' => $canceled_at
    ];
}
$rCancelled->close();

$totalRegistrations = count($regs) + count($webRegs);
$totalCancelled     = count($cancelled);
$totalRevenue       = $totalRegistrations * $oc_price;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenClass Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="attendance.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>

<nav class="nav">
    <a style="visibility: hidden;" class="nav-btn nav-left" aria-label="Back">
        <svg viewBox="0 0 24 24">
            <path d="M15 18l-6-6 6-6" />
        </svg>
    </a>

    <h1 class="nav-title">View Only | <?= htmlspecialchars($oc_title) ?></h1>

    <button style="visibility: hidden;" class="nav-btn nav-right">
        <svg viewBox="0 0 24 24">
            <circle cx="5" cy="12" r="1"/>
            <circle cx="12" cy="12" r="1"/>
            <circle cx="19" cy="12" r="1"/>
        </svg>
    </button>
</nav>
<div class="registrations">
    <h1>Registrace (<?= $totalRegistrations ?>)</h1>

    <p>Dancefy Uživatelé <img src="source/assets/info.png" class="info-trigger" data-info-key="dancefy_users"></p>
    <div class="dancefy-users">
        <?php if (count($regs) === 0): ?>
            <div class="row-dancefy"><span>Zatím žádné registrace</span></div>
        <?php else: ?>
            <?php foreach ($regs as $user): ?>
                <div class="row-dancefy row-link"> <!-- onclick="window.location.href='/view-profile.php?public_id=<?= $user['public_id'] ?>&from=attendance&id=<?= urlencode($openclassId) ?>'" -->
                    <img class="pfp" src="<?= htmlspecialchars($user['pfp']) ?>">
                    <span><?= htmlspecialchars($user['username']) ?></span>
                    <div class="reputation <?= $user['rep_class'] ?>">
                        • <?= $user['reputation'] ?>%
                    </div>
                    <?php $isReported = isset($reportedMap[$user['public_id']]); ?>
                    <div class="menu" onclick="event.stopPropagation()">
                        <img class="dots" src="source/assets/dots.png" data-public-id="<?= htmlspecialchars($user['public_id']) ?>" data-reported="<?= $isReported ? '1' : '0' ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <p>Hosté (<?= count($webRegs) ?>) <img src="source/assets/info.png" class="info-trigger" data-info-key="guests"></p>
    <div class="users">
        <?php if (count($webRegs) === 0): ?>
            <div class="row"><span>Zatím žádní hosté</span></div>
        <?php else: ?>
            <?php foreach ($webRegs as $guest): ?>
                <div class="row">
                    <div class="circle"><?= htmlspecialchars($guest['initials']) ?></div>
                    <span><?= htmlspecialchars($guest['name']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($totalCancelled > 0): ?>
        <p>Zrušeno <img src="source/assets/info.png" class="info-trigger" data-info-key="cancelled"></p>
        <div class="users-storno">
            <?php foreach ($cancelled as $user): ?>
                <div class="row-storno">
                    <div class="circle"><?= mb_strtoupper(mb_substr($user['username'], 0, 2)) ?></div>
                    <span><?= htmlspecialchars($user['username']) ?></span>
                    <div class="badge"><?= timeAgoCz($user['canceled_at']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="share">
    <a class="main" id="shareBtn">Sdílet OpenClass</a>
</div>

<div id="info-modal-backdrop"></div>
<div id="info-modal">
    <div class="info-modal-header">
        <h2 id="info-modal-title"></h2>
        <button id="info-modal-close">×</button>
    </div>
    <div class="info-modal-body" id="info-modal-text"></div>
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
            <button id="dancefyShare">
                <img src="/source/assets/logo.png">
                <span>Dancefy</span>
            </button>

            <button id="waShare">
                <img src="/source/assets/whatsapp.png">
                <span>WhatsApp</span>
            </button>
        </div>

    </div>
</div>

<div id="ocActionSheet" class="oc-sheet hidden">
    <div class="oc-backdrop"></div>

    <div class="oc-panel">
        <div class="oc-handle"></div>

        <button style="display: none;"
            class="oc-btn secondary"
            onclick="window.location.href='apply.php?id=<?= urlencode($openclassId) ?>'">
            Požádat o změnu
        </button>

        <button
            class="oc-btn danger"
            onclick="window.location.href='cancel.php?id=<?= urlencode($openclassId) ?>'">
            Zrušit OpenClass
        </button>


        <p class="oc-warning">
            Všechny akce, které uděláte, jsou nevratné.
        </p>
    </div>
</div>

<div id="userMenu" class="user-menu hidden" onclick="event.stopPropagation()">
    <img src="source/assets/flag.png">
    <button id="reportUserBtn"></button>
</div>

<?php
    $shareUrl = 'https://dancefy.cz/openclass.php?id=' . urlencode($openclassId);
?>

<script>
const INFO_CONTENT = {
    dancefy_users: {
        title: 'Dancefy uživatelé',
        text: 'Procento vyjadřuje spolehlivost účasti na základě minulých OpenClasses. 80 % znamená nový nebo zatím nevyhodnocený účet. Účast reputaci zvyšuje, pozdní storna nebo neúčast ji snižují.'
    },
    guests: {
        title: 'Hosté',
        text: 'Registrovaní uživatelé přes web s ověřeným e-mailem. Nemají reputaci ani historii.'
    },
    cancelled: {
        title: 'Zrušené registrace',
        text: 'Uživatelé, kteří registraci zrušili. Storno má negativní vliv na reputaci tanečníka.'
    }
};

const modal = document.getElementById('info-modal');
const backdrop = document.getElementById('info-modal-backdrop');
const titleEl = document.getElementById('info-modal-title');
const textEl = document.getElementById('info-modal-text');
const closeBtn = document.getElementById('info-modal-close');

document.querySelectorAll('.info-trigger').forEach(el => {
    el.addEventListener('click', e => {
        const content = INFO_CONTENT[el.dataset.infoKey];
        if (!content) return;
        titleEl.textContent = content.title;
        textEl.textContent = content.text;
        modal.classList.add('active');
        backdrop.classList.add('active');
    });
});

function closeModal() {
    modal.classList.remove('active');
    backdrop.classList.remove('active');
}

closeBtn.onclick = closeModal;
backdrop.onclick = closeModal;
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>

<script>
const shareBtn = document.getElementById('shareBtn');
const sheet = document.getElementById('shareSheet');
const shareBackdrop = document.querySelector('.share-backdrop');
const urlInput = document.getElementById('shareUrl');
const copyBtn = document.getElementById('copyBtn');

const message = 'Zaregistruj se na tuto openclass v Dancefy 👇';
const url = <?php echo json_encode($shareUrl); ?>;
const fullText = `${message}\n${url}`;

shareBtn.onclick = () => {
    urlInput.value = url;
    sheet.classList.remove('hidden');
};

shareBackdrop.onclick = () => {
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

document.getElementById('dancefyShare').onclick = () => {
    const params = new URLSearchParams({
        text: 'Nová openclass je venku. Registrace běží.'
    });

    window.location.href = `/post/tweet.php?${params.toString()}`;
};


</script>
<script>
const navMoreBtn = document.querySelector('.nav-right');
const ocSheet = document.getElementById('ocActionSheet');
const ocBackdrop = ocSheet.querySelector('.oc-backdrop');

navMoreBtn.onclick = () => {
    ocSheet.classList.remove('hidden');
};

ocBackdrop.onclick = () => {
    ocSheet.classList.add('closing');

    setTimeout(() => {
        ocSheet.classList.add('hidden');
        ocSheet.classList.remove('closing');
    }, 200);
};
</script>

<script>
const userMenu = document.getElementById('userMenu');
const reportBtn = document.getElementById('reportUserBtn');

let selectedPublicId = null;
let alreadyReported = false;

document.querySelectorAll('.dots').forEach(dot => {
    dot.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();

        selectedPublicId = dot.dataset.publicId;
        alreadyReported = dot.dataset.reported === '1';

        reportBtn.textContent = alreadyReported ? 'Nahlášeno' : 'Nahlásit';
        reportBtn.disabled = alreadyReported;

        const rect = dot.getBoundingClientRect();
        userMenu.style.top = rect.bottom + window.scrollY + 'px';
        userMenu.style.left = rect.left + window.scrollX - 120 + 'px';

        userMenu.classList.remove('hidden');
    });
});

reportBtn.onclick = async () => {
    if (alreadyReported || !selectedPublicId) return;

    await fetch('/report_user.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            public_id: selectedPublicId,
            openclass_id: <?= json_encode($openclassId) ?>
        })
    });

    reportBtn.textContent = 'Nahlášeno';
    reportBtn.disabled = true;

    document
        .querySelector(`.dots[data-public-id="${selectedPublicId}"]`)
        .dataset.reported = '1';

    alreadyReported = true;
};
document.addEventListener('click', () => {
    userMenu.classList.add('hidden');
    selectedPublicId = null;
});
</script>

</body>
</html>
