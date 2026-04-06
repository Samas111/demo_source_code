<?php
/**
 * Calculate countdown for events
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

function eventCountdown(string $date, string $startTime): ?string
{
    $eventTs = strtotime($date . ' ' . $startTime);
    if (!$eventTs) return null;

    $diff = $eventTs - time();
    if ($diff <= 0) return null;

    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);

    if ($days > 0) return $days . 'd ' . $hours . 'h';
    if ($hours > 0) return $hours . 'h ' . $minutes . 'm';
    return $minutes . 'm';
}

/**
 * Calculate time ago for posts
 */
function timeAgo(string $time): string
{
    $t = time() - strtotime($time);
    if ($t < 60) return "Právě teď";
    if ($t < 3600) return floor($t / 60) . " min";
    if ($t < 86400) return floor($t / 3600) . " hod";
    return floor($t / 86400) . " dny";
}

/**
 * Format numbers for stats display
 */
function formatStat($value): string 
{
    return (empty($value) || $value == 0) ? '—' : number_format((int)$value);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';

$conn = $mysqli;

/* -----------------------------------------------------------
    1. Resolve TARGET profile from URL
----------------------------------------------------------- */
$public_id = $_GET['public_id'] ?? null;

if (!$public_id) {
    exit('Profil nenalezen (Missing ID)');
}

/* -----------------------------------------------------------
    2. Identify the logged-in VIEWER
----------------------------------------------------------- */
$viewer_public_id  = $GLOBALS['AUTH_USER']['public_id'];
$viewer_is_creator = $GLOBALS['AUTH_USER']['is_creator'];

/* -----------------------------------------------------------
    3. Load target user's core data (Username, Bio, PFP)
----------------------------------------------------------- */
$name = "";
$bio = "";
$pfp = "default.png";
$dance_group = "";
$location = "";
$city = "";
$username = "";
$is_creator = 0;
$badges = "none";

$p = $conn->prepare("
    SELECT 
        u.username, 
        u.is_creator, 
        up.name, 
        up.bio, 
        up.pfp_path, 
        up.dance_group, 
        up.location, 
        up.city,
        up.badges
    FROM users u
    LEFT JOIN user_profile up ON u.public_id = up.public_id
    WHERE u.public_id = ?
    LIMIT 1
");
$p->bind_param("s", $public_id);
$p->execute();
$p->store_result();

if ($p->num_rows === 1) {
    $p->bind_result($username, $is_creator, $name, $bio, $pfp, $dance_group, $location, $city, $badges);
    $p->fetch();
} else {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Účet byl smazán</title>
        <link rel="stylesheet" href="../source/styles/main.css?v=<?= APP_VERSION ?>">
        <style>
            body {
                margin: 0;
                background: #0f0f14;
                font-family: 'Inter', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100dvh;
                color: white;
            }
            .deleted-container {
                text-align: center;
                max-width: 420px;
                padding: 30px;
            }
            .deleted-container h1 {
                font-size: 22px;
                margin-bottom: 10px;
            }
            .deleted-container p {
                opacity: 0.7;
                margin-bottom: 25px;
                line-height: 1.4;
            }
            .back-btn {
                display: inline-block;
                padding: 6px 10px;
                border-radius: 15px;
                background: linear-gradient(90deg, #FF5143, #FF1B73);
                color: white;
                text-decoration: none;
                font-weight: 600;
                font-size: 0.8rem;
                transition: 0.2s ease;
            }
            .back-btn:hover {
                opacity: 0.85;
            }
        </style>
    </head>
    <body>
        <div class="deleted-container">
            <h1><?= $T['account_deleted'] ?></h1>
            <p><?= $T['profile_not_found'] ?></p>
            <a class="back-btn" href="javascript:void(0)" onclick="window.history.back(); return false;">
                <?= $T['back'] ?>
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$p->close();

/* Hard casts for template safety */
$username   = (string)$username;
$name       = (string)$name;
$bio        = (string)$bio;
$pfp        = (string)($pfp ?: 'default.png');
$is_creator = (int)$is_creator;
$location   = (string)$location;
$city       = (string)$city;
$badges       = (string)$badges;


/* -----------------------------------------------------------
    4. Load Statistics
----------------------------------------------------------- */
// Followers
$followersCount = 0;
$f = $conn->prepare("SELECT COUNT(*) FROM user_follows WHERE followed_public_id = ?");
$f->bind_param("s", $public_id);
$f->execute();
$f->bind_result($followersCount);
$f->fetch();
$f->close();

// OpenClasses Count
$openclasses_count = 0;
$st = $conn->prepare("
    SELECT COUNT(*) 
    FROM openclasses 
    WHERE public_id = ? OR collab_id = ?
");
$st->bind_param("ss", $public_id, $public_id);
$st->execute();
$st->bind_result($openclasses_count);
$st->fetch();
$st->close();

// Participants count (IN-APP + WEB)
$participants_count = 0;

$st = $conn->prepare("
    SELECT
        (
            SELECT COUNT(*) 
            FROM openclass_registrations r
            JOIN openclasses oc ON oc.openclass_id = r.openclass_id
            WHERE (oc.public_id = ? OR oc.collab_id = ?)
            AND r.storno != 1
        )
        +
        (
            SELECT COUNT(*) 
            FROM openclass_registrations_web rw
            JOIN openclasses oc ON oc.openclass_id = rw.openclass_id
            WHERE (oc.public_id = ? OR oc.collab_id = ?)
            AND rw.verified = 1
        )
    AS total_participants
");

$st->bind_param("ssss", $public_id, $public_id, $public_id, $public_id);

$st->execute();
$st->bind_result($participants_count);
$st->fetch();
$st->close();

// --- Add this in Section 4 (Load Statistics) ---
$completed_lessons = 0;
if ($is_creator === 0) {
    $st = $conn->prepare("
        SELECT COUNT(*) 
        FROM openclass_registrations 
        WHERE public_id = ? AND storno != 1
    ");
    // Note: Use $public_id (the profile being viewed) 
    $st->bind_param("s", $public_id);
    $st->execute();
    $st->bind_result($completed_lessons);
    $st->fetch();
    $st->close();
}
/* -----------------------------------------------------------
    5. Load OpenClasses Items
----------------------------------------------------------- */
$items = [];

if ($is_creator === 1) {
    // Logic for Creators: Show ONLY upcoming classes they are teaching
    $oc = $conn->prepare("
        SELECT 
            oc.openclass_id, 
            oc.title, 
            oc.date, 
            oc.start_time, 
            oc.end_time, 
            oc.cover_image, 
            oc.created_at,

            u.username, 
            up.pfp_path,

            oc.public_id,
            oc.collab_id

        FROM openclasses oc

        JOIN users u ON u.public_id = oc.public_id
        LEFT JOIN user_profile up ON up.public_id = u.public_id

        WHERE (oc.public_id = ? OR oc.collab_id = ?)
        AND oc.date >= CURDATE()

        ORDER BY oc.date ASC, oc.start_time ASC
    ");
    $oc->bind_param("ss", $public_id, $public_id);
} else {
    // Logic for Dancers (is_creator = 0): Show ALL registered classes (Portfolio)
    $oc = $conn->prepare("
        SELECT oc.openclass_id, oc.title, oc.date, oc.start_time, oc.end_time, oc.cover_image, oc.created_at, u.username, up.pfp_path
        FROM openclass_registrations r
        JOIN openclasses oc ON r.openclass_id = oc.openclass_id
        JOIN users u ON u.public_id = oc.public_id
        LEFT JOIN user_profile up ON up.public_id = u.public_id
        WHERE r.public_id = ? AND r.storno != 1
        ORDER BY oc.date DESC, oc.start_time DESC
    ");
    $oc->bind_param("s", $public_id);
}

$oc->execute();
$res_oc = $oc->get_result();
while ($row = $res_oc->fetch_assoc()) {
    $items[] = $row;
}
$oc->close();

/* -----------------------------------------------------------
    6. Load Posts
----------------------------------------------------------- */
$profile_posts = [];
$pp = $conn->prepare("
    SELECT
        p.id,
        p.content,
        p.created_at,
        u.username,
        u.public_id AS author_public_id,
        up.pfp_path AS pfp
    FROM posts p
    JOIN users u ON u.public_id = p.public_id
    LEFT JOIN user_profile up ON up.public_id = u.public_id
    WHERE p.public_id = ?
    ORDER BY p.created_at DESC
    LIMIT 20
");
$pp->bind_param("s", $public_id);
$pp->execute();
$res_pp = $pp->get_result();
while ($row = $res_pp->fetch_assoc()) {
    $profile_posts[] = $row;
}

$pp->close();

/* -----------------------------------------------------------
    7. Resolve Follow State (Single Source of Truth)
----------------------------------------------------------- */
$is_following = false;
if ($viewer_public_id && $viewer_public_id !== $public_id) {
    $fs = $conn->prepare("SELECT 1 FROM user_follows WHERE follower_public_id = ? AND followed_public_id = ? LIMIT 1");
    $fs->bind_param("ss", $viewer_public_id, $public_id);
    $fs->execute();
    $fs->store_result();
    $is_following = ($fs->num_rows === 1);
    $fs->close();
}
$is_own_profile = ($viewer_public_id === $public_id);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="/source/styles/main-app/main.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="/source/styles/main-app/dashboard.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="/source/styles/main-app/feed.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="/source/styles/main-app/profile.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">

</head>
<body>
<div class="profile-body">
    <nav>
        <a href="javascript:void(0)" onclick="window.history.back(); return false;">
            <img src="/source/assets/back.png" alt="Back" style="opacity: 0.7;">
        </a>
        <span>@<?php echo htmlspecialchars($username); ?></span>
        
        <div style="width: 24px;"></div> 
    </nav>
    <div class="limit">
        <div class="profile-header">
            <div class="profile-left">
                <?php
                $pfp = $pfp ?? '';

                if ($pfp === '') {
                    $pfp = '/assets/default-pfp.png';
                } elseif (!preg_match('#^https?://#', $pfp)) {
                    $pfp = '/' . ltrim($pfp, '/');
                }
                ?>

                <img src="<?= htmlspecialchars($pfp) ?>" alt="Profile photo" class="profile-avatar">
            </div>

            <div class="profile-right">
                <div class="profile-name">
                <span class="name"><?php echo htmlspecialchars($name); ?></span>
                    <?php if ($is_creator === 1): ?>
                        <span class="verified"><img src="../source/assets/verify_badge.png" alt=""></span>
                    <?php endif; ?>
                </div>

                <div class="profile-stats">
                    <div class="stat">
                        <span class="stat-number"><?php echo formatStat($followersCount); ?></span>
                        <span class="stat-label"><?= $T['followers'] ?></span>
                    </div>

                    <?php if ($is_creator === 0): ?>
                        <div class="stat">
                            <span class="stat-number"><?php echo formatStat($completed_lessons); ?></span>
                            <span class="stat-label"><?= $T['attendances'] ?></span>
                        </div>
                    <?php endif; ?>

                    <?php 
                        $p_stat = formatStat($participants_count); 
                        if ($is_creator === 1 && $participants_count > 0 && $p_stat !== '—'): 
                    ?>
                        <div class="stat">
                            <span class="stat-number"><?php echo $p_stat; ?></span>
                            <span class="stat-label"><?= $T['participants'] ?></span>
                        </div>
                    <?php endif; ?>

                    <?php 
                        $oc_stat = formatStat($openclasses_count); 
                        if ($is_creator === 1 && $openclasses_count > 0 && $oc_stat !== '—'): 
                    ?>
                        <div class="stat">
                            <span class="stat-number"><?php echo $oc_stat; ?></span>
                            <span class="stat-label">OpenClasses</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($badges !== "none" && !empty($badges)): ?>
            <?php 
                $badgeArray = explode(';', $badges);
                foreach ($badgeArray as $badge):
                    $badge = trim($badge);
                    if ($badge === '') continue;
            ?>
                <div class="badge"><?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="bio-section">
            <p><?php echo nl2br(htmlspecialchars($bio)); ?></p>
        </div>
        <div class="divider5"></div>
        <div class="tags">
            <?php
                $styles = array_filter(array_map('trim', explode(',', $dance_group)));

                if (empty($styles)) {
                    echo '<div class="tag">—</div>';
                } else {
                    foreach ($styles as $style) {
                        echo '<div class="tag">' . htmlspecialchars($style) . '</div>';
                    }
                }
            ?>
        </div>
        <div class="divider5"></div>
        <div class="location">
            <?php if (!empty($location) && strtolower(trim($location)) !== 'none'): ?>
                <span style="text-transform: uppercase;">
                    STUDIO: <strong><?= nl2br(htmlspecialchars($location)) ?></strong>
                </span>
            <?php endif; ?>

            <?php if (!empty($city) && strtolower(trim($city)) !== 'none'): ?>
                <span style="text-transform: uppercase;">
                    CITY: <strong><?= nl2br(htmlspecialchars($city)) ?></strong>
                </span>
            <?php endif; ?>
        </div>
        <div class="profile-actions" style="margin-top: 15px;">
            <?php if (!$is_own_profile && $viewer_public_id): ?>
                <button 
                    type="button" 
                    id="followBtn"
                    class="flw-button <?= $is_following ? 'following' : '' ?>" 
                    data-user="<?= htmlspecialchars($public_id) ?>"
                    data-state="<?= $is_following ? 'followed' : 'unfollowed' ?>">
                    <?= $is_following ? $T['following_btn'] : $T['follow'] ?>
                </button>
            <?php endif; ?>
            <?php if ($is_own_profile): ?>
                <button class="send-button" onclick="window.location='/settings/profile.php'">
                    <?= $T['edit_profile'] ?>
                </button>
            <?php else: ?>
                <button class="message-button" onclick="window.location='/messages/chat.php?public_id=<?= htmlspecialchars($public_id) ?>'">
                    <?= $T['message_btn'] ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="divider6"></div>
        <section class="openclass-section">
        <?php if (!empty($items)): ?>
            <h3 class="openclass-title">
                <?php echo ($is_creator === 1) ? $T['current_openclasses'] : $T['portfolio_lessons']; ?>
            </h3>
        <?php endif; ?>
        
        <?php foreach ($items as $item): ?>
            <div class="openclass-card">
                <div class="openclass-content">
                    
                    <?php if ($is_creator === 1): ?>
                        <div class="openclass-meta">
                            <span class="countdown">                        
                                <?php if ($is_creator === 1 && $item['collab_id'] === $public_id): ?>
                                    <?= $T['collab'] ?> |
                                <?php endif; ?> 
                                Za <?php echo eventCountdown($item['date'], $item['start_time']); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <h2 class="openclass-name"><?php echo htmlspecialchars($item['title']); ?></h2>
                    
                    <?php if($is_creator === 0): ?>
                        <div class="openclass-teacher" style="font-size: 0.8rem; opacity: 0.7; margin-bottom: 5px; font-weight: 500;">
                            <?= $T['instructor'] ?>: @<?php echo htmlspecialchars($item['username']); ?>
                        </div>
                    <?php endif; ?>
            
                    <div class="openclass-time">
                        <span><?php echo date('d.m Y', strtotime($item['date'])); ?></span>
                        <span><?php echo $item['start_time']; ?> – <?php echo $item['end_time']; ?></span>
                    </div>
                    
                    <button class="openclass-cta" onclick="window.location='/main-indexes/openclass-listing.php?id=<?php echo $item['openclass_id']; ?>'">
                        <?= $T['show'] ?>
                    </button>
                </div>
                <div class="openclass-image">
                    <img src="../uploads/openclasses/<?php echo htmlspecialchars($item['cover_image']); ?>" alt="OpenClass cover">
                </div>
            </div>
        <?php endforeach; ?>
    </section>
    <?php if (empty($items) && empty($items)): ?>
        <img
            style="width: 80%; margin-left: 10%; margin-top: 15px;"
            src="/source/assets/no-openclass-profile.png"
            alt="no"
        >
    <?php endif; ?>
    </div><br><br>

</div>
<script>
(function() {
    const btn = document.getElementById('followBtn');
    const countEl = document.querySelector('.stat-number'); 
    
    if (!btn) return;

    let isWorking = false;

    btn.addEventListener('click', async () => {
        if (isWorking) return;
        isWorking = true;
        
        btn.style.opacity = '0.7';

        const publicId = btn.dataset.user;
        const currentState = btn.dataset.state; 

        const fd = new FormData();
        fd.append('public_id', publicId);
        fd.append('_t', Date.now());

        try {
            const r = await fetch('/ajax/follow.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                cache: 'no-store' 
            });

            const j = await r.json();

            if (j.state === 'followed') {
                btn.classList.add('following');
                btn.textContent = '<?= $T['following_btn'] ?>';
                btn.dataset.state = 'followed';
                updateCount(1);
            } else if (j.state === 'unfollowed') {
                btn.classList.remove('following');
                btn.textContent = '<?= $T['follow'] ?>';
                btn.dataset.state = 'unfollowed';
                updateCount(-1);
            }
        } catch (e) {
            console.error("Follow error:", e);
        } finally {
            isWorking = false;
            btn.style.opacity = '1';
        }
    });

    function updateCount(diff) {
        if (!countEl) return;
        let current = parseInt(countEl.textContent.replace(/\s/g, '')) || 0;
        let next = current + diff;
        countEl.textContent = (next <= 0) ? '—' : next.toLocaleString('cs-CZ');
    }
})();
</script>
</body>
</html>
