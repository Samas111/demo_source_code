<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require __DIR__ . '/../../secure/logic.php';
require_once __DIR__ . '/../../secure/auth.php';

/* ============================
   RESOLVE VIEWER (NEW)
   ============================ */
$viewer_public_id = null;

if (empty($_COOKIE['dancefy_token'])){
    header("/registration/register-server-logic/auto.php");
    exit;
}

if (
    !empty($_COOKIE['dancefy_token']) &&
    preg_match('/^[a-f0-9]{64}$/', $_COOKIE['dancefy_token'])
) {
    $tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

    $u = $mysqli->prepare("
        SELECT u.public_id
        FROM user_tokens ut
        JOIN users u ON u.user_id = ut.user_id
        WHERE ut.token_hash = ?
        LIMIT 1
    ");
    $u->bind_param('s', $tokenHash);
    $u->execute();
    $u->bind_result($viewer_public_id);
    $u->fetch();
    $u->close();
}

/* ============================
   FEED QUERY (EXTENDED, NOT REWRITTEN)
   ============================ */
$q = "
(
    SELECT
        'post' AS type,
        p.id,
        u.public_id AS author_public_id,
        u.username,
        up.pfp_path AS pfp,
        NULL AS cover_image,
        NULL AS openclass_id,
        p.content,
        p.likes,
        0 AS reposts,
        p.created_at,
        NULL AS event_title,
        NULL AS event_date,
        NULL AS start_time,
        NULL AS end_time,
        NULL AS user_storno,

        EXISTS (
            SELECT 1
            FROM post_likes pl
            WHERE pl.post_id = p.id
              AND pl.public_id = ?
        ) AS viewer_liked,

        CASE
            WHEN u.public_id = ? THEN 1
            WHEN EXISTS (
                SELECT 1
                FROM user_follows uf
                WHERE uf.follower_public_id = ?
                AND uf.followed_public_id = u.public_id
            ) THEN 1
            ELSE 0
        END AS is_following


    FROM posts p
    JOIN users u ON u.public_id = p.public_id
    LEFT JOIN user_profile up ON up.public_id = u.public_id
    WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
)

UNION ALL

(
    SELECT
        'openclass' AS type,
        o.id,
        u.public_id AS author_public_id,
        u.username,
        up.pfp_path AS pfp,
        o.cover_image AS cover_image,
        o.openclass_id AS openclass_id,
        NULL AS content,
        0 AS likes,
        0 AS reposts,
        o.created_at,
        o.title AS event_title,
        o.date AS event_date,
        o.start_time,
        o.end_time,
        r.storno AS user_storno,
        0 AS viewer_liked,

        CASE
            WHEN u.public_id = ? THEN 1
            WHEN EXISTS (
                SELECT 1
                FROM user_follows uf
                WHERE uf.follower_public_id = ?
                AND uf.followed_public_id = u.public_id
            ) THEN 1
            ELSE 0
        END AS is_following

    FROM openclasses o
    JOIN users u ON u.public_id = o.public_id
    LEFT JOIN user_profile up ON up.public_id = o.public_id
    LEFT JOIN openclass_registrations r
        ON r.openclass_id = o.openclass_id
       AND r.public_id = ?
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
)

ORDER BY is_following DESC, created_at DESC
";

/* ============================
   EXECUTE (PREPARED)
   ============================ */
$stmt = $mysqli->prepare($q);
$stmt->bind_param(
    'ssssss',
    $viewer_public_id, // posts: self-check
    $viewer_public_id, // posts: follows
    $viewer_public_id, // post_likes

    $viewer_public_id, // openclasses: self-check
    $viewer_public_id, // openclasses: follows
    $viewer_public_id  // registrations
);



$stmt->execute();
$res = $stmt->get_result();

$feed = [];
while ($row = $res->fetch_assoc()) {
    $feed[] = $row;
}

$followingFeed = [];
$discoverFeed  = [];

foreach ($feed as $item) {
    if ((int)$item['is_following'] === 1) {
        $followingFeed[] = $item;
    } else {
        $discoverFeed[] = $item;
    }
}

/* ============================
   HELPERS (UNCHANGED)
   ============================ */
function timeAgo($time) {
    $t = time() - strtotime($time);
    if ($t < 60) return "Právě teď";
    if ($t < 3600) return floor($t / 60) . " min";
    if ($t < 86400) return floor($t / 3600) . " hod";
    return floor($t / 86400) . " dny";
}

function eventCountdown($date, $startTime) {
    if (!$date) return null;

    $eventTs = strtotime($date . ' ' . ($startTime ?: '00:00'));
    $diff = $eventTs - time();

    if ($diff <= 0) return "Registrace Skončila!";

    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);

    if ($days > 0) return "⏱ {$days} d · {$hours} h";
    if ($hours > 0) return "⏱ {$hours} h · {$minutes} min";
    return "⏱ {$minutes} min";
}
?>
<div class="feed-fixed">
    <div class="feed-switch">
        <span class="switch-btn switch-active" data-target="following">Sleduji</span>
        <span>|</span>
        <span class="switch-btn" data-target="discover">Objevit</span>
    </div>
