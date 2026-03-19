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
require_once __DIR__ . '/../../secure/require_creator.php';
require_once __DIR__ . '/../../languages/loader.php';


$conn = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    exit('DB error');
}
$conn->set_charset('utf8mb4');


/* -------------------------------
   Resolve user by token (JOIN)
-------------------------------- */
$tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

if (empty($_COOKIE['dancefy_token'])) {
    http_response_code(401);
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
    SELECT bio, pfp_path, dance_group
    FROM user_profile
    WHERE public_id = ?
    LIMIT 1
");

$p->bind_param("s", $public_id);
$p->execute();
$p->store_result();

if ($p->num_rows === 1) {
    $p->bind_result($bio, $pfp, $dance_group);
    $p->fetch();
}

$p->close();

/* Safety casts */
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
    $t = time() - strtotime($time);

    if ($t < 60) return "Právě teď";
    if ($t < 3600) return floor($t / 60) . " min";
    if ($t < 86400) return floor($t / 3600) . " hod";
    return floor($t / 86400) . " dny";
}

$oc->close();
$conn->close();

?>

<div class="creator-window">

    <div class="tag"><?= $T['badge'] ?></div>

    <div class="pfp">
        <img src="<?php echo htmlspecialchars($pfp); ?>">
        <div class="pfp-layer1"></div>
        <div class="pfp-layer2"></div>
        <div class="pfp-layer3"></div>
    </div>

    <div class="username">
        <?php if ($is_creator === 1): ?>
            <div class="creator">
                <img src="source/assets/verify_badge.png" alt="">
            </div>
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($username); ?></h1>
    </div>

    <div class="tags">
        <?php
            $styles = array_filter(array_map('trim', explode(',', $dance_group)));

            if (empty($styles)) {
                echo '<div class="style muted">—</div>';
            } else {
                foreach ($styles as $style) {
                    echo '<div class="style">' . htmlspecialchars($style) . '</div>';
                }
            }
        ?>
    </div>


    <div class="bio"><?php echo nl2br(htmlspecialchars($bio)); ?></div>

    <div class="flw-card" style="width: 40%;">
        <div class="flw-count"><?php echo $followersCount; ?> <?= $T['followers'] ?></div>
        <!-- <a class="flw-button">+ Sledovat</a> -->
    </div>
</div>
<?php if (empty($items)): ?>
    <img style="width: 80%; margin-left: 10%;" src="/source/assets/no-openclass.png" alt="no">
<?php endif; ?>
<?php if (!empty($items)): ?>
    <h2 class="title"><?= $T['your_oc'] ?></h2>
<?php endif; ?>
<?php foreach ($items as $item): ?>
    <div class="hero-card">
        <div class="hero-left">
            <div class="hero-countdown">
                ⏱ <?php echo eventCountdown($item['event_date'], $item['start_time']); ?>
            </div>
            <h2 class="hero-title">
                <?php echo htmlspecialchars($item['event_title']); ?>
            </h2>
            <div class="hero-meta">
                <span><?php echo date('m.d Y', strtotime($item['event_date'])); ?></span>
                <span><?php echo $item['start_time']; ?> – <?php echo $item['end_time']; ?></span>
            </div>
            <button
                class="hero-cta"
                onclick="window.location='../../openclass-listing.php?id=<?php echo $item['openclass_id']; ?>'">
                <?= $T['view'] ?>
            </button>
        </div>
        <div class="hero-right">
            <img src="uploads/openclasses/<?php echo htmlspecialchars($item['cover_image']); ?>">
        </div>
    </div>
<?php endforeach; ?>

