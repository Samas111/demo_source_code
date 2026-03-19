<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(E_ALL);


require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once __DIR__ . '/../../secure/auth.php';

date_default_timezone_set('Europe/Prague');

$mysqli->set_charset('utf8mb4');

function displayOrDash(int $value): string
{
    return $value > 0 ? number_format($value, 0, ',', ' ') : '—';
}

$excludedOpenclassIds = [];
$nextOpenclass = null;

if (empty($_COOKIE['dancefy_token'])){
    header("/registration/register-server-logic/auto.php");
    exit;
}

if (!empty($_COOKIE['dancefy_token'])) {

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
    $u->bind_result($public_id);
    $u->fetch();
    $u->close();
}

$creators = [];

if (!empty($public_id)) {
    $stmt = $mysqli->prepare("
        SELECT 
            u.public_id,
            u.username,
            COALESCE(up.pfp_path, 'default.png') AS pfp_path,
            IF(uf.follower_public_id IS NULL, 0, 1) AS is_followed,
            (
                SELECT COUNT(*) 
                FROM user_follows f 
                WHERE f.followed_public_id = u.public_id
            ) AS followers_count
        FROM users u
        LEFT JOIN user_profile up 
            ON up.public_id = u.public_id
        LEFT JOIN user_follows uf 
            ON uf.followed_public_id = u.public_id
            AND uf.follower_public_id = ?
        WHERE u.is_creator = 1
          AND u.public_id != ?
          AND u.public_id != 'VxJNAQXBZx'
        ORDER BY 
            is_followed ASC,
            followers_count DESC
        LIMIT 4
    ");

    $stmt->bind_param('ss', $public_id, $public_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $creators[] = $row;
    }

    $stmt->close();
}

$openclasses = [];

if (!empty($public_id)) {
    $stmt = $mysqli->prepare("
        SELECT 
            oc.openclass_id,
            oc.title,
            oc.date,
            oc.start_time,
            oc.end_time,
            oc.cover_image,
            oc.public_id AS creator_public_id,
            oc.created_at,
            u.username
        FROM openclasses oc
        JOIN user_follows uf 
            ON uf.followed_public_id = oc.public_id
            AND uf.follower_public_id = ?
        JOIN users u 
            ON u.public_id = oc.public_id
        WHERE
            STR_TO_DATE(
                CONCAT(oc.date, ' ', oc.end_time),
                '%Y-%m-%d %H:%i'
            ) > NOW()
        ORDER BY oc.created_at DESC
        LIMIT 2
    ");

    $stmt->bind_param('s', $public_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $openclasses[] = $row;
    }

    $stmt->close();

        $favoriteOpenclasses = [];

    $sql = "
    SELECT 
        oc.openclass_id,
        oc.title,
        oc.date,
        oc.start_time,
        oc.cover_image,
        oc.price,
        oc.public_id AS creator_public_id,
        u.username,
        COALESCE(up.pfp_path, 'default.png') AS author_pfp,

        (
            COALESCE((
                SELECT COUNT(*) 
                FROM openclass_registrations r 
                WHERE r.openclass_id = oc.openclass_id
            ), 0)
            +
            COALESCE((
                SELECT COUNT(*) 
                FROM openclass_registrations_web rw 
                WHERE rw.openclass_id = oc.openclass_id
            ), 0)
        ) AS total_registrations,

        (
            SELECT COUNT(*)
            FROM user_follows uf
            WHERE uf.followed_public_id = oc.public_id
        ) AS follower_count

    FROM openclasses oc
    JOIN users u 
        ON u.public_id = oc.public_id
    LEFT JOIN user_profile up
        ON up.public_id = u.public_id

    WHERE STR_TO_DATE(
            CONCAT(oc.date, ' ', oc.end_time),
            '%Y-%m-%d %H:%i'
        ) > NOW()
    ";

    $params = [];
    $types = '';

    if (!empty($excludedOpenclassIds)) {
        $placeholders = implode(',', array_fill(0, count($excludedOpenclassIds), '?'));
        $sql .= " AND oc.openclass_id NOT IN ($placeholders)";
        $types = str_repeat('s', count($excludedOpenclassIds));
        $params = $excludedOpenclassIds;
    }

    $sql .= "
        ORDER BY 
            total_registrations DESC,
            follower_count DESC
        LIMIT 2
    ";



    $stmt = $mysqli->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if ((int)$row['total_registrations'] > 0) {
            $favoriteOpenclasses[] = $row;
            $excludedOpenclassIds[] = $row['openclass_id'];
        }
    }

    $stmt->close();
    $shownMap = [];

    foreach ($openclasses as $oc) {
        if (!isset($shownMap[$oc['openclass_id']])) {
            $shownMap[$oc['openclass_id']] = true;
            $excludedOpenclassIds[] = $oc['openclass_id'];
        }
    }
    
    $soonOpenclasses = [];

    $stmt = $mysqli->prepare("
        SELECT 
            oc.openclass_id,
            oc.title,
            oc.date,
            oc.start_time,
            oc.end_time,
            oc.cover_image,
            oc.price,
            oc.public_id AS creator_public_id,
            u.username,
            COALESCE(up.pfp_path, 'default.png') AS author_pfp
        FROM openclasses oc
        JOIN users u 
            ON u.public_id = oc.public_id
        LEFT JOIN user_profile up
            ON up.public_id = u.public_id
        WHERE
            STR_TO_DATE(
                CONCAT(oc.date, ' ', oc.start_time),
                '%Y-%m-%d %H:%i'
            ) > NOW()
        ORDER BY 
            STR_TO_DATE(
                CONCAT(oc.date, ' ', oc.start_time),
                '%Y-%m-%d %H:%i'
            ) ASC
        LIMIT 2
    ");

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $soonOpenclasses[] = $row;

        if (!isset($shownMap[$row['openclass_id']])) {
            $shownMap[$row['openclass_id']] = true;
            $excludedOpenclassIds[] = $row['openclass_id'];
        }
    }
    $stmt->close();

    // ==============================
    // PROZKOUMAT DALŠÍ (the rest)
    // ==============================

    $restOpenclasses = [];

    $sql = "
        SELECT 
            oc.openclass_id,
            oc.title,
            oc.date,
            oc.start_time,
            oc.cover_image,
            oc.address,
            oc.price,
            oc.public_id AS creator_public_id,
            u.username,
            COALESCE(up.pfp_path, 'default.png') AS author_pfp
        FROM openclasses oc
        JOIN users u 
            ON u.public_id = oc.public_id
        LEFT JOIN user_profile up
            ON up.public_id = u.public_id
        WHERE
            STR_TO_DATE(
                CONCAT(oc.date, ' ', oc.start_time),
                '%Y-%m-%d %H:%i'
            ) > NOW()
    ";

    $params = [];
    $types = '';

    if (!empty($excludedOpenclassIds)) {
        $placeholders = implode(',', array_fill(0, count($excludedOpenclassIds), '?'));
        $sql .= " AND oc.openclass_id NOT IN ($placeholders)";
        $types = str_repeat('s', count($excludedOpenclassIds));
        $params = $excludedOpenclassIds;
    }

    $sql .= "
        ORDER BY 
            STR_TO_DATE(
                CONCAT(oc.date, ' ', oc.start_time),
                '%Y-%m-%d %H:%i'
            ) ASC
    ";

    $stmt = $mysqli->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $restOpenclasses[] = $row;
    }

    $stmt->close();
}