</div>
<div class="feed-root">
    <?php if (empty($followingFeed)): ?>
        <div class="update-info">
            <p>Začni sledovat své <strong>oblíbené tvůrce!</strong></p>
        </div>
    <?php else: ?>
        <div class="update-info">
            <?php
                $threeWeeksAgo = strtotime('-2 weeks');
                $recentFollowingCount = 0;

                foreach ($feed as $item) {
                    if (
                        (int)$item['is_following'] === 1 &&
                        strtotime($item['created_at']) >= $threeWeeksAgo
                    ) {
                        $recentFollowingCount++;
                    }
                }
            ?>
            <p>Nových <strong><?= $recentFollowingCount ?></strong> příspěvků od tvůrců co sleduješ</p>
        </div>
    <?php endif; ?>
<div class="feed">

    <!-- =========================
         FOLLOWING
    ========================== -->
    <div class="following" id="following-section">
        <?php if (empty($followingFeed)): ?>
            <div class="nic"></div>
        <?php endif; ?>

        <?php foreach ($followingFeed as $item): ?>

            <?php if ($item['type'] === 'post'): ?>

                <div class="feed-card" data-type="post" data-post-id="<?= (int)$item['id'] ?>"  data-author-id="<?= htmlspecialchars($item['author_public_id']) ?>">
                    <a class="pfp-part" href="/view-profile.php?public_id=<?= htmlspecialchars($item['author_public_id']) ?>">
                        <img src="<?= htmlspecialchars($item['pfp'] ?? 'source/assets/default.png') ?>">
                    </a>

                    <div class="content-part">
                        <div class="divider">
                            <a class="username" href="/view-profile.php?public_id=<?= htmlspecialchars($item['author_public_id']) ?>">
                                <?= htmlspecialchars($item['username']) ?>
                            </a>
                            <img src="source/assets/verify_badge.png">
                            <span class="time"><?= timeAgo($item['created_at']) ?></span>
                            <button class="report-btn" type="button">⋯</button>

                            <div class="report-popup">
                                <button class="report-action">Nahlásit</button>
                            </div>
                        </div>

                        <div class="main-content">
                            <p><?= nl2br(htmlspecialchars($item['content'])) ?></p>
                        </div>

                        <div class="action-panel" style="display: none">
                            <div class="user-actions"
                                 data-liked="<?= (int)$item['viewer_liked'] ?>"
                                 data-post-id="<?= (int)$item['id'] ?>">
                                <img src="source/assets/<?= $item['viewer_liked'] ? 'full-like.png' : 'empty-like.png' ?>">
                                <div class="count">UpVote</div>
                                <img src="source/assets/share.png" style="display: none;">
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>

                <?php
                    $isRegistered = false;
                    $hasCanceled  = false;

                    if ($item['user_storno'] !== null) {
                        $isRegistered = (int)$item['user_storno'] === 0;
                        $hasCanceled  = (int)$item['user_storno'] === 1;
                    }
                ?>

                <div class="feed-card event" data-type="post" data-post-id="<?= (int)$item['id'] ?>" data-author-id="<?= htmlspecialchars($item['author_public_id']) ?>">
                    <a class="pfp-part" href="/view-profile.php?public_id=<?= htmlspecialchars($item['author_public_id']) ?>">
                        <img src="<?= htmlspecialchars($item['pfp'] ?? 'source/assets/default.png') ?>">
                    </a>

                    <div class="content-part">
                        <div class="divider">
                            <a class="username" href="/view-profile.php?public_id=<?= htmlspecialchars($item['author_public_id']) ?>">
                                <?= htmlspecialchars($item['username']) ?>
                            </a>
                            <img src="source/assets/verify_badge.png">
                            <span class="time"><?= timeAgo($item['created_at']) ?></span>
                            <button class="report-btn" type="button">⋯</button>

                            <div class="report-popup">
                                <button class="report-action">Nahlásit</button>
                            </div>
                        </div>

                        <div class="main-content event-content">
                            <div class="event-left">
                                <?php if ($cd = eventCountdown($item['event_date'], $item['start_time'])): ?>
                                    <span class="event-countdown"><?= $cd ?></span>
                                <?php endif; ?>

                                <h2 class="event-title"><?= htmlspecialchars($item['event_title']) ?></h2>

                                <div class="event-meta">
                                    <span><?= date('d.m.', strtotime($item['event_date'])) ?></span>
                                    <span><?= $item['start_time'] ?> – <?= $item['end_time'] ?></span>
                                </div>

                                <button class="event-cta"
                                        onclick="window.location='../../openclass-dancer.php?id=<?= $item['openclass_id'] ?>'">
                                    Rezervovat Místo
                                </button>
                            </div>

                            <div class="event-thumb">
                                <img src="uploads/openclasses/<?= htmlspecialchars($item['cover_image']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        <?php endforeach; ?>

    </div>

    <!-- =========================
         DISCOVER PLACEHOLDER
    ========================== -->
    <div class="feed-divider" id="discover-anchor">
        <p>Objevuj</p>
    </div>

    <!-- =========================
         DISCOVER
    ========================== -->
    <div class="discover" id="discover-section">

        <?php foreach ($discoverFeed as $item): ?>

            <?php if ($item['type'] === 'post'): ?>

                <div class="feed-card" data-type="post" data-post-id="<?= (int)$item['id'] ?>"  data-author-id="<?= htmlspecialchars($item['author_public_id']) ?>">
                    <a class="pfp-part" href="/view-profile.php?public_id=<?= htmlspecialchars($item['author_public_id']) ?>">
                        <img src="<?= htmlspecialchars($item['pfp'] ?? 'source/assets/default.png') ?>">
                    </a>

                    <div class="content-part">
                        <div class="divider">
                            <a class="username" href="/view-profile.php?public_id=<?= htmlspecialchars($item['author_public_id']) ?>">
                                <?= htmlspecialchars($item['username']) ?>
                            </a>
                            <img src="source/assets/verify_badge.png">
                            <span class="time"><?= timeAgo($item['created_at']) ?></span>
                            <button class="report-btn" type="button">⋯</button>

                            <div class="report-popup">
                                <button class="report-action">Nahlásit</button>
                            </div>
                        </div>

                        <div class="main-content">
                            <p><?= nl2br(htmlspecialchars($item['content'])) ?></p>
                        </div>

                        <div class="action-panel" style="display: none">
                            <div class="user-actions"
                                 data-liked="<?= (int)$item['viewer_liked'] ?>"
                                 data-post-id="<?= (int)$item['id'] ?>">
                                <img src="source/assets/<?= $item['viewer_liked'] ? 'full-like.png' : 'empty-like.png' ?>">
                                <div class="count">UpVote</div>
                                <img src="source/assets/share.png" style="display: none;">
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>

                <div class="feed-card event" data-type="post" data-post-id="<?= (int)$item['id'] ?>" data-author-id="<?= htmlspecialchars($item['author_public_id']) ?>">
                    <a class="pfp-part" href="/view-profile.php?public_id=<?= htmlspecialchars($item['author_public_id']) ?>">
                        <img src="<?= htmlspecialchars($item['pfp'] ?? 'source/assets/default.png') ?>">
                    </a>

                    <div class="content-part">
                        <div class="divider">
                            <a class="username" href="/view-profile.php?public_id=<?= htmlspecialchars($item['author_public_id']) ?>">
                                <?= htmlspecialchars($item['username']) ?>
                            </a>
                            <img src="source/assets/verify_badge.png">
                            <span class="time"><?= timeAgo($item['created_at']) ?></span>
                            <button class="report-btn" type="button">⋯</button>
                            <div class="report-popup">
                                <button class="report-action">Nahlásit</button>
                            </div>
                        </div>

                        <div class="main-content event-content">
                            <div class="event-left">
                                <?php if ($cd = eventCountdown($item['event_date'], $item['start_time'])): ?>
                                    <span class="event-countdown"><?= $cd ?></span>
                                <?php endif; ?>

                                <h2 class="event-title"><?= htmlspecialchars($item['event_title']) ?></h2>

                                <div class="event-meta">
                                    <span><?= date('d.m.', strtotime($item['event_date'])) ?></span>
                                    <span><?= $item['start_time'] ?> – <?= $item['end_time'] ?></span>
                                </div>

                                <button class="event-cta"
                                        onclick="window.location='../../openclass-dancer.php?id=<?= $item['openclass_id'] ?>'">
                                    Otevřít
                                </button>
                            </div>

                            <div class="event-thumb">
                                <img src="uploads/openclasses/<?= htmlspecialchars($item['cover_image']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        <?php endforeach; ?>
        <img style="width: 80%; margin-left: 10%; margin-right: 10%;" src="source/assets/no-discover.png">
    </div>
    <br><br><br><br>
</div>
</div>
