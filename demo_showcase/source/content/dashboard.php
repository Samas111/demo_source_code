<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(E_ALL);


require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once __DIR__ . '/../../secure/auth.php';
require_once __DIR__ . '/../../secure/require_creator.php';
require_once __DIR__ . '/../../languages/loader.php';


date_default_timezone_set('Europe/Prague');

function displayOrDash(int $value): string
{
    return $value > 0 ? number_format($value, 0, ',', ' ') : '—';
}

$nextOpenclass = null;

if (empty($_COOKIE['dancefy_token'])){
    header("/registration/register-server-logic/auto.php");
    exit;
}

if (!empty($_COOKIE['dancefy_token'])) {

    $tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

    $u = $mysqli->prepare("
        SELECT u.public_id, u.username, up.iban
        FROM user_tokens ut
        JOIN users u ON u.user_id = ut.user_id
        LEFT JOIN user_profile up ON up.public_id = u.public_id
        WHERE ut.token_hash = ?
        LIMIT 1
    ");
    $u->bind_param('s', $tokenHash);
    $u->execute();
    $u->bind_result($public_id, $username, $iban);
    $u->fetch();
    $u->close();

    $usernameSafe = htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8');

    /* ----------------------------
    DAILY DOPAMINE ENGINE
    Priority:
    1. Registrations today
    2. Followers today
    3. Views today
    4. Fallback welcome
    ---------------------------- */

    $todayRegistrations = 0;
    $todayFollowers = 0;
    $todayViews = 0;

    if (!empty($public_id)) {

        /* Registrations today */
        $regTodayStmt = $mysqli->prepare("
            SELECT
                (
                    SELECT COUNT(*)
                    FROM openclass_registrations r
                    WHERE r.storno = 0
                    AND DATE(r.created_at) = CURDATE()
                    AND r.openclass_id IN (
                        SELECT openclass_id FROM openclasses WHERE public_id = ?
                    )
                ) +
                (
                    SELECT COUNT(*)
                    FROM openclass_registrations_web rw
                    WHERE rw.verified = 1
                    AND DATE(rw.created_at) = CURDATE()
                    AND rw.openclass_id IN (
                        SELECT openclass_id FROM openclasses WHERE public_id = ?
                    )
                )
        ");
        $regTodayStmt->bind_param('ss', $public_id, $public_id);
        $regTodayStmt->execute();
        $regTodayStmt->bind_result($todayRegistrations);
        $regTodayStmt->fetch();
        $regTodayStmt->close();

        /* Followers today */
        $followTodayStmt = $mysqli->prepare("
            SELECT COUNT(*)
            FROM user_follows
            WHERE followed_public_id = ?
            AND DATE(created_at) = CURDATE()
        ");
        $followTodayStmt->bind_param('s', $public_id);
        $followTodayStmt->execute();
        $followTodayStmt->bind_result($todayFollowers);
        $followTodayStmt->fetch();
        $followTodayStmt->close();

        /* Views today */
        $viewsTodayStmt = $mysqli->prepare("
            SELECT COUNT(*)
            FROM openclass_views
            WHERE DATE(viewed_at) = CURDATE()
            AND openclass_id IN (
                SELECT openclass_id FROM openclasses WHERE public_id = ?
            )
        ");
        $viewsTodayStmt->bind_param('s', $public_id);
        $viewsTodayStmt->execute();
        $viewsTodayStmt->bind_result($todayViews);
        $viewsTodayStmt->fetch();
        $viewsTodayStmt->close();
    }

    /* Build message */
    $dopamineMessage = "Vítej zpátky @" . $usernameSafe;
    if ($todayRegistrations > 0) {
        $dopamineMessage = "🔥 Dnes máš # nových registrací!";
    } elseif ($todayFollowers > 0) {
        $dopamineMessage = "🚀 Dnes tě začalo sledovat $todayFollowers lidí!";
    } elseif ($todayViews > 0) {
        $dopamineMessage = "👀 Dnes tvoje lekce získaly $todayViews zobrazení!";
    }

    /* ----------------------------
    CREATOR MONTHLY REVENUE
    ---------------------------- */
    $monthRevenue = 0;

    if ($public_id) {
        $monthlyStmt = $mysqli->prepare("
            SELECT
                COALESCE(SUM(oc.price * (
                    (
                        SELECT COUNT(*) 
                        FROM openclass_registrations r
                        WHERE r.openclass_id = oc.openclass_id
                        AND r.storno = 0
                    ) +
                    (
                        SELECT COUNT(*) 
                        FROM openclass_registrations_web rw
                        WHERE rw.openclass_id = oc.openclass_id
                        AND rw.verified = 1
                    )
                )), 0)
            FROM openclasses oc
            WHERE oc.public_id = ?
            AND oc.date LIKE CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m'), '%')
        ");

        $monthlyStmt->bind_param('s', $public_id);
        $monthlyStmt->execute();
        $monthlyStmt->bind_result($monthRevenue);
        $monthlyStmt->fetch();
        $monthlyStmt->close();
    }
    /* ----------------------------
    CREATOR TOTAL FOLLOWERS
    ---------------------------- */
    $creatorTotalFollowers = 0;

    if ($public_id) {
        $fStmt = $mysqli->prepare("
            SELECT COUNT(*)
            FROM user_follows
            WHERE followed_public_id = ?
        ");
        $fStmt->bind_param('s', $public_id);
        $fStmt->execute();
        $fStmt->bind_result($creatorTotalFollowers);
        $fStmt->fetch();
        $fStmt->close();
    }

    /* ----------------------------
    CREATOR TOTAL VIEWS (ALL TIME)
    ---------------------------- */
    $viewsStmt = $mysqli->prepare("
        SELECT COUNT(*)
        FROM openclass_views
        WHERE openclass_id IN (
            SELECT openclass_id
            FROM openclasses
            WHERE public_id = ?
        )
    ");
    $viewsStmt->bind_param('s', $public_id);
    $viewsStmt->execute();
    $viewsStmt->bind_result($creatorTotalViews);
    $viewsStmt->fetch();
    $viewsStmt->close();

    if ($public_id) {

        $openclasses = [];

        $stmt = $mysqli->prepare("
            SELECT
                openclass_id,
                title,
                date,
                start_time,
                end_time,
                price,
                capacity
            FROM openclasses
            WHERE public_id = ?
            AND STR_TO_DATE(CONCAT(date,' ',start_time), '%Y-%m-%d %H:%i') > NOW()
            ORDER BY STR_TO_DATE(CONCAT(date,' ',start_time), '%Y-%m-%d %H:%i') ASC
        ");

        $stmt->bind_param('s', $public_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $eventTs = strtotime($row['date'].' '.$row['start_time']);
            $diff = max(0, $eventTs - time());

            $days = floor($diff / 86400);
            $hours = floor(($diff % 86400) / 3600);
            $minutes = floor(($diff % 3600) / 60);

            $row['countdown'] = "$days dní, $hours hodin, $minutes minut";

            $openclasses[] = $row;
        }

        $stmt->close();

        foreach ($openclasses as &$oc) {

            $regStmt = $mysqli->prepare("
                SELECT
                    (
                        SELECT COUNT(*) FROM openclass_registrations
                        WHERE openclass_id = ? AND storno = 0
                    ) +
                    (
                        SELECT COUNT(*) FROM openclass_registrations_web
                        WHERE openclass_id = ? AND verified = 1
                    )
            ");

            $regStmt->bind_param('ss', $oc['openclass_id'], $oc['openclass_id']);
            $regStmt->execute();
            $regStmt->bind_result($attendance);
            $regStmt->fetch();
            $regStmt->close();

            $oc['attendance'] = (int)$attendance;
            $oc['revenue'] = $attendance * (int)$oc['price'];
        }
        unset($oc);

    }
}

$yesterdayFollowers = 0;

if (!empty($public_id)) {
    $stmt = $mysqli->prepare("
        SELECT COUNT(*)
        FROM user_follows
        WHERE followed_public_id = ?
        AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ");
    $stmt->bind_param('s', $public_id);
    $stmt->execute();
    $stmt->bind_result($yesterdayFollowers);
    $stmt->fetch();
    $stmt->close();
}

$yesterdayViews = 0;

if (!empty($public_id)) {
    $stmt = $mysqli->prepare("
        SELECT COUNT(*)
        FROM openclass_views
        WHERE DATE(viewed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        AND openclass_id IN (
            SELECT openclass_id FROM openclasses WHERE public_id = ?
        )
    ");
    $stmt->bind_param('s', $public_id);
    $stmt->execute();
    $stmt->bind_result($yesterdayViews);
    $stmt->fetch();
    $stmt->close();
}

$yesterdayRegistrations = 0;

if (!empty($public_id)) {
    $stmt = $mysqli->prepare("
        SELECT
            (
                SELECT COUNT(*)
                FROM openclass_registrations r
                WHERE r.storno = 0
                AND DATE(r.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                AND r.openclass_id IN (
                    SELECT openclass_id FROM openclasses WHERE public_id = ?
                )
            ) +
            (
                SELECT COUNT(*)
                FROM openclass_registrations_web rw
                WHERE rw.verified = 1
                AND DATE(rw.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                AND rw.openclass_id IN (
                    SELECT openclass_id FROM openclasses WHERE public_id = ?
                )
            )
    ");
    $stmt->bind_param('ss', $public_id, $public_id);
    $stmt->execute();
    $stmt->bind_result($yesterdayRegistrations);
    $stmt->fetch();
    $stmt->close();
}


function calculateGrowthBadge(?int $today, ?int $yesterday): ?array
{
    $today = (int)($today ?? 0);
    $yesterday = (int)($yesterday ?? 0);

    // If nothing happened today → no badge
    if ($today <= 0) {
        return null;
    }

    // If yesterday had nothing → no baseline → no badge
    if ($yesterday <= 0) {
        return null;
    }

    // No change
    if ($today === $yesterday) {
        return null;
    }

    $percent = (($today - $yesterday) / $yesterday) * 100;
    $percent = round($percent);

    if ($percent <= 0) {
        return null;
    }

    return [
        'class' => 'profit',
        'label' => '+' . $percent . '%'
    ];
}


/* ----------------------------
CREATOR TOTAL REGISTRATIONS (ALL TIME)
---------------------------- */
$creatorTotalRegistrations = 0;

if ($public_id) {
    $regTotalStmt = $mysqli->prepare("
        SELECT
            (
                SELECT COUNT(*)
                FROM openclass_registrations r
                WHERE r.storno = 0
                AND r.openclass_id IN (
                    SELECT openclass_id
                    FROM openclasses
                    WHERE public_id = ?
                )
            ) +
            (
                SELECT COUNT(*)
                FROM openclass_registrations_web rw
                WHERE rw.verified = 1
                AND rw.openclass_id IN (
                    SELECT openclass_id
                    FROM openclasses
                    WHERE public_id = ?
                )
            )
    ");
    $regTotalStmt->bind_param('ss', $public_id, $public_id);
    $regTotalStmt->execute();
    $regTotalStmt->bind_result($creatorTotalRegistrations);
    $regTotalStmt->fetch();
    $regTotalStmt->close();
}

$viewsBadge = calculateGrowthBadge($todayViews, $yesterdayViews);
$followersBadge = calculateGrowthBadge($todayFollowers, $yesterdayFollowers);
$registrationsBadge = calculateGrowthBadge($todayRegistrations, $yesterdayRegistrations);


$czMonths = [
    1 => 'Lednu',
    2 => 'Únoru',
    3 => 'Březenu',
    4 => 'Dubnu',
    5 => 'Květnu',
    6 => 'Červnu',
    7 => 'Červenci',
    8 => 'Srpnu',
    9 => 'Září',
    10 => 'Říjnu',
    11 => 'Listopadu',
    12 => 'Prosinci'
];

$currentMonthCz = $czMonths[(int)date('n')];

$levels = [
    [
        'name' => '1. Začínající tvůrce',
        'boost' => 1,
        'followers_required' => 10,
        'registrations_required' => 5,
        'needs_share_post' => true
    ],
    [
        'name' => '2. Aktivní tvůrce',
        'boost' => 10,
        'followers_required' => 25,
        'registrations_required' => 10,
        'needs_share_post' => false
    ],
    [
        'name' => '3. Rostoucí tvůrce',
        'boost' => 15,
        'followers_required' => 50,
        'registrations_required' => 15,
        'needs_share_post' => false
    ],
    [
        'name' => '4. Pokročilý tvůrce',
        'boost' => 20,
        'followers_required' => 75,
        'registrations_required' => 25,
        'needs_share_post' => false
    ],
    [
        'name' => '5. Seriózní tvůrce',
        'boost' => 25,
        'followers_required' => 100,
        'registrations_required' => 50,
        'needs_share_post' => false
    ],
];

$currentLevelIndex = null;

foreach ($levels as $index => $level) {

    $meetsFollowers = $creatorTotalFollowers >= $level['followers_required'];
    $meetsRegistrations = $creatorTotalRegistrations >= $level['registrations_required'];

    if ($meetsFollowers && $meetsRegistrations) {
        continue;
    }

    $currentLevelIndex = $index;
    break;
}

if ($currentLevelIndex === null) {
    // User reached max level → hide card
}

$hasPost = false;

if ($public_id) {
    $postStmt = $mysqli->prepare("
        SELECT COUNT(*)
        FROM posts
        WHERE public_id = ?
    ");
    $postStmt->bind_param('s', $public_id);
    $postStmt->execute();
    $postStmt->bind_result($postCount);
    $postStmt->fetch();
    $postStmt->close();

    $hasPost = $postCount > 0;
}

function renderBottomLine(int $value): string
{
    if ($value > 0) {
        return '<div class="bottom-line">+' . $value . ' dnes</div>';
    }

    return '';
}

$egoMessage = null;

if (!empty($public_id)) {

    /* ---------------------------------
       1️⃣ TOP 3 ACTIVE OPENCLASS BY REGISTRATIONS
    --------------------------------- */

    $stmt = $mysqli->prepare("
        SELECT oc.openclass_id,
               COUNT(*) AS reg_count
        FROM openclasses oc
        LEFT JOIN openclass_registrations r
            ON r.openclass_id = oc.openclass_id
            AND r.storno = 0
        WHERE oc.public_id = ?
        AND STR_TO_DATE(CONCAT(oc.date,' ',oc.start_time), '%Y-%m-%d %H:%i') > NOW()
        GROUP BY oc.openclass_id
        ORDER BY reg_count DESC
        LIMIT 3
    ");
    $stmt->bind_param('s', $public_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $egoMessage = "🔥 Tvoje OpenClass patří mezi TOP 3.";
    }

    $stmt->close();


    /* ---------------------------------
       2️⃣ TOP 5 MOST FOLLOWED CREATORS
    --------------------------------- */

    if (!$egoMessage) {

        $stmt = $mysqli->prepare("
            SELECT u.public_id,
                   COUNT(f.followed_public_id) AS follower_count
            FROM users u
            LEFT JOIN user_follows f
                ON f.followed_public_id = u.public_id
            WHERE u.is_creator = 1
            GROUP BY u.public_id
            ORDER BY follower_count DESC
            LIMIT 5
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if ($row['public_id'] === $public_id) {
                $egoMessage = "⭐ Patříš mezi TOP 5 nejvíce sledovaných tvůrců.";
                break;
            }
        }

        $stmt->close();
    }


    /* ---------------------------------
       3️⃣ TOP 5 MOST VIEWED OPENCLASS
    --------------------------------- */

    if (!$egoMessage) {

        $stmt = $mysqli->prepare("
            SELECT oc.public_id,
                   COUNT(v.id) AS view_count
            FROM openclass_views v
            JOIN openclasses oc
                ON oc.openclass_id = v.openclass_id
            GROUP BY oc.public_id
            ORDER BY view_count DESC
            LIMIT 5
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if ($row['public_id'] === $public_id) {
                $egoMessage = "👀 Tvoje lekce patří mezi TOP 5 nejvíce zobrazené.";
                break;
            }
        }

        $stmt->close();
    }
}

?>
<!-- 
<div class="main-info">
    <div class="center">
        <div class="cash-display">
            <span>
                <?= $monthRevenue > 0
                    ? number_format((int)$monthRevenue, 0, ',', ' ') . ' Kč'
                    : '—'
                ?>
            </span>
            <p><?= $T['summary'] ?> <?= $currentMonthCz ?></p>
            <p style="margin: 0; padding: 0; font-size: 0.7rem; opacity: 0.3; font-weight: 400; padding-top: 5px;"><?= $T['private'] ?></p>
        </div>
    </div>
</div>
-->
<div class="container-overflow">
    <div class="dopamine-message">
        <?= htmlspecialchars($dopamineMessage, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php if ($egoMessage): ?>
        <div class="ego-message">
            <p><?= htmlspecialchars($egoMessage) ?></p>
        </div>
    <?php endif; ?>
    <div class="main-stats">
        <div class="main-stat-card">
            <?php if ($viewsBadge): ?>
                <div class="top-left-badge <?= $viewsBadge['class'] ?>">
                    <?= $viewsBadge['label'] ?>
                </div>
            <?php endif; ?>
            <span><?= displayOrDash((int)$creatorTotalViews) ?></span>
            <p><?= $T['views'] ?></p>
            <?= renderBottomLine((int)$todayViews); ?>
        </div>
        <div class="main-stat-card">
            <?php if ($followersBadge): ?>
                <div class="top-left-badge <?= $followersBadge['class'] ?>">
                    <?= $followersBadge['label'] ?>
                </div>
            <?php endif; ?>
            <span><?= displayOrDash((int)$creatorTotalFollowers) ?></span>
            <p><?= $T['followers'] ?></p>
            <?= renderBottomLine((int)$todayFollowers); ?>
        </div>
        <div class="main-stat-card">
            <?php if ($registrationsBadge): ?>
                <div class="top-left-badge <?= $registrationsBadge['class'] ?>">
                    <?= $registrationsBadge['label'] ?>
                </div>
            <?php endif; ?>
            <span><?= displayOrDash((int)$creatorTotalRegistrations) ?></span>
            <p><?= $T['registrations'] ?></p>
            <?= renderBottomLine((int)$todayRegistrations); ?>
        </div>
    </div>
    <div class="active-workshops">
        <?php if (!empty($openclasses)): ?>
            <?php foreach ($openclasses as $index => $oc): ?>
                <div class="active-workshop-card"
                    onclick="window.location.href='attendance.php?id=<?= urlencode($oc['openclass_id']) ?>'">
                    <?php if ($index === 0): ?>
                        <span><?= $T['in'] ?> <?= htmlspecialchars($oc['countdown']) ?></span>
                    <?php else: ?>
                        <span style="visibility: hidden; display: none;">next</span>
                    <?php endif; ?>
                    <h1><?= htmlspecialchars($oc['title']) ?></h1>
                    <p><?= date('d.m.', strtotime($oc['date'])) ?></p>
                    <p><?= htmlspecialchars($oc['start_time']) ?> - <?= htmlspecialchars($oc['end_time']) ?></p>
                    <div class="workshop-stats">
                        <div class="stat attendance">
                            <img src="source/assets/people.png">
                            <?= $oc['attendance'] ?> / <?= $oc['capacity'] ?>
                        </div>
                        <div class="stat cash">
                            <img src="source/assets/cash.png">
                            <?= number_format($oc['revenue'], 0, ',', ' ') ?> Kč
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <?php
                $ctaTitle = "Vytvoř novou OpenClass";
                $ctaSubtitle = "Zobrazí se ve feedu a otevře registrace.";

                if ($creatorTotalFollowers > 0) {
                    $ctaTitle = "Máš {$creatorTotalFollowers} sledujících.";
                    $ctaSubtitle = "Čekají na další OpenClass.";
                }
                if ($todayFollowers > 0) {
                    $ctaSubtitle = "Čekají na tvou další OpenClass.";
                }
                ?>

                <div class="add-openclass-container" onclick="window.location='post/listing.php'">
                    <div class="add-openclass-inner">
                        <div class="add-openclass-icon">+</div>
                        <div class="add-openclass-text">
                            <h3><?= htmlspecialchars($ctaTitle) ?></h3>
                            <p><?= htmlspecialchars($ctaSubtitle) ?></p>
                        </div>
                        <div class="add-openclass-button">
                            Vytvořit
                        </div>
                    </div>
                </div>
        <?php endif; ?>
    </div>
    <?php if ($currentLevelIndex !== null): 
        $level = $levels[$currentLevelIndex];

        $progressParts = [];
        $totalParts = 0;

        /* SHARE POST */
        if ($level['needs_share_post']) {
            $totalParts++;
            $progressParts[] = $hasPost ? 1 : 0;
        }

        /* REGISTRATIONS */
        if ($level['registrations_required'] > 0) {
            $totalParts++;
            $regProgress = min(
                $creatorTotalRegistrations / $level['registrations_required'],
                1
            );
            $progressParts[] = $regProgress;
        }

        /* FOLLOWERS */
        if ($level['followers_required'] > 0) {
            $totalParts++;
            $followProgress = min(
                $creatorTotalFollowers / $level['followers_required'],
                1
            );
            $progressParts[] = $followProgress;
        }

        /* FINAL PERCENT */
        $levelProgressPercent = 0;

        if ($totalParts > 0) {
            $levelProgressPercent = array_sum($progressParts) / $totalParts;
        }

        $levelProgressPercent = round($levelProgressPercent * 100);
    ?>
    <div class="creator-level-card">
        <div class="level-progress-bar">
            <div class="level-progress-fill" style="width: <?= $levelProgressPercent ?>%;"></div>
        </div>

        <div class="level-header">
            <div class="level-title">
                <?= htmlspecialchars($level['name']) ?>
            </div>
            <div class="level-boost">
                <span class="boost-value">+<?= (int)$level['boost'] ?>%</span> boost viditelnosti
            </div>
        </div>

        <div class="level-divider"></div>
        <div class="level-tasks">

            <?php if ($level['needs_share_post']): ?>
                <div class="task <?= $hasPost ? 'completed' : 'active' ?>">
                    <div class="task-icon">
                        <svg viewBox="0 0 24 24">
                            <?php if ($hasPost): ?>
                                <path d="M5 13l4 4L19 7" />
                            <?php else: ?>
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 7v5l3 2" />
                            <?php endif; ?>
                        </svg>
                    </div>
                    <div class="task-text">
                        <div class="task-title">Sdílej svůj první příspěvek</div>
                        <div class="task-desc">Otevři si dveře k prvním sledujícím</div>
                    </div>
                </div>
            <?php endif; ?>
                <div class="task <?= $creatorTotalRegistrations >= $level['registrations_required'] ? 'completed' : 'active' ?>">
                    <div class="task-icon">
                        <svg viewBox="0 0 24 24">
                            <?php if ($creatorTotalRegistrations >= $level['registrations_required']): ?>
                                <path d="M5 13l4 4L19 7" />
                            <?php else: ?>
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 7v5l3 2" />
                            <?php endif; ?>
                        </svg>
                    </div>
                    <div class="task-text">
                        <div class="task-title">
                            Získej Registrace 
                            <span class="progress">
                                <?php
                                $displayRegistrations = min(
                                    $creatorTotalRegistrations,
                                    $level['registrations_required']
                                );
                                ?>
                                <?= (int)$displayRegistrations ?>/<?= (int)$level['registrations_required'] ?>
                            </span>
                        </div>
                        <div class="task-desc">Přitahuj tanečníky na své OpenClasses</div>
                    </div>
                </div>
                <div class="task <?= $creatorTotalFollowers >= $level['followers_required'] ? 'completed' : 'active' ?>">
                    <div class="task-icon">
                        <svg viewBox="0 0 24 24">
                            <?php if ($creatorTotalFollowers >= $level['followers_required']): ?>
                                <path d="M5 13l4 4L19 7" />
                            <?php else: ?>
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 7v5l3 2" />
                            <?php endif; ?>
                        </svg>
                    </div>
                    <div class="task-text">
                        <div class="task-title">
                            Získej Sledující 
                            <span class="progress">
                                <?php
                                $displayFollowers = min(
                                    $creatorTotalFollowers,
                                    $level['followers_required']
                                );
                                ?>
                                <?= (int)$displayFollowers ?>/<?= (int)$level['followers_required'] ?>
                            </span>
                        </div>
                        <div class="task-desc">Získej sledující, kteří přijdou na lekci</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (empty($iban) || $iban === "none"): ?>
            <div class="profile-warning" onclick="window.location='/settings/payments.php'">
                <div class="warning-left">
                    <div class="warning-icon">💰</div>
                    <div class="warning-text">
                        <span class="warning-title">Přidej IBAN k profilu</span>
                        <span class="warning-sub">Ať můžeš přijímat platby</span>
                    </div>
                </div>
                <div class="warning-action">Přidat</div>
            </div>
        <?php endif; ?>
    <div class="bottom"></div><br><br><br><br><br><br><br><br>
</div>

