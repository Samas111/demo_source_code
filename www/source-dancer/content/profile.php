<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


function eventCountdown(string $date, string $startTime): ?string
{
    $eventTs = strtotime($date . ' ' . $startTime);
    if (!$eventTs) {
        return null;
    }

    $diff = $eventTs - time();
    if ($diff <= 0) {
        return null;
    }

    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);

    if ($days > 0) return $days . 'd ' . $hours . 'h';
    if ($hours > 0) return $hours . 'h ' . $minutes . 'm';
    return $minutes . 'm';
}


include __DIR__ . '/../../secure/logic.php';
require_once __DIR__ . '/../../secure/auth.php';
require_once __DIR__ . '/../../languages/loader.php';

/* -------------------------------
   Resolve user by token (JOIN)
-------------------------------- */
$tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

if (empty($_COOKIE['dancefy_token'])){
    header("/registration/register-server-logic/auto.php");
    exit;
}

$u = $conn->prepare("
    SELECT 
        u.username,
        u.public_id,
        u.is_creator
    FROM user_tokens ut
    JOIN users u ON u.user_id = ut.user_id
    WHERE ut.token_hash = ?
    LIMIT 1
");
$u->bind_param("s", $tokenHash);
$u->execute();
$u->store_result();


$u->bind_result($username, $public_id, $is_creator);
$u->fetch();
$u->close();

$followersCount = 0;

$f = $conn->prepare("
    SELECT COUNT(*) 
    FROM user_follows 
    WHERE followed_public_id = ?
");
$f->bind_param("s", $public_id);
$f->execute();
$f->bind_result($followersCount);
$f->fetch();
$f->close();

$followersCount = (int)$followersCount;

/* -------------------------------------------
   Load profile info by public_id
------------------------------------------- */
$bio = "";
$pfp = "default.png";
$dance_group = "";

$p = $conn->prepare("
    SELECT name, bio, pfp_path, dance_group, location, city
    FROM user_profile
    WHERE public_id = ?
    LIMIT 1
");

$p->bind_param("s", $public_id);
$p->execute();
$p->store_result();

if ($p->num_rows === 1) {
    $p->bind_result($name, $bio, $pfp, $dance_group, $location, $city);
    $p->fetch();
}

$p->close();

/* Safety casts */
$name = (string)$name;

$bio = (string)$bio;
$pfp = (string)$pfp;
$dance_group = (string)$dance_group;



/* -------------------------------------------
   Final hard casts (safety)
------------------------------------------- */
$username   = (string)$username;
$bio        = (string)$bio;
$pfp        = (string)$pfp;
$is_creator = (int)$is_creator;
$location   = (string)$location;
$city       = (string)$city;


/* -------------------------------------------
   Load OpenClasses the user is REGISTERED for
------------------------------------------- */
$items = [];

// We JOIN registrations to openclasses to get the details of the classes they joined
$oc = $conn->prepare("
    SELECT 
        oc.openclass_id, 
        oc.title, 
        oc.date, 
        oc.start_time, 
        oc.end_time, 
        oc.cover_image, 
        oc.created_at,
        u.username AS creator_name,
        up.pfp_path AS creator_pfp
    FROM openclass_registrations r
    JOIN openclasses oc ON r.openclass_id = oc.openclass_id
    JOIN users u ON u.public_id = oc.public_id
    LEFT JOIN user_profile up ON up.public_id = u.public_id
    WHERE r.public_id = ? 
      AND r.storno != 1
      AND oc.date >= CURDATE()
    ORDER BY oc.date ASC
");

$oc->bind_param("s", $public_id);
$oc->execute();
$oc->store_result();

$oc->bind_result(
    $openclass_id, 
    $event_title, 
    $event_date, 
    $start_time, 
    $end_time, 
    $cover_image, 
    $created_at, 
    $username_db, 
    $pfp_db
);

while ($oc->fetch()) {
    $items[] = [
        'pfp'          => $pfp_db,
        'username'     => $username_db,
        'created_at'   => $created_at,
        'event_date'   => $event_date,
        'event_title'  => $event_title,
        'start_time'   => $start_time,
        'end_time'     => $end_time,
        'openclass_id' => $openclass_id,
        'cover_image'  => $cover_image
    ];
}


function timeAgo(string $time): string
{
    $t = time() - strtotime($time);

    if ($t < 60) return "Právě teď";
    if ($t < 3600) return floor($t / 60) . " min";
    if ($t < 86400) return floor($t / 3600) . " hod";
    return floor($t / 86400) . " dny";
}

$st = $conn->prepare("
    SELECT COUNT(*)
    FROM openclasses
    WHERE public_id = ?
");
$st->bind_param("s", $public_id);
$st->execute();
$st->bind_result($openclasses_count);
$st->fetch();
$st->close();

/* Participants count (IN-APP + WEB) */
$st = $conn->prepare("
    SELECT
        (
            SELECT COUNT(*)
            FROM openclass_registrations r
            JOIN openclasses oc ON oc.openclass_id = r.openclass_id
            WHERE oc.public_id = ?
              AND r.storno != 1
        ) +
        (
            SELECT COUNT(*)
            FROM openclass_registrations_web rw
            JOIN openclasses oc ON oc.openclass_id = rw.openclass_id
            WHERE oc.public_id = ?
              AND rw.verified = 1
        ) AS total_participants
");
$st->bind_param("ss", $public_id, $public_id);
$st->execute();
$st->bind_result($participants_count);
$st->fetch();
$st->close();

$profile_posts = [];

$pp = $conn->prepare("
    SELECT
        p.id,
        p.content,
        p.created_at,
        u.username,
        u.public_id AS author_public_id,
        up.pfp_path AS pfp,
        0 AS viewer_liked
    FROM posts p
    JOIN users u ON u.public_id = p.public_id
    LEFT JOIN user_profile up ON up.public_id = u.public_id
    WHERE p.public_id = ?
    ORDER BY p.created_at DESC
    LIMIT 20
");

$pp->bind_param("s", $public_id);
$pp->execute();
$res = $pp->get_result();

while ($row = $res->fetch_assoc()) {
    $profile_posts[] = $row;
}

$pp->close();

$oc->close();
$conn->close();

function formatStat($value): string 
{
    return (empty($value) || $value == 0) ? '—' : number_format($value);
}

$profileIncomplete = false;

if (
    str_contains($pfp, 'default.png') ||
    $name === 'Dancefy Uživatel' ||
    empty(trim($name))
) {
    $profileIncomplete = true;
}

?>

<div class="profile-body">
    <!--
    <nav>
        <a href="#">
            <img src="../source/assets/back.png" src="back.png" alt="Notifications" style="opacity: <?= $has_unread_notifications ? '1' : '0.45' ?>;">
        </a>
        <span><?php echo htmlspecialchars($username); ?></span>
        <a href="settings.php">
            <img src="<?php echo htmlspecialchars($pfp); ?>" alt="Profile">
        </a>
    </nav>
    -->
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
                        <span class="stat-number"><?php echo count($items); ?></span>
                        <span class="stat-label">Absolvovaných Classes</span>
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
    </div>
    <div class="divider6"></div>
    <?php if ($profileIncomplete): ?>
        <div class="profile-warning" onclick="window.location='/settings/profile.php'">
            <div class="warning-left">
                <div class="warning-icon">⚡</div>
                <div class="warning-text">
                    <span class="warning-title">Dokonči svůj profil</span>
                    <span class="warning-sub">Tvůj profil vidí tvůrci při registraci</span>
                </div>
            </div>
            <div class="warning-action">Upravit</div>
        </div>
    <?php endif; ?>
    <section class="openclass-section">
        <?php if (!empty($items)): ?>
            <h3 class="openclass-title">Vaše portfolio</h3>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <div class="openclass-card">
                <div class="openclass-content">
                <div class="openclass-meta">
                    <span class="countdown">
                    Za <?php echo eventCountdown($item['event_date'], $item['start_time']); ?>
                    </span>
                </div>
                <h2 class="openclass-name"><?php echo htmlspecialchars($item['event_title']); ?></h2>
                <div class="openclass-time">
                    <span><?php echo date('m.d Y', strtotime($item['event_date'])); ?></span>
                    <span><?php echo $item['start_time']; ?> – <?php echo $item['end_time']; ?></span>
                </div>
                <button class="openclass-cta" onclick="window.location='../../openclass-listing.php?id=<?php echo $item['openclass_id']; ?>'">Zobrazit</button>
                </div>
                <div class="openclass-image">
                <img src="uploads/openclasses/<?php echo htmlspecialchars($item['cover_image']); ?>" alt="OpenClass cover">
                </div>
            </div>
        <?php endforeach; ?>
    </section>


    <?php if (empty($items)): ?>
            <img style="width: 80%; margin-left: 10%; margin-top: 0px;" src="/source-dancer/assets/no-openclass-dancer.png" alt="no">
    <?php endif; ?>

</div>
