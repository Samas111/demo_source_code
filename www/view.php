<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/**
 * Calculate countdown for events
 */

error_reporting(E_ALL);

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

include __DIR__ . '/secure/logic.php';
require_once __DIR__ . '/secure/auth.php';
require_once __DIR__ . '/languages/loader.php';

$conn = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    exit('DB error');
}
$conn->set_charset('utf8mb4');

/* -----------------------------------------------------------
    1. Resolve TARGET profile from URL
----------------------------------------------------------- */
$public_id = $_GET['public_id'] ?? null;

if (!$public_id) {
    // If no ID is provided, we can't show a profile
    exit('Profil nenalezen (Missing ID)');
}

/* -----------------------------------------------------------
    2. Identify the logged-in VIEWER (Optional but good practice)
----------------------------------------------------------- */
$viewer_public_id = null;
if (!empty($_COOKIE['dancefy_token'])) {
    $tokenHash = hash('sha256', $_COOKIE['dancefy_token']);
    $u = $conn->prepare("
        SELECT u.public_id 
        FROM user_tokens ut
        JOIN users u ON u.user_id = ut.user_id
        WHERE ut.token_hash = ?
        LIMIT 1
    ");
    $u->bind_param("s", $tokenHash);
    $u->execute();
    $u->bind_result($viewer_public_id);
    $u->fetch();
    $u->close();
}

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

$p = $conn->prepare("
    SELECT 
        u.username, 
        u.is_creator, 
        up.name, 
        up.bio, 
        up.pfp_path, 
        up.dance_group, 
        up.location, 
        up.city
    FROM users u
    LEFT JOIN user_profile up ON u.public_id = up.public_id
    WHERE u.public_id = ?
    LIMIT 1
");
$p->bind_param("s", $public_id);
$p->execute();
$p->store_result();

if ($p->num_rows === 1) {
    $p->bind_result($username, $is_creator, $name, $bio, $pfp, $dance_group, $location, $city);
    $p->fetch();
} else {
    exit('Profil neexistuje');
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
$st = $conn->prepare("SELECT COUNT(*) FROM openclasses WHERE public_id = ?");
$st->bind_param("s", $public_id);
$st->execute();
$st->bind_result($openclasses_count);
$st->fetch();
$st->close();

// Participants count (IN-APP + WEB)
$participants_count = 0;
$st = $conn->prepare("
    SELECT
        (SELECT COUNT(*) FROM openclass_registrations r 
         JOIN openclasses oc ON oc.openclass_id = r.openclass_id 
         WHERE oc.public_id = ? AND r.storno != 1) 
        +
        (SELECT COUNT(*) FROM openclass_registrations_web rw 
         JOIN openclasses oc ON oc.openclass_id = rw.openclass_id 
         WHERE oc.public_id = ? AND rw.verified = 1) 
    AS total_participants
");
$st->bind_param("ss", $public_id, $public_id);
$st->execute();
$st->bind_result($participants_count);
$st->fetch();
$st->close();

/* -----------------------------------------------------------
    5. Load OpenClasses Items
----------------------------------------------------------- */
$items = [];
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
        up.pfp_path
    FROM openclasses oc
    JOIN users u ON u.public_id = oc.public_id
    LEFT JOIN user_profile up ON up.public_id = u.public_id
    WHERE oc.public_id = ?
      AND oc.date >= CURDATE()
    ORDER BY oc.date ASC, oc.start_time ASC
");
$oc->bind_param("s", $public_id);
$oc->execute();
$res_oc = $oc->get_result();
while ($row = $res_oc->fetch_assoc()) {
    $items[] = [
        'pfp'          => $row['pfp_path'],
        'username'     => $row['username'],
        'created_at'   => $row['created_at'],
        'event_date'   => $row['date'],
        'event_title'  => $row['title'],
        'start_time'   => $row['start_time'],
        'end_time'     => $row['end_time'],
        'openclass_id' => $row['openclass_id'],
        'cover_image'  => $row['cover_image']
    ];
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

$viewer_is_creator = 0; 
$viewer_public_id = null;

if (!empty($_COOKIE['dancefy_token'])) {
    $tokenHash = hash('sha256', $_COOKIE['dancefy_token']);
    $u = $conn->prepare("
        SELECT u.public_id, u.is_creator 
        FROM user_tokens ut
        JOIN users u ON u.user_id = ut.user_id
        WHERE ut.token_hash = ?
        LIMIT 1
    ");
    $u->bind_param("s", $tokenHash);
    $u->execute();
    $u->bind_result($viewer_public_id, $viewer_is_creator);
    $u->fetch();
    $u->close();
}

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
    <link rel="stylesheet" href="source/styles/main.css">
    <link rel="stylesheet" href="source/styles/dashboard.css">
    <link rel="stylesheet" href="source/styles/feed.css">
    <link rel="stylesheet" href="source/styles/profile.css">
</head>
<body>
<div class="profile-body">
    <nav>
        <a href="<?= ($viewer_is_creator === 1) ? 'app.php?tab=dashboard' : 'app-dancer.php' ?>">
            <img src="../source/assets/back.png" alt="Back" style="opacity: <?= $has_unread_notifications ? '1' : '0.45' ?>;">
        </a>
        <span>@<?php echo htmlspecialchars($username); ?></span>
        <a href="#">
            <img src="../source/assets/back.png" style="visibility: hidden;">
        </a>
    </nav>
    <div class="limit">
        <div class="profile-header">
            <div class="profile-left">
                <img src="<?php echo htmlspecialchars($pfp); ?>" alt="Profile photo" class="profile-avatar">
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
                    
                    <div class="stat">
                        <span class="stat-number"><?php echo formatStat($participants_count); ?></span>
                        <span class="stat-label">Účastníků</span>
                    </div>
                    
                    <div class="stat">
                        <span class="stat-number"><?php echo formatStat($openclasses_count); ?></span>
                        <span class="stat-label">OpenClasses</span>
                    </div>
                </div>
            </div>
        </div>
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
            <span style="text-transform: uppercase;">STUDIO: <strong><?php echo nl2br(htmlspecialchars($location)); ?></strong></span>
            <span style="text-transform: uppercase;">CITY: <strong><?php echo nl2br(htmlspecialchars($city)); ?></strong></span>
        </div>
        <div class="profile-actions" style="margin-top: 15px;">
            <?php if (!$is_own_profile && $viewer_public_id): ?>
                <button 
                    type="button" 
                    id="followBtn"
                    class="flw-button <?= $is_following ? 'following' : '' ?>" 
                    data-user="<?= htmlspecialchars($public_id) ?>"
                    data-state="<?= $is_following ? 'followed' : 'unfollowed' ?>">
                    <?= $is_following ? 'Sleduješ' : 'Sledovat' ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="divider6"></div>
    <section class="openclass-section">
        <?php if (!empty($items)): ?>
            <h3 class="openclass-title"><?= $T['your_oc'] ?></h3>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <div class="openclass-card">
                <div class="openclass-content">
                <div class="openclass-meta">
                    <span class="countdown">Za 
                    <?php echo eventCountdown($item['event_date'], $item['start_time']); ?>
                    </span>
                </div>
                <h2 class="openclass-name"><?php echo htmlspecialchars($item['event_title']); ?></h2>
                <div class="openclass-time">
                    <span><?php echo date('d.m Y', strtotime($item['event_date'])); ?></span>
                    <span><?php echo $item['start_time']; ?> – <?php echo $item['end_time']; ?></span>
                </div>
                <button class="openclass-cta" onclick="window.location='../../openclass-listing.php?id=<?php echo $item['openclass_id']; ?>'">Připojit se</button>
                </div>
                <div class="openclass-image">
                <img src="uploads/openclasses/<?php echo htmlspecialchars($item['cover_image']); ?>" alt="OpenClass cover">
                </div>
            </div>
        <?php endforeach; ?>
    </section>
    <?php if (empty($profile_posts) && empty($items)): ?>
        <img
            style="width: 80%; margin-left: 10%; margin-top: 15px;"
            src="/source/assets/no-openclass-profile.png"
            alt="no"
        >
    <?php endif; ?>
    <?php if (!empty($profile_posts)): ?>
        <h3 class="openclass-title"  style="width: 85%; margin: auto; margin-top: 20px;">Příspěvky</h3>
    <?php endif; ?>
    <div class="feed" style="width: 87%; margin: auto;"><br>
        <?php foreach ($profile_posts as $item): ?>
            <div class="feed-card">
                <a class="pfp-part">
                    <img src="<?= htmlspecialchars($item['pfp'] ?: 'source/assets/default.png') ?>">
                </a>

                <div class="content-part">
                    <div class="divider">
                        <a class="username"
                        href="/view-profile.php?public_id=<?= htmlspecialchars($item['author_public_id']) ?>">
                            <?= htmlspecialchars($item['username']) ?>
                        </a>
                        <img src="source/assets/verify_badge.png">
                        <span class="time"><?= timeAgo($item['created_at']) ?></span>
                    </div>
                    <div class="main-content">
                        <p><?= nl2br(htmlspecialchars($item['content'])) ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div><br><br>

</div>
<script>
(function() {
    const btn = document.getElementById('followBtn');
    const countEl = document.querySelector('.stat-number'); // First stat is usually followers
    
    if (!btn) return;

    let isWorking = false;

    btn.addEventListener('click', async () => {
        if (isWorking) return;
        isWorking = true;
        
        // Visual feedback - slight opacity to show it's "loading"
        btn.style.opacity = '0.7';

        const publicId = btn.dataset.user;
        const currentState = btn.dataset.state; 

        const fd = new FormData();
        fd.append('public_id', publicId);
        // Cache-busting: append a random timestamp
        fd.append('_t', Date.now());

        try {
            const r = await fetch('/ajax/follow.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                cache: 'no-store' // Forces browser to bypass cache
            });

            const j = await r.json();

            if (j.state === 'followed') {
                btn.classList.add('following');
                btn.textContent = 'Sleduješ';
                btn.dataset.state = 'followed';
                updateCount(1);
            } else if (j.state === 'unfollowed') {
                btn.classList.remove('following');
                btn.textContent = 'Sledovat';
                btn.dataset.state = 'unfollowed';
                updateCount(-1);
            }
        } catch (e) {
            console.error("Follow error:", e);
            // Optional: Alert the user or reset UI
        } finally {
            isWorking = false;
            btn.style.opacity = '1';
        }
    });

    function updateCount(diff) {
        if (!countEl) return;
        let current = parseInt(countEl.textContent.replace(/\s/g, '')) || 0;
        let next = current + diff;
        // Format with spaces for consistency with your PHP number_format
        countEl.textContent = (next <= 0) ? '—' : next.toLocaleString('cs-CZ');
    }
})();
</script>
</body>
</html>