$noOpenclasses =
empty($openclasses)
&& empty($soonOpenclasses)
&& empty($restOpenclasses);

$activeOpenclassesCount = 0;

$stmt = $mysqli->prepare("
    SELECT COUNT(*) 
    FROM openclasses
    WHERE STR_TO_DATE(
        CONCAT(date, ' ', end_time),
        '%Y-%m-%d %H:%i:%s'
    ) > NOW()
");

$stmt->execute();
$stmt->bind_result($activeOpenclassesCount);
$stmt->fetch();
$stmt->close();


$trendId = null;
$trendTitle = '';
$trendTotal = 0;

$dopamineText = '';
$dopamineType = '';

$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');

/* =========================
   1) REGISTRATIONS TODAY
========================= */

$registrationsToday = 0;

$stmt = $mysqli->prepare("
    SELECT 
        (
            COALESCE((
                SELECT COUNT(*) 
                FROM openclass_registrations 
                WHERE created_at BETWEEN ? AND ?
            ), 0)
            +
            COALESCE((
                SELECT COUNT(*) 
                FROM openclass_registrations_web 
                WHERE created_at BETWEEN ? AND ?
            ), 0)
        ) AS total
");
$stmt->bind_param('ssss', $todayStart, $todayEnd, $todayStart, $todayEnd);
$stmt->execute();
$stmt->bind_result($registrationsToday);
$stmt->fetch();
$stmt->close();

if ($registrationsToday > 0) {
    $dopamineText = "<strong>{$registrationsToday}</strong> tanečníků se dnes <strong>přihlásilo</strong> na openclass 🔥";
    $dopamineType = 'registrations';
}

/* =========================
   2) NEW OPENCLASSES TODAY
========================= */

if ($registrationsToday === 0) {

    $newOpenclassesToday = 0;

    $stmt = $mysqli->prepare("
        SELECT COUNT(*) 
        FROM openclasses
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->bind_param('ss', $todayStart, $todayEnd);
    $stmt->execute();
    $stmt->bind_result($newOpenclassesToday);
    $stmt->fetch();
    $stmt->close();

    if ($newOpenclassesToday > 0) {
        $dopamineText = "<strong>{$newOpenclassesToday}</strong> nové openclass dnes přibyly 🚀";
        $dopamineType = 'openclasses';
    }

    /* =========================
       3) NEW POSTS TODAY
    ========================= */

    if ($newOpenclassesToday === 0) {

        $newPostsToday = 0;

        $stmt = $mysqli->prepare("
            SELECT COUNT(*) 
            FROM posts
            WHERE created_at BETWEEN ? AND ?
        ");
        $stmt->bind_param('ss', $todayStart, $todayEnd);
        $stmt->execute();
        $stmt->bind_result($newPostsToday);
        $stmt->fetch();
        $stmt->close();

        if ($newPostsToday > 0) {
            $dopamineText = "<strong>{$newPostsToday}</strong> nové příspěvky dnes přibyly 💬";
            $dopamineType = 'posts';
        }
    }
}

/* =========================
   4) TRENDING OPENCLASS
========================= */

if (empty($dopamineText)) {

    $stmt = $mysqli->prepare("
        SELECT 
            oc.openclass_id,
            oc.title,
            (
                COALESCE((SELECT COUNT(*) FROM openclass_registrations r WHERE r.openclass_id = oc.openclass_id),0)
                +
                COALESCE((SELECT COUNT(*) FROM openclass_registrations_web rw WHERE rw.openclass_id = oc.openclass_id),0)
            ) AS total_reg
        FROM openclasses oc
        WHERE STR_TO_DATE(CONCAT(oc.date,' ',oc.start_time),'%Y-%m-%d %H:%i') >= CURDATE()
        ORDER BY total_reg DESC
        LIMIT 1
    ");

    $stmt->execute();
    $stmt->bind_result($trendId, $trendTitle, $trendTotal);
    $stmt->fetch();
    $stmt->close();

    if (!empty($trendId) && $trendTotal > 0) {
        $safeTitle = htmlspecialchars(mb_strimwidth($trendTitle, 0, 25, '…', 'UTF-8'));
        $dopamineText = "🔥 <strong>{$safeTitle}</strong> právě trenduje";
        $dopamineType = 'trending';
    } else {
        $dopamineText = "Zatím je klid… buď první kdo rozjede hype ⚡";
        $dopamineType = 'empty';
    }
}

function resolveOpenclassBadge(array $oc): ?array
{
    $capacity = (int)($oc['capacity'] ?? 0);
    $regs = (int)($oc['total_registrations'] ?? 0);

    if ($capacity > 0 && $regs >= $capacity) {
        return ['label' => 'Sold Out', 'class' => 'badge-trending'];
    }

    if ($capacity > 0) {
        $left = $capacity - $regs;
        if ($left > 0 && $left <= 5) {
            return ['label' => 'Last Spots', 'class' => 'badge-trending'];
        }
    }

    $createdAt = strtotime($oc['created_at'] ?? '');
    if ($createdAt && $createdAt >= strtotime('-3 days')) {
        return ['label' => 'Nový', 'class' => 'badge-trending'];
    }

    if ($regs >= 10) {
        return ['label' => 'Populární', 'class' => 'badge-trending'];
    }

    return null;
}

/* =========================
   5) UNREAD MESSAGES CHECK
========================= */
$unreadCount = 0;
$latestUnreadMsg = null;

if (!empty($public_id)) {
    $msgStmt = $mysqli->prepare("
        SELECT m.message, m.sender_public_id, u.username, COALESCE(up.pfp_path, 'default.png') as pfp_path
        FROM dms m
        JOIN users u ON m.sender_public_id = u.public_id
        LEFT JOIN user_profile up ON u.public_id = up.public_id
        WHERE m.receiver_public_id = ? AND m.is_read = 0
        ORDER BY m.created_at DESC
    ");
    $msgStmt->bind_param('s', $public_id);
    $msgStmt->execute();
    $msgResult = $msgStmt->get_result();
    
    $unreadCount = $msgResult->num_rows;
    if ($unreadCount > 0) {
        $latestUnreadMsg = $msgResult->fetch_assoc();
    }
    $msgStmt->close();
}

?>

    <div class="container-overflow">
        <?php if ($unreadCount > 0 && $latestUnreadMsg): ?>
            <div class="unread-messages-alert" onclick="window.location='messages/chat.php?public_id=<?= htmlspecialchars($latestUnreadMsg['sender_public_id']) ?>'">
                <div class="msg-flex">
                    <div class="msg-pfp">
                        <img src="<?= htmlspecialchars($latestUnreadMsg['pfp_path']) ?>" alt="pfp">
                        
                        <span class="msg-badge">
                            <?php if ($unreadCount > 1): ?>
                                +<?= $unreadCount - 1 ?>
                            <?php else: ?>
                                1
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="msg-content">
                        <div class="msg-sender"><?= htmlspecialchars($latestUnreadMsg['username']) ?></div>
                        <div class="msg-text"><?= htmlspecialchars(mb_strimwidth($latestUnreadMsg['message'], 0, 35, '...', 'UTF-8')) ?></div>
                    </div>

                    <div class="msg-unread-indicator">
                        <div class="dot"></div>
                    </div>
                </div>
            </div>
        <?php else: ?>
        <div class="dopamine-message">
            <?= $dopamineText ?>
        </div>
        <div class="follow-creators">
            <?php foreach ($creators as $c): ?>
                <div class="creator">
                    <div class="pfp" onclick="window.location='view2.php?public_id=<?php echo htmlspecialchars($c['public_id'])?>'">
                        <img src="<?= htmlspecialchars($c['pfp_path']) ?>" alt="pfp" loading="lazy">
                    </div>
                    <div class="name" onclick="window.location='view2.php?public_id=<?php echo htmlspecialchars($c['public_id'])?>'">
                        <?= htmlspecialchars($c['username']) ?>
                    </div>
                    <?php if (!$c['is_followed']): ?>
                        <button class="follow" data-public-id="<?= htmlspecialchars($c['public_id']) ?>">Sledovat</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="creator search">
                <div class="pfp" onclick="window.location.href='creators.php'">
                    <img src="source-dancer/assets/follow-lupa.png" alt="">
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($noOpenclasses): ?>
        <div class="no-openclass">
            <img 
                src="/source-dancer/assets/no-op.png"
                alt="No openclasses"
                loading="lazy"
            >
        </div>
    <?php endif; ?>

    <?php if (!empty($openclasses)): ?>
        <div class="soon">
            <h3>Od Tvůrců co sleduješ</h3>

            <div class="soon-flex">
                <?php foreach ($openclasses as $oc): ?>
                    <div class="openclass-soon"
                        onclick="window.location='openclass-dancer.php?id=<?= htmlspecialchars($oc['openclass_id']) ?>'">
                        <div class="soon-cover">
                            <img 
                                src="uploads/openclasses/<?= htmlspecialchars($oc['cover_image']) ?>" 
                                alt="cover"
                                loading="lazy"
                            >
                        </div>
                        <div class="soon-description">
                            <span>
                                <?= htmlspecialchars(mb_strimwidth($oc['title'], 0, 12, '…', 'UTF-8')) ?>
                            </span>

                            <p>
                                <?= date('d.m.', strtotime($oc['date'])) ?>
                                | <?= htmlspecialchars($oc['start_time']) ?>
                            </p>

                            <div class="author-soon">
                                <div class="username">
                                    <?= htmlspecialchars($oc['username']) ?>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>


    <?php if (!empty($favoriteOpenclasses)): ?>
        <div class="discover-op">
            <h3>Populární na Dancefy</h3>
            <div class="dis-flex">
                <?php foreach ($favoriteOpenclasses as $index => $oc): ?>
                    <div class="open-card"
                        onclick="window.location='openclass-dancer.php?id=<?= htmlspecialchars($oc['openclass_id']) ?>'">

                        <div class="thumbnail-wrapper">
                            <img class="thumbnail" 
                                src="uploads/openclasses/<?= htmlspecialchars($oc['cover_image']) ?>">

                            <?php 
                            $badge = resolveOpenclassBadge($oc); 
                            if ($badge): 
                            ?>
                                <div class="trending-badge <?= $badge['class'] ?>">
                                    <?= $badge['label'] ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="dis-pad">

                            <span><?= htmlspecialchars(mb_strimwidth($oc['title'], 0, 14, '…', 'UTF-8')) ?></span>
                            <p>
                                <?= date('d.m.', strtotime($oc['date'])) ?>
                                <?= htmlspecialchars($oc['start_time']) ?>
                                • <?= htmlspecialchars(mb_strimwidth($oc['price'], 0, 14, '…', 'UTF-8')) ?>Kč
                            </p>
                            <div class="main-button" onclick="window.location='openclass-listing.php?id=<?= htmlspecialchars(mb_strimwidth($oc['openclass_id'], 0, 14, '…', 'UTF-8')) ?>'">Rezervovat místo</div>
                            <div class="author-dis">
                                <img 
                                    src="<?= htmlspecialchars($oc['author_pfp'] ?? 'default.png') ?>" 
                                    alt="pfp"
                                    loading="lazy"
                                >
                                <div class="username"><?= htmlspecialchars($oc['username']) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>


    <?php if (!empty($soonOpenclasses)): ?>
        <div class="soon">
            <h3>Brzy se koná</h3>
            <div class="soon-flex">
                <?php foreach ($soonOpenclasses as $oc): ?>
                <?php
                $eventTimestamp = strtotime($oc['date'] . ' ' . ($oc['start_time'] ?? '00:00'));
                $now = time();
                $diff = $eventTimestamp - $now;
                ?>
                <div class="openclass-soon"
                    onclick="window.location='openclass-dancer.php?id=<?= htmlspecialchars($oc['openclass_id']) ?>'">
                    <div class="soon-cover">
                        <img 
                            src="uploads/openclasses/<?= htmlspecialchars($oc['cover_image']) ?>" 
                            alt="cover"
                            loading="lazy"
                        >
                    </div>
                    <div class="soon-description">
                        <span>
                            <?= htmlspecialchars(mb_strimwidth($oc['title'], 0, 12, '…', 'UTF-8')) ?>
                        </span>
                        <p class="action-soon">
                            <?php
                            if ($diff <= 0) {
                                echo 'Proběhlo';
                            } else {
                                $days = floor($diff / 86400);
                                $hours = floor(($diff % 86400) / 3600);

                                if ($days > 0) {
                                    echo 'Za ' . $days . ' dní';
                                } elseif ($hours > 0) {
                                    echo 'Za ' . $hours . ' hodin';
                                } else {
                                    echo 'Uzavřeno';
                                }
                            }
                            ?>
                        </p>
                        <div class="author-soon">
                            <div class="username">
                                <?= date('d.m.', strtotime($oc['date'])) ?>
                                | <?= htmlspecialchars($oc['start_time']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($restOpenclasses)): ?>
        <div class="rest">
            <h3>Prozkoumat další</h3>

            <div class="list-rest">
                <?php foreach ($restOpenclasses as $oc): ?>
                    <div class="openclass-card"
                        onclick="window.location='openclass-dancer.php?id=<?= htmlspecialchars($oc['openclass_id']) ?>'">

                        <div class="openclass-content">
                            <h3 class="openclass-title-dis">
                                <?= htmlspecialchars(mb_strimwidth($oc['title'], 0, 25, '…', 'UTF-8'))?>
                            </h3>

                            <div class="openclass-info">
                                <span><?= date('d.m.', strtotime($oc['date'])) ?></span>
                                <?php if (!empty($oc['address'])): ?>
                                    <span><?= htmlspecialchars(mb_strimwidth($oc['address'], 0, 18, '…', 'UTF-8')) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="author-dis">
                                <img
                                    src="<?= htmlspecialchars($oc['author_pfp'] ?? 'default.png') ?>"
                                    alt="pfp"
                                    loading="lazy"
                                >
                                <div class="username">
                                    <?= htmlspecialchars($oc['username'] ?? '') ?>
                                </div>
                            </div>
                        </div>

                        <div class="openclass-image">
                            <img
                                src="uploads/openclasses/<?= htmlspecialchars($oc['cover_image']) ?>"
                                alt="cover"
                                loading="lazy"
                            >
                                <?php 
                                $badge = resolveOpenclassBadge($oc); 
                                if ($badge): 
                                ?>
                                    <div class="trending-badge <?= $badge['class'] ?>">
                                        <?= $badge['label'] ?>
                                    </div>
                                <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?><br><br><br>
</div>
