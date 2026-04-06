<?php
require __DIR__ . '/../secure/logic.php';

$me = $_SESSION['public_id'] ?? null;
if (!$me) { http_response_code(401); exit; }

$with = $_GET['public_id'] ?? '';
if ($with === '' || $with === $me) { http_response_code(400); exit; }

// 1. Profile & Account Data
$profileStmt = $conn->prepare("
    SELECT u.username, up.name, up.pfp_path
    FROM users u
    LEFT JOIN user_profile up ON u.public_id = up.public_id
    WHERE u.public_id = ? LIMIT 1
");
$profileStmt->bind_param('s', $with);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc();

$username = $profile['username'] ?? $with;
$dancerName = $profile['name'] ?? $username;
$pfpPath = (!empty($profile['pfp_path'])) ? '../' . $profile['pfp_path'] : '../uploads/profile-pictures/default.png';
$profileStmt->close();

$timeline = [];
$lastId = 0;

// 2. Fetch Messages
$stmt = $conn->prepare("SELECT id, sender_public_id, message, is_read, created_at FROM dms
                        WHERE (sender_public_id = ? AND receiver_public_id = ?)
                           OR (sender_public_id = ? AND receiver_public_id = ?)
                        ORDER BY id ASC LIMIT 100");
$stmt->bind_param('ssss', $me, $with, $with, $me);
$stmt->execute();
$msgData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach($msgData as $m) {
    $m['type'] = 'msg';
    $timeline[] = $m;
    $lastId = $m['id'];
}
$stmt->close();

// 3. Fetch Bidirectional Class Registrations & Stornos (FIXED LOGIC)
$regStmt = $conn->prepare("
    SELECT 
        o.title, 
        r.created_at, 
        r.storno, 
        r.canceled_at, 
        r.public_id AS registrant_id,
        o.public_id AS author_id,
        r.is_paid  -- <--- Add this line
    FROM openclass_registrations r
    JOIN openclasses o ON r.openclass_id = o.openclass_id
    WHERE (r.public_id = ? AND o.public_id = ?) 
       OR (r.public_id = ? AND o.public_id = ?)
");
$regStmt->bind_param('ssss', $me, $with, $with, $me);
$regStmt->execute();
$regData = $regStmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach($regData as $r) {
    $isMeRegistrant = ($r['registrant_id'] === $me);
    $textPrefix = $isMeRegistrant ? "Zapsal jste se na lekci: " : "Registrace na lekci: ";
    $stornoPrefix = $isMeRegistrant ? "Odhlásil jste se z lekce: " : "Storno lekce: ";

    $timeline[] = [
        'type' => 'event', 
        'created_at' => $r['created_at'], 
        'content' => $textPrefix . $r['title']
    ];

    if (isset($r['is_paid']) && $r['is_paid'] == 1) {
        $timeline[] = [
            'type' => 'event',
            'created_at' => date('Y-m-d H:i:s', strtotime($r['created_at']) + 1), 
            'content' => 'Tvůrce přijal vaší platbu',
            'is_paid_status' => true 
        ];
    }
    
    if($r['storno'] == 1) {
        $timeline[] = ['type' => 'event', 'created_at' => $r['canceled_at'], 'content' => $stornoPrefix . $r['title'], 'is_storno' => true];
    }
}
$regStmt->close();

// 4. Fetch Bidirectional Follow Status
$followStmt = $conn->prepare("
    SELECT created_at, follower_public_id
    FROM user_follows
    WHERE (follower_public_id = ? AND followed_public_id = ?)
       OR (follower_public_id = ? AND followed_public_id = ?)
");
$followStmt->bind_param('ssss', $with, $me, $me, $with);
$followStmt->execute();
$follows = $followStmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach($follows as $f) {
    $content = ($f['follower_public_id'] === $me) ? "Začal jste sledovat uživatele" : "Uživatel vás začal sledovat";
    $timeline[] = ['type' => 'event', 'created_at' => $f['created_at'], 'content' => $content];
}
$followStmt->close();

// Sort everything by date
usort($timeline, function($a, $b) {
    return strtotime($a['created_at']) <=> strtotime($b['created_at']);
});

$myStatusStmt = $conn->prepare("SELECT is_creator FROM users WHERE public_id = ? LIMIT 1");
$myStatusStmt->bind_param('s', $me);
$myStatusStmt->execute();
$myStatus = $myStatusStmt->get_result()->fetch_assoc();
$isCreator = $myStatus['is_creator'] ?? 0;
$myStatusStmt->close();

$backUrl = ($isCreator == 1) ? '../app.php?tab=chat' : '../app-dancer.php?tab=chat';

$onlyMessages = array_filter($timeline, fn($i) => $i['type'] === 'msg');
$lastMsg = !empty($onlyMessages) ? end($onlyMessages) : null;
$showStatusAtBottom = ($lastMsg && $lastMsg['sender_public_id'] === $me);

$myProfileStmt = $conn->prepare("
    SELECT name, pfp_path
    FROM user_profile
    WHERE public_id = ?
    LIMIT 1
");

$myProfileStmt->bind_param('s', $me);
$myProfileStmt->execute();
$myProfile = $myProfileStmt->get_result()->fetch_assoc();
$myProfileStmt->close();

$myName = $myProfile['name'] ?? '';
$myPfp  = $myProfile['pfp_path'] ?? 'uploads/profile-pictures/default.png';

$profileIncomplete = false;

if (
    str_contains($myPfp, 'default.png') ||
    $myName === 'Dancefy Uživatel' ||
    empty(trim($myName))
) {
    $profileIncomplete = true;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap">
    <link rel="stylesheet" href="chat.css?version=1">
    <title>Chat | <?= htmlspecialchars($dancerName) ?></title>
</head>
<body>

<div class="header">
    <a href="<?= $backUrl ?>" class="back-btn" onclick="handleBack(event)">‹</a>
    <img src="<?= htmlspecialchars($pfpPath) ?>" alt="" class="avatar" style="width:52px; height:52px; object-fit:cover; border-radius:50%;">
    <div onclick="window.location='/main-indexes/view-profile.php?public_id=<?= htmlspecialchars($with) ?>'" class="user-info">
        <h1><?= htmlspecialchars($dancerName) ?></h1>
        <p><?= htmlspecialchars($username) ?></p>
    </div>
</div>

<?php if ($profileIncomplete): ?>
    <div class="profile-alert" onclick="window.location='/settings/profile.php'">
        <span class="alert-icon">⚡</span>
        <span class="alert-text">
            Váš profil není kompletní. Přidej fotku a jméno, ať tě tvůrci poznají.        </span>
        <span class="alert-action">Upravit</span>
    </div>
<?php endif; ?>

<div class="chat" id="chat">
    <?php 
    $prevType = null; // Track the type of the previous item
    foreach ($timeline as $item): 
        $isEvent = ($item['type'] !== 'msg');
    ?>
        <?php if ($item['type'] === 'msg'): ?>
            <div class="msg <?= $item['sender_public_id']===$me?'me':'them' ?>">
                <?= nl2br(htmlspecialchars($item['message'], ENT_QUOTES, 'UTF-8')) ?>
            </div>
        <?php else: ?>
            <?php 
                $eventClass = 'system-event';
                if (isset($item['is_storno'])) $eventClass .= ' storno';
                
                // If this is an event AND the previous one was an event, add 'no-top-margin'
                if ($prevType !== 'msg' && $prevType !== null) {
                    $eventClass .= ' consecutive-event';
                }

                $extraStyle = '';
                if (isset($item['is_paid_status'])) {
                    $extraStyle = 'style="color: #28a745; font-weight: 600;"';
                }
            ?>
            <div class="<?= $eventClass ?>" <?= $extraStyle ?>>
                <span class="event-text">
                    <?= isset($item['is_paid_status']) ? '• ' : '' ?>
                    <?= htmlspecialchars($item['content']) ?>
                </span>
            </div>
        <?php endif; ?>
        
    <?php 
        $prevType = $item['type']; 
    endforeach; 
    ?>
    
    <div id="status-row">
        <?php if($showStatusAtBottom): ?>
            <?= $lastMsg['is_read'] ? 'Zobrazeno' : 'Odesláno' ?>
        <?php endif; ?>
    </div>
</div>
    

<div class="input-area">
    <form id="form">
        <textarea id="msg" placeholder="Napište zprávu..." rows="1" required></textarea>
        <button>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="white">
                <path d="M2 21L23 12L2 3L2 10L17 12L2 14Z"/>
            </svg>
        </button>
    </form>
</div>

<script>
const withId = "<?= $with ?>";
const myId = "<?= $me ?>";
let lastId = <?= (int)$lastId ?>;
const chatContainer = document.getElementById('chat');
const statusRow = document.getElementById('status-row');
const form = document.getElementById('form');
const msgInput = document.getElementById('msg');

const scrollToBottom = () => { chatContainer.scrollTop = chatContainer.scrollHeight; };
scrollToBottom();

const markAsRead = () => {
    fetch(`mark_read.php?with=${withId}`);
};

markAsRead();

form.onsubmit = async e => {
    e.preventDefault();
    if (!msgInput.value.trim()) return;
    const text = msgInput.value;
    msgInput.value = '';
    msgInput.style.height = '42px';
    statusRow.textContent = 'Odesílání...';

    await fetch('send.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ to: withId, message: text })
    });
};

setInterval(async () => {
    const r = await fetch(`fetch.php?with=${withId}&after=${lastId}`);
    if (r.ok) {
        const d = await r.json();
        if (d.length > 0) {
            d.forEach(m => {
                lastId = m.id;
                const div = document.createElement('div');
                div.className = 'msg ' + (m.sender_public_id === withId ? 'them' : 'me');
                div.textContent = m.message;
                chatContainer.insertBefore(div, statusRow);
                if (m.sender_public_id === myId) {
                    statusRow.textContent = m.is_read ? 'Zobrazeno' : 'Odesláno';
                } else {
                    statusRow.textContent = '';
                    fetch(`mark_read.php?with=${withId}`);
                }
            });
            scrollToBottom();
        }
    }
    if (statusRow.textContent === 'Odesláno') {
        const check = await fetch(`check_seen.php?with=${withId}&last_id=${lastId}`);
        if (check.ok) {
            const status = await check.json();
            if (status.is_read) statusRow.textContent = 'Zobrazeno';
        }
    }
}, 1500);

msgInput.addEventListener('input', function() {
    this.style.height = '42px';
    if (this.scrollHeight > 42) this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    this.style.overflowY = this.scrollHeight > 120 ? 'auto' : 'hidden';
});
</script>
<script>
function handleBack(e) {
    e.preventDefault();

    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = "<?= $backUrl ?>";
    }
}
</script>
</body>
</html>