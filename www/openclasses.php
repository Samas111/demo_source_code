<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(E_ALL);


require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once __DIR__ . '/secure/auth.php';
require_once __DIR__ . '/secure/require_creator.php';

date_default_timezone_set('Europe/Prague');

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    exit;
}
$mysqli->set_charset('utf8mb4');

function displayOrDash(int $value): string
{
    return $value > 0 ? number_format($value, 0, ',', ' ') : '—';
}

$nextOpenclass = null;

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

function fakeDopamineBadge(string $seed, int $baseValue): ?array
{
    if ($baseValue < 5) {
        return null;
    }


    $hourSeed = date('Y-m-d-H');
    $hash = crc32($seed . $hourSeed);

    mt_srand($hash);

    $value = mt_rand(2, 4);

    return [
        'value' => $value,
        'class' => 'profit',
        'label' => '+' . $value . '%'
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

$viewsBadge = fakeDopamineBadge($public_id . '_views', (int)$creatorTotalViews);
$followersBadge = fakeDopamineBadge($public_id . '_followers', (int)$creatorTotalFollowers);
$registrationsBadge = fakeDopamineBadge($public_id . '_registrations', (int)$creatorTotalRegistrations);

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
?>

<div class="main-info">
    <div class="center">
        <div class="cash-display">
            <span>
                <?= $monthRevenue > 0
                    ? number_format((int)$monthRevenue, 0, ',', ' ') . ' Kč'
                    : '—'
                ?>
            </span>
            <p>V <?= $currentMonthCz ?></p>
        </div>
    </div>
</div>
<div class="container-overflow">
    <div class="dopamine-message">Vítej v předběžném přístupu Dancefy!</div>
    <div class="main-stats">
        <div class="main-stat-card">
            <?php if ($viewsBadge): ?>
                <div class="top-left-badge <?= $viewsBadge['class'] ?>">
                    <?= $viewsBadge['label'] ?>
                </div>
            <?php endif; ?>
            <span><?= displayOrDash((int)$creatorTotalViews) ?></span>
            <p>Zobrazení</p>
        </div>
        <div class="main-stat-card">
            <?php if ($followersBadge): ?>
                <div class="top-left-badge <?= $followersBadge['class'] ?>">
                    <?= $followersBadge['label'] ?>
                </div>
            <?php endif; ?>
            <span><?= displayOrDash((int)$creatorTotalFollowers) ?></span>
            <p>Sledujících</p>
        </div>
        <div class="main-stat-card">
            <?php if ($registrationsBadge): ?>
                <div class="top-left-badge <?= $registrationsBadge['class'] ?>">
                    <?= $registrationsBadge['label'] ?>
                </div>
            <?php endif; ?>
            <span><?= displayOrDash((int)$creatorTotalRegistrations) ?></span>
            <p>Registrací</p>
        </div>
    </div>
    <div class="active-workshops">
        <?php if (!empty($openclasses)): ?>
            <?php foreach ($openclasses as $index => $oc): ?>
                <div class="active-workshop-card"
                    onclick="window.location.href='attendance.php?id=<?= urlencode($oc['openclass_id']) ?>'">
                    <?php if ($index === 0): ?>
                        <span>Za <?= htmlspecialchars($oc['countdown']) ?></span>
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
            <div class="add">
                <img onclick="window.location='post/openclass.php'" src="source/assets/add-openclass.png">
            </div>
        <?php endif; ?>

        </div>
    <div class="bottom"></div><br><br><br><br>
</div>

