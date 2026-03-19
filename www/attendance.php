<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
date_default_timezone_set('Europe/Prague');

require_once __DIR__ . '/secure/auth.php';
require_once __DIR__ . '/secure/require_creator.php';

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

function formatStat($value) {
    return ($value == 0 || $value === '0') ? '&mdash;' : $value;
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
    SELECT title, capacity, price, description, cover_image
    FROM openclasses 
    WHERE openclass_id = ? 
    LIMIT 1
");
$oc->bind_param('s', $openclassId);
$oc->execute();
$oc->bind_result($oc_title, $oc_capacity, $oc_price, $oc_description, $cover_image);

if (!$oc->fetch()) {
    http_response_code(404);
    exit('OpenClass not found');
}
$oc->close();

$viewsStmt = $mysqli->prepare("
    SELECT COUNT(*) 
    FROM openclass_views
    WHERE openclass_id = ?
");
$viewsStmt->bind_param('s', $openclassId);
$viewsStmt->execute();
$viewsStmt->bind_result($totalViews);
$viewsStmt->fetch();
$viewsStmt->close();

$reporterPublicId = null;

if (!empty($_COOKIE['dancefy_token'])) {
    $tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

    $u = $mysqli->prepare("
        SELECT u.public_id
        FROM user_tokens t
        JOIN users u ON u.user_id = t.user_id
        WHERE t.token_hash = ?
        LIMIT 1
    ");
    $u->bind_param('s', $tokenHash);
    $u->execute();
    $u->bind_result($reporterPublicId);
    $u->fetch();
    $u->close();
}

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
$paidDancefyCount = 0;

$r = $mysqli->prepare("
    SELECT
        u.username,
        up.pfp_path,
        up.reputation,
        u.public_id,
        r.created_at,
        r.is_paid
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
    $created_at,
    $is_paid
);

while ($r->fetch()) {
    $rep = calculateReputation($dbReputation);
    $paidStatus = (int)$is_paid;
    
    if ($paidStatus === 1) {
        $paidDancefyCount++;
    }

    $regs[] = [
        'username'   => $username,
        'pfp'        => $pfp_path ?: 'pfp.png',
        'public_id'  => $public_id,
        'joined'     => $created_at,
        'reputation' => $rep,
        'rep_class'  => reputationClass($rep),
        'is_paid'    => $paidStatus
    ];
}

$r->close();

usort($regs, function ($a, $b) {
    if ($a['is_paid'] !== $b['is_paid']) {
        return $b['is_paid'] <=> $a['is_paid'];
    }
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
$currentProfit      = $paidDancefyCount * $oc_price;

$attendanceUrl = 'https://www.dancefy.cz/attendance-view.php?id=' . urlencode($openclassId);
$shareUrl = 'https://dancefy.cz/openclass.php?id=' . urlencode($openclassId);

$capacityPercent = 0;
if ($oc_capacity > 0) {
    $capacityPercent = min(100, round(($paidDancefyCount / $oc_capacity) * 100));
}

$unpaidCount = count($regs) - $paidDancefyCount;

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenClass Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="attendance.css?version=6">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>

<nav class="nav">
    <a href="app.php" class="nav-btn nav-left" aria-label="Back">
        <svg viewBox="0 0 24 24">
            <path d="M15 18l-6-6 6-6" />
        </svg>
    </a>

    <h1 class="nav-title">OpenClass | <?= htmlspecialchars($oc_title) ?></h1>

    <button class="nav-btn nav-right" aria-label="More">
        <svg viewBox="0 0 24 24">
            <circle cx="5" cy="12" r="1"/>
            <circle cx="12" cy="12" r="1"/>
            <circle cx="19" cy="12" r="1"/>
        </svg>
    </button>
</nav>

<?php if ($totalRegistrations === 0): ?>
    <div class="gradient">
        <div class="hint2">
            <p>Tvoje openclass je <strong>ZVEŘEJNĚNA</strong> 🚀</p>
        </div>

        <div class="oc-preview-card">
            <div class="oc-card-content">
                <h2 class="oc-card-title"><?= htmlspecialchars($oc_title) ?></h2>
                <p class="oc-card-desc">
                    <?= !empty($oc_description) ? htmlspecialchars(mb_strimwidth($oc_description, 0, 120, "...")) : "Popis se nenačetl..." ?>
                </p>
            </div>
            <div class="oc-card-image-placeholder">
                <img src="uploads/openclasses/<?= htmlspecialchars($cover_image)?>" alt="">
            </div>
        </div>

        <div class="zero-state">
            <div class="zero-icon">🚀</div>
            <h2>Rozjeď registrace</h2>
            <span>Sdílej do stories a přiveď první tanečníky.</span>
            <button class="zero-cta" id="shareZeroBtn">Sdílet OpenClass</button>
            <p>Tip: Sdílení hned po vytvoření má nejvíce registrací.</p>
        </div>
    </div>
<?php else: ?>

    <?php if ($totalRegistrations > 10): ?>
        <div class="hint"><p>Tvoje Openclass je TRENDING 🚀</p></div>
    <?php elseif ($totalRegistrations > 5): ?>
        <div class="hint"><p>Už se to začíná plnit 🚀</p></div>
    <?php else: ?>
        <div class="hint"><p>První registrace přišly 🚀 <br> Využij Instagram a naplň lekci rychleji 🔥</p></div>
    <?php endif; ?>

    <?php if ($oc_capacity > 0): ?>
        <div class="capacity-container">
            <div class="capacity-top">
                <span class="capacity-label">Placené registrace</span>
                <span class="capacity-count">
                    <strong><?= $paidDancefyCount == 0 ? '—' : $paidDancefyCount ?></strong> / <?= $oc_capacity ?>
                </span>
            </div>
            <div class="progress-bg">
                <div class="progress-fill <?= ($capacityPercent >= 100) ? 'full' : '' ?>" 
                    style="width: <?= $capacityPercent ?>%;">
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat">
            <span><?= $paidDancefyCount == 0 ? '—' : $paidDancefyCount ?></span>
            <p>Zaplaceno</p>
        </div>

        <div class="stat wide">
            <span><?= $currentProfit == 0 ? '—' : number_format($currentProfit, 0, ',', ' ') . ' Kč' ?></span>
            <p>Potvrzené tržby</p>
        </div>

        <div class="stat">
            <span><?= $totalViews == 0 ? '—' : $totalViews ?></span>
            <p>Zobrazení</p>
        </div>
    </div>

    <div class="sub-stats">
        <div class="sub-stat">
            <span><?= $totalRegistrations == 0 ? '—' : $totalRegistrations ?></span>
            <p>Celkem</p>
        </div>

        <div class="sub-stat">
            <span><?= count($regs) == 0 ? '—' : count($regs) ?></span>
            <p>Dancefy</p>
        </div>

        <div class="sub-stat">
            <span><?= count($webRegs) == 0 ? '—' : count($webRegs) ?></span>
            <p>Web</p>
        </div>
    </div>
    
    <?php 
        $unpaidCount = count($regs) - $paidDancefyCount; 
        
        if ($unpaidCount > 0): ?>
            <div class="unpaid-alert">
                <div class="content">
                    <h4>⏳ <?= $unpaidCount ?> <?= ($unpaidCount === 1 ? ' platba čeká' : ($unpaidCount < 5 ? ' platby čekají' : ' plateb čeká')) ?></h4>
                    <p>Někteří tanečníci ještě nezaplatili. Označ je jako zaplacené.</p>
                </div>
            </div>
    <?php endif; ?>

<?php endif; ?>



<?php if ($totalRegistrations > 0): ?>

<div class="registrations">
    <h1>Registrace</h1>

    <p>Dancefy Uživatelé <img src="source/assets/info.png" class="info-trigger" data-info-key="dancefy_users"></p>
    <?php if (!empty($regs)): ?>
        <div class="dancefy-users">
            <?php foreach ($regs as $user): ?>
                <div class="row-dancefy row-link <?= (int)$user['is_paid'] === 0 ? 'unpaid' : '' ?>" onclick="window.location.href='/view-profile.php?public_id=<?= $user['public_id'] ?>&from=attendance&id=<?= urlencode($openclassId) ?>'">
                    
                    <img class="pfp" src="<?= htmlspecialchars($user['pfp']) ?>">
                    <span><?= htmlspecialchars($user['username']) ?></span>
                    
                    <div class="paid-status-container" id="paid-status-<?= $user['public_id'] ?>" onclick="event.stopPropagation(); togglePayment('<?= $user['public_id'] ?>', this)">
                        <?php if ((int)$user['is_paid'] === 1): ?>
                            <span class="is-paid" data-paid="1">Placeno</span>
                        <?php else: ?>
                            <span class="is-unpaid" data-paid="0">Nezaplaceno</span>
                        <?php endif; ?>
                    </div>

                    <a class="chat" href="../messages/chat.php?public_id=<?= htmlspecialchars($user['public_id']) ?>" onclick="event.stopPropagation()">Napsat</a>
                    
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-placeholder">
            Žádní Dancefy uživatelé zatím
        </div>
    <?php endif; ?>

    <?php if (!empty($webRegs)): ?>
        <p>Hosté (<?= count($webRegs) ?>) <img src="source/assets/info.png" class="info-trigger" data-info-key="guests"></p>
        <div class="users">
            <?php foreach ($webRegs as $guest): ?>
                <div class="row">
                    <div class="circle"><?= htmlspecialchars($guest['initials']) ?></div>
                    <div class="field">
                        <span><?= htmlspecialchars($guest['name']) ?></span>
                        <p class="wmail"><?= htmlspecialchars($guest['email']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

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
</div><br><br><br><br>

<div class="share">
    <a class="main" id="shareBtn">Sdílet OpenClass</a>
</div>

<?php endif; ?>

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
        <div class="share-text">Sdílej odkaz tanečníkům, ať se zaregistrují!</div>
        <div class="share-link-row">
            <input id="shareUrl" readonly value="<?= $shareUrl ?>">
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
        <div class="share-text">Možnosti k OpenClass</div>
        <button class="oc-btn secondary" onclick="window.location.href='openclass-edit.php?id=<?= urlencode($openclassId) ?>'">Editovat</button>
        <button class="oc-btn danger" onclick="window.location.href='cancel.php?id=<?= urlencode($openclassId) ?>'">Zastavit OpenClass</button>
        <p class="oc-warning">Všechny akce jsou nevratné.</p>
    </div>
</div>

<div id="userMenu" class="user-menu hidden" onclick="event.stopPropagation()">
    <img src="source/assets/paid.png">
    <button id="reportUserBtn"></button>
</div>

<script>
const INFO_CONTENT = {
    dancefy_users: { title: 'Dancefy uživatelé', text: 'Tanečníci, kteří se zaregistrovali v aplikaci. Mají reputaci a portfolio.' },
    guests: { title: 'Hosté', text: 'Registrovaní uživatelé přes web. Nemají reputaci ani historii.' },
    cancelled: { title: 'Zrušené registrace', text: 'Uživatelé, kteří registraci zrušili.' }
};

const modal = document.getElementById('info-modal');
const backdrop = document.getElementById('info-modal-backdrop');
const titleEl = document.getElementById('info-modal-title');
const textEl = document.getElementById('info-modal-text');

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

const closeModal = () => { modal.classList.remove('active'); backdrop.classList.remove('active'); };
document.getElementById('info-modal-close').onclick = closeModal;
backdrop.onclick = closeModal;

const shareBtn = document.getElementById('shareBtn');
const sheet = document.getElementById('shareSheet');
const copyBtn = document.getElementById('copyBtn');
const shareUrl = <?= json_encode($shareUrl) ?>;

if(shareBtn) {
    shareBtn.onclick = () => sheet.classList.remove('hidden');
}
document.querySelector('.share-backdrop').onclick = () => sheet.classList.add('hidden');

copyBtn.onclick = () => {
    navigator.clipboard.writeText(shareUrl);
    copyBtn.textContent = 'Zkopírováno';
    setTimeout(() => copyBtn.textContent = 'Kopírovat', 1200);
};

document.getElementById('waShare').onclick = () => {
    window.location.href = `whatsapp://send?text=${encodeURIComponent(shareUrl)}`;
};

document.getElementById('dancefyShare').onclick = () => {
    window.location.href = `/post/tweet.php?text=${encodeURIComponent('Nová openclass je venku!')}`;
};
const navMoreBtn = document.querySelector('.nav-right');
const ocSheet = document.getElementById('ocActionSheet');
navMoreBtn.onclick = () => ocSheet.classList.remove('hidden');
document.querySelector('.oc-backdrop').onclick = () => {
    ocSheet.classList.add('closing');
    setTimeout(() => {
        ocSheet.classList.add('hidden');
        ocSheet.classList.remove('closing');
    }, 200);
};

async function togglePayment(publicId, container) {
    const span = container.querySelector('span');
    const currentStatus = parseInt(span.getAttribute('data-paid'));
    const newStatus = currentStatus === 1 ? 0 : 1;

    container.style.opacity = '0.5';

    try {
        const response = await fetch('toggle_paid.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                public_id: publicId,
                openclass_id: <?= json_encode($openclassId) ?>,
                status: newStatus
            })
        });

        if (response.ok) {
            if (newStatus === 1) {
                container.innerHTML = `<span class="is-paid" data-paid="1">Placeno</span>`;
                container.closest('.row-dancefy').classList.remove('unpaid');
            } else {
                container.innerHTML = `<span class="is-unpaid" data-paid="0">Nezaplaceno</span>`;
                container.closest('.row-dancefy').classList.add('unpaid');
            }
        }
    } catch (err) {
        console.error("Update failed", err);
    } finally {
        container.style.opacity = '1';
    }
}

document.addEventListener('click', () => userMenu.classList.add('hidden'));

const shareZeroBtn = document.getElementById('shareZeroBtn');
if (shareZeroBtn) {
    shareZeroBtn.onclick = () => {
        sheet.classList.remove('hidden');
    };
}
</script>

</body>
</html>