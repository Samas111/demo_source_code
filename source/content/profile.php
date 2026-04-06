<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/require_creator.php';

$user = $GLOBALS['AUTH_USER'];

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

$username    = (string)$GLOBALS['AUTH_USER']['username'];
$public_id   = $GLOBALS['AUTH_USER']['public_id'];
$is_creator  = (int)$GLOBALS['AUTH_USER']['is_creator'];
$name        = (string)$GLOBALS['AUTH_USER']['name'];
$bio         = (string)$GLOBALS['AUTH_USER']['bio'];
$pfp         = (string)$GLOBALS['AUTH_USER']['pfp'];
$dance_group = (string)$GLOBALS['AUTH_USER']['dance_group'];
$location    = (string)$GLOBALS['AUTH_USER']['location'];
$city        = (string)$GLOBALS['AUTH_USER']['city'];
$badges      = (string)$GLOBALS['AUTH_USER']['badges'];

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
    ORDER BY oc.created_at DESC
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
    global $T;
    $t = time() - strtotime($time);

    if ($t < 60) return $T['just_now'];
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
                        <span class="stat-number"><?php echo formatStat($followersCount); ?></span>
                        <span class="stat-label"><?= $T['followers'] ?></span>
                    </div>
                    
                    <div class="stat">
                        <span class="stat-number"><?php echo formatStat($participants_count); ?></span>
                        <span class="stat-label"><?= $T['participants'] ?></span>
                    </div>
                    
                    <div class="stat">
                        <span class="stat-number"><?php echo formatStat($openclasses_count); ?></span>
                        <span class="stat-label">OpenClasses</span>
                    </div>
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
            <button class="send-button" onclick="window.location='/settings/profile.php'"><?= $T['edit_profile'] ?></button>
        </div>
    </div>
    <div class="divider6"></div>
    <div class="feed-root">
    <div class="feed">
    <section class="openclass-section">
        <?php if (!empty($items)): ?>
            <h3 class="openclass-title"><?= $T['your_oc'] ?></h3>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <div class="openclass-card">
                <div class="openclass-content">
                <div class="openclass-meta">
                    <span class="countdown"><?= $T['countdown_in'] ?>
                    <?php echo eventCountdown($item['event_date'], $item['start_time']); ?>
                    </span>
                </div>
                <h2 class="openclass-name"><?php echo htmlspecialchars($item['event_title']); ?></h2>
                <div class="openclass-time">
                    <span><?php echo date('m.d Y', strtotime($item['event_date'])); ?></span>
                    <span><?php echo $item['start_time']; ?> – <?php echo $item['end_time']; ?></span>
                </div>
                <button class="openclass-cta" onclick="window.location='main-indexes/openclass-listing.php?id=<?php echo $item['openclass_id']; ?>'"><?= $T['join'] ?></button>
                </div>
                <div class="openclass-image">
                <img src="uploads/openclasses/<?php echo htmlspecialchars($item['cover_image']); ?>" alt="OpenClass cover">
                </div>
            </div>
        <?php endforeach; ?>
    </section>
    <?php if (empty($items)): ?>
            <img style="width: 80%; margin-left: 10%; margin-top: -20px;" src="/source/assets/no-openclass-creator.png" onclick="window.location='post/post.php'" alt="no">
    <?php endif; ?>
    <br><br>

</div>
