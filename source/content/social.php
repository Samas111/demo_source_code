<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/require_creator.php';

$user = $GLOBALS['AUTH_USER'];
$public_id = $user['public_id'];

$days = 30;
$since = date('Y-m-d H:i:s', strtotime("-$days days"));

function dash($val) {
    return ($val == 0) ? '–' : $val;
}

/*
|--------------------------------------------------------------------------
| OPENCLASSES
|--------------------------------------------------------------------------
*/
$ocs = [];
$stmt = $mysqli->prepare("SELECT openclass_id, title, date, start_time, end_time FROM openclasses WHERE public_id = ?");
$stmt->bind_param("s", $public_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $ocs[] = $r;

$ids = array_column($ocs, 'openclass_id');
if (empty($ids)) $ids[] = 'none';

$in = implode(',', array_fill(0, count($ids), '?'));

/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/
$params = array_merge($ids, [$since]);

$stmt = $mysqli->prepare("SELECT COUNT(*) FROM openclass_views WHERE openclass_id IN ($in) AND viewed_at >= ?");
$stmt->bind_param(str_repeat('s', count($ids)) . 's', ...$params);
$stmt->execute(); $stmt->bind_result($views); $stmt->fetch(); $stmt->close();

$stmt = $mysqli->prepare("SELECT COUNT(*) FROM openclass_registrations WHERE openclass_id IN ($in) AND created_at >= ? AND storno = 0");
$stmt->bind_param(str_repeat('s', count($ids)) . 's', ...$params);
$stmt->execute(); $stmt->bind_result($regs); $stmt->fetch(); $stmt->close();

$conversion = $views > 0 ? round(($regs/$views)*100,1) : 0;

/*
|--------------------------------------------------------------------------
| GRAPH DATA (GROUPED → NOT TRASH QUERIES)
|--------------------------------------------------------------------------
*/
$viewsGraph = array_fill(0, 30, 0);
$regsGraph = array_fill(0, 30, 0);

$stmt = $mysqli->prepare("
    SELECT DATE(viewed_at) d, COUNT(*) c
    FROM openclass_views
    WHERE openclass_id IN ($in) AND viewed_at >= ?
    GROUP BY d
");
$stmt->bind_param(str_repeat('s', count($ids)) . 's', ...$params);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $index = (strtotime($row['d']) - strtotime(date('Y-m-d', strtotime("-29 days")))) / 86400;
    if ($index >= 0 && $index < 30) $viewsGraph[(int)$index] = (int)$row['c'];
}

$stmt = $mysqli->prepare("
    SELECT DATE(created_at) d, COUNT(*) c
    FROM openclass_registrations
    WHERE openclass_id IN ($in) AND created_at >= ? AND storno = 0
    GROUP BY d
");
$stmt->bind_param(str_repeat('s', count($ids)) . 's', ...$params);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $index = (strtotime($row['d']) - strtotime(date('Y-m-d', strtotime("-29 days")))) / 86400;
    if ($index >= 0 && $index < 30) $regsGraph[(int)$index] = (int)$row['c'];
}

/*
|--------------------------------------------------------------------------
| WORKSHOPS
|--------------------------------------------------------------------------
*/
$workshops = [];

foreach ($ocs as $oc) {
    $id = $oc['openclass_id'];

    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM openclass_views WHERE openclass_id=?");
    $stmt->bind_param("s", $id);
    $stmt->execute(); $stmt->bind_result($v); $stmt->fetch(); $stmt->close();

    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM openclass_registrations WHERE openclass_id=? AND storno=0");
    $stmt->bind_param("s", $id);
    $stmt->execute(); $stmt->bind_result($r); $stmt->fetch(); $stmt->close();

    $c = $v > 0 ? round(($r/$v)*100,1) : 0;

    $workshops[] = [
        'title'=>$oc['title'],
        'date'=>$oc['date'],
        'start'=>$oc['start_time'],
        'end'=>$oc['end_time'],
        'views'=>$v,
        'regs'=>$r,
        'conv'=>$c
    ];
}

usort($workshops, fn($a,$b)=>$b['regs']<=>$a['regs']);
$best = $workshops[0] ?? null;

if ($best) {
    array_shift($workshops); 
}

/*
|--------------------------------------------------------------------------
| ALL TIME TOTALS
|--------------------------------------------------------------------------
*/
$stmt = $mysqli->prepare("SELECT COUNT(*) FROM openclass_views WHERE openclass_id IN ($in)");
$stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
$stmt->execute(); $stmt->bind_result($views_all); $stmt->fetch(); $stmt->close();

$stmt = $mysqli->prepare("SELECT COUNT(*) FROM openclass_registrations WHERE openclass_id IN ($in) AND storno=0");
$stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
$stmt->execute(); $stmt->bind_result($regs_all); $stmt->fetch(); $stmt->close();

$conv_all = $views_all > 0 ? round(($regs_all/$views_all)*100,1) : 0;

/*
|--------------------------------------------------------------------------
| PREVIOUS 30 DAYS (for badge)
|--------------------------------------------------------------------------
*/
$prev_since = date('Y-m-d H:i:s', strtotime("-60 days"));
$prev_until = date('Y-m-d H:i:s', strtotime("-30 days"));

$paramsPrev = array_merge($ids, [$prev_since, $prev_until]);

$stmt = $mysqli->prepare("
    SELECT COUNT(*) FROM openclass_views 
    WHERE openclass_id IN ($in) 
    AND viewed_at BETWEEN ? AND ?
");
$stmt->bind_param(str_repeat('s', count($ids)).'ss', ...$paramsPrev);
$stmt->execute(); $stmt->bind_result($views_prev); $stmt->fetch(); $stmt->close();

$stmt = $mysqli->prepare("
    SELECT COUNT(*) FROM openclass_registrations 
    WHERE openclass_id IN ($in) 
    AND created_at BETWEEN ? AND ? 
    AND storno=0
");
$stmt->bind_param(str_repeat('s', count($ids)).'ss', ...$paramsPrev);
$stmt->execute(); $stmt->bind_result($regs_prev); $stmt->fetch(); $stmt->close();

$conv_prev = $views_prev > 0 ? round(($regs_prev/$views_prev)*100,1) : 0;

/*
|--------------------------------------------------------------------------
| CHANGE %
|--------------------------------------------------------------------------
*/
function percentChange($current, $prev) {
    if ($prev == 0) return 0;
    return round((($current - $prev) / $prev) * 100);
}

$views_change = percentChange($views, $views_prev);
$regs_change = percentChange($regs, $regs_prev);
$conv_change = percentChange($conversion, $conv_prev);

/*
|--------------------------------------------------------------------------
| TOP 3 LOYAL ATTENDEES
|--------------------------------------------------------------------------
*/
$loyalAttendees = [];
if ($ids !== ['none']) {
    $stmt = $mysqli->prepare("
        SELECT
            u.username,
            up.pfp_path,
            u.public_id,
            COUNT(*) AS times
        FROM openclass_registrations r
        JOIN users u ON u.public_id = r.public_id
        LEFT JOIN user_profile up ON up.public_id = u.public_id
        WHERE r.openclass_id IN ($in)
          AND r.storno = 0
        GROUP BY u.public_id
        ORDER BY times DESC
        LIMIT 3
    ");
    $stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['pfp_path'] = !empty($row['pfp_path'])
            ? ltrim($row['pfp_path'], '/')
            : 'source/assets/default.png';
        $loyalAttendees[] = $row;
    }
    $stmt->close();
}

// Pad to always have 3 slots
while (count($loyalAttendees) < 3) {
    $loyalAttendees[] = null;
}

function attendeeInitials(string $name): string {
    $parts = preg_split('/[\s_]+/', trim($name));
    if (count($parts) >= 2)
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    return mb_strtoupper(mb_substr($name, 0, 2));
}
?>

<div class="container-overflow">
    <div class="top-stat-bar">
        <span><?= $T['statistics'] ?></span>
        <div class="stats-toggle">
            <button class="active" onclick="setMode('30', this)"><?= $T['last_30_days'] ?></button>
            <button onclick="setMode('all', this)"><?= $T['all_time'] ?></button>
        </div>
    </div>

    <div class="main-stats" id="stats-30">
        <div class="main-stat-card">
            <div class="top-left-badge <?= $views_change >= 0 ? 'profit' : 'loss' ?>">
                <?= $views_change >= 0 ? '+' : '' ?><?= $views_change ?>%
            </div>
            <span><?= dash($views) ?></span>
            <p><?= $T['views'] ?></p>
        </div>
        <div class="main-stat-card">
            <div class="top-left-badge <?= $regs_change >= 0 ? 'profit' : 'loss' ?>">
                <?= $regs_change >= 0 ? '+' : '' ?><?= $regs_change ?>%
            </div>
            <span><?= dash($regs) ?></span>
            <p><?= $T['registrations'] ?></p>
        </div>
        <div class="main-stat-card">
            <div class="top-left-badge <?= $conv_change >= 0 ? 'profit' : 'loss' ?>">
                <?= $conv_change >= 0 ? '+' : '' ?><?= $conv_change ?>%
            </div>
            <span><?= $conversion == 0 ? '–' : $conversion.'%' ?></span>
            <p><?= $T['conversion'] ?></p>
        </div>
    </div>

    <div class="main-stats" id="stats-all" style="display:none;">
        <div class="main-stat-card">
            <span><?= dash($views_all) ?></span>
            <p><?= $T['views'] ?></p>
        </div>
        <div class="main-stat-card">
            <span><?= dash($regs_all) ?></span>
            <p><?= $T['registrations'] ?></p>
        </div>
        <div class="main-stat-card">
            <span><?= $conv_all == 0 ? '–' : $conv_all.'%' ?></span>
            <p><?= $T['conversion'] ?></p>
        </div>
    </div>

    <div class="stats-graph">
        <div class="graph-top">
            <div id="graph-title"><?= $T['regs_30_days'] ?></div>

            <div class="graph-switch" data-toggle>
                <button class="active" onclick="handleToggle(this, 'currentType', 'regs')"><?= $T['registrations'] ?></button>
                <button onclick="handleToggle(this, 'currentType', 'views')"><?= $T['views'] ?></button>
            </div>
        </div>

        <div id="graph-meta"></div>

        <canvas id="statsChart"></canvas>
    </div>

    <?php $allEmpty = array_filter($loyalAttendees) === []; ?>
    <div class="loyal-section">
        <div class="loyal-header">
            <span><?= $T['loyal_header'] ?></span>
        </div>
        <div class="loyal-slots">
            <?php
            $rankColors = ['#FFD166', '#C0C0C0', '#CD7F32'];
            foreach ($loyalAttendees as $i => $a):
            ?>
                <div class="loyal-slot <?= $a ? 'filled' : 'empty' ?>"
                     <?= $a ? 'onclick="window.location=\'main-indexes/view-profile.php?public_id=' . urlencode($a['public_id']) . '\'"' : '' ?>>
                    <div class="loyal-rank" style="color: <?= $rankColors[$i] ?>"><?= $i + 1 ?></div>
                    <?php if ($a): ?>
                        <div class="loyal-avatar">
                            <img src="<?= htmlspecialchars($a['pfp_path']) ?>" alt="">
                        </div>
                        <div class="loyal-name"><?= htmlspecialchars($a['username']) ?></div>
                        <div class="loyal-count"><?= (int)$a['times'] ?>×</div>
                    <?php else: ?>
                        <div class="loyal-avatar-empty"></div>
                        <div class="loyal-name-empty"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($allEmpty): ?>
            <p class="loyal-teaser"><?= $T['loyal_teaser'] ?></p>
        <?php endif; ?>
    </div>

    <div class="active-workshops">
        <?php if ($best): ?>
            <div class="active-workshop-card">
                <div class="flex">
                    <div class="content">
                        <div class="bsst"><?= $T['best_openclass'] ?></div>
                        <h1><h1><?= htmlspecialchars($best['title']) ?></h1></h1>
                        <div class="workshop-stats">
                            <div class="stat attendance">
                                <img src="source/assets/people.png">
                                <?= dash($best['regs']) ?>
                            </div>
                            <div class="stat cash">
                                <img src="source/assets/view.png">
                                <?= dash($best['views']) ?>
                            </div>
                            <div class="stat cash">
                                <img src="source/assets/cash.png">
                                <?= $best['conv'] ?: '–' ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php foreach ($workshops as $w): ?>
        <div class="active-workshop-card">
            <div class="flex">
                <div class="content">
                    <h1><?= htmlspecialchars($w['title']) ?></h1>

                    <div class="workshop-stats">
                        <div class="stat attendance">
                            <img src="source/assets/people.png">
                            <?= dash($w['regs']) ?>
                        </div>

                        <div class="stat cash">
                            <img src="source/assets/view.png">
                            <?= dash($w['views']) ?>
                        </div>

                        <div class="stat cash">
                            <img src="source/assets/cash.png">
                            <?= $w['conv'] ? $w['conv'].'%' : '–' ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="padding"></div>
</div>
