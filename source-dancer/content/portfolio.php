<?php

error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';

date_default_timezone_set('Europe/Prague');

function displayOrDash(int $value): string
{
    return $value > 0 ? number_format($value, 0, ',', ' ') : '—';
}

$excludedOpenclassIds = [];
$nextOpenclass = null;

$public_id = $GLOBALS['AUTH_USER']['public_id'];

$target = 2;

$stmt = $mysqli->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as ym,
        COUNT(DISTINCT openclass_id) as cnt
    FROM openclass_registrations
    WHERE public_id = ?
      AND storno = 0
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym
");
$stmt->bind_param('s', $public_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[$row['ym']] = (int)$row['cnt'];
}
$stmt->close();

$months = [];
for ($i = 6; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $count = $data[$key] ?? 0;

    $months[] = [
        'count' => $count,
        'done' => $count >= $target
    ];
}

$current = $months[6];
$monthlyCount = $current['count'];
$progress = min($monthlyCount / $target, 1);
$remaining = max($target - $monthlyCount, 0);

$formatter = new IntlDateFormatter(
    'cs_CZ',
    IntlDateFormatter::LONG,
    IntlDateFormatter::NONE,
    'Europe/Prague',
    IntlDateFormatter::GREGORIAN,
    'LLLL'
);

$monthName = $formatter->format(new DateTime());

$nextStmt = $mysqli->prepare("
    SELECT 
        oc.openclass_id,
        oc.title,
        oc.date,
        oc.start_time,
        oc.end_time,
        oc.description,
        oc.cover_image
    FROM openclass_registrations r
    JOIN openclasses oc ON oc.openclass_id = r.openclass_id
    WHERE r.public_id = ?
      AND r.storno = 0
      AND (STR_TO_DATE(oc.date, '%Y-%m-%d') > CURDATE()
           OR (STR_TO_DATE(oc.date, '%Y-%m-%d') = CURDATE() AND oc.start_time >= CURTIME()))
    ORDER BY 
        STR_TO_DATE(oc.date, '%Y-%m-%d') ASC,
        oc.start_time ASC
    LIMIT 1
");

$nextStmt->bind_param('s', $public_id);
$nextStmt->execute();
$res = $nextStmt->get_result();

if ($row = $res->fetch_assoc()) {
    $nextOpenclass = $row;
}

$nextStmt->close();

$timeLabel = null;

if ($nextOpenclass) {
    $eventDateTime = DateTime::createFromFormat(
        'Y-m-d H:i:s',
        $nextOpenclass['date'] . ' ' . $nextOpenclass['start_time']
    );

    $now = new DateTime();

    if ($eventDateTime > $now) {
        $diffSeconds = $eventDateTime->getTimestamp() - $now->getTimestamp();
        $diffHours = floor($diffSeconds / 3600);
        $diffDays = floor($diffHours / 24);

        if ($diffHours < 48) {
            $timeLabel = sprintf($T['in_hours'], $diffHours);
        } else {
            $timeLabel = sprintf($T['in_days'], $diffDays);
        }
    }
}

$pastStmt = $mysqli->prepare("
    SELECT 
        oc.title,
        oc.date,
        oc.start_time,
        oc.end_time,
        oc.description,
        oc.cover_image
    FROM openclass_registrations r
    JOIN openclasses oc ON oc.openclass_id = r.openclass_id
    WHERE r.public_id = ?
      AND r.storno = 0
      AND (
            STR_TO_DATE(oc.date, '%Y-%m-%d') < CURDATE()
         OR (STR_TO_DATE(oc.date, '%Y-%m-%d') = CURDATE() AND oc.end_time < CURTIME())
      )
    ORDER BY 
        STR_TO_DATE(oc.date, '%Y-%m-%d') DESC,
        oc.start_time DESC
    LIMIT 2
");

$pastStmt->bind_param('s', $public_id);
$pastStmt->execute();
$pastRes = $pastStmt->get_result();

$pastOpenclasses = [];
while ($row = $pastRes->fetch_assoc()) {
    $pastOpenclasses[] = $row;
}

$pastStmt->close();

$hasAnyRegistrations = false;

$checkStmt = $mysqli->prepare("
    SELECT 1 
    FROM openclass_registrations 
    WHERE public_id = ? 
      AND storno = 0
    LIMIT 1
");

$checkStmt->bind_param('s', $public_id);
$checkStmt->execute();
$checkStmt->store_result();

$hasAnyRegistrations = $checkStmt->num_rows > 0;

$checkStmt->close();
?>


<div class="app-container">
    <?php if ($hasAnyRegistrations): ?>
        <div class="streak-card">
            <div class="streak-header">
                <span class="title"><?= ucfirst($monthName) ?>: <?= $monthlyCount ?> / <?= $target ?></span>
                <span class="subtitle">
                    <?= $remaining > 0 ? sprintf($T['streak_left'], $remaining) : $T['streak_done'] ?>
                </span>
            </div>
            <div class="progress-container"><div class="progress-fill" style="width: <?= $progress * 100 ?>%;"></div></div>
            <div class="days-grid">
            <?php foreach ($months as $m): ?>
                <div class="day-circle <?= $m['done'] ? 'active' : '' ?>">
                    <?= $m['done'] ? '✓' : '✕' ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="df-empty-primary">
            <div class="df-empty-icon">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 8v4l3 2"/>
                </svg>
            </div>

            <div class="df-empty-title"><?= $T['start_progress'] ?></div>
            <div class="df-empty-sub"><?= $T['first_oc_unlock'] ?></div>

            <a href="/app-dancer.php?tab=openclasses" class="df-empty-btn"><?= $T['find_first_class'] ?></a>
        </div>
    <?php endif; ?>

    <?php if ($nextOpenclass): ?>

        <div class="reg-card">
            <div class="reg-info">
                <h4><?= htmlspecialchars($nextOpenclass['title']) ?></h4>
                <h2>
                    <?= date('d.m.', strtotime($nextOpenclass['date'])) ?>
                    <?= $nextOpenclass['start_time'] ?> - <?= $nextOpenclass['end_time'] ?>
                </h2>
                <p><?= htmlspecialchars(substr($nextOpenclass['description'], 0, 25)) ?>...</p>
            </div>
            <div class="img-placeholder">
                <img src="../uploads/openclasses/<?= htmlspecialchars($nextOpenclass['cover_image']) ?>" alt="">
            </div>
            <div class="reg-footer">
                <button class="btn-white" onclick="window.location='/main-indexes/openclass-listing.php?id=<?= htmlspecialchars($nextOpenclass['openclass_id']) ?>'"><?= $T['registered'] ?></button>
            </div>
        </div>

    <?php else: ?>

        <div class="df-cta-inline">
            <div class="df-cta-left">
                <div class="df-empty-icon small">
                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="6"/>
                        <path d="M21 21L16.65 16.65"/>
                    </svg>
                </div>

                <div>
                    <div class="df-cta-title"><?= $T['next_step'] ?></div>
                    <div class="df-cta-sub"><?= $T['find_next_oc'] ?></div>
                </div>
            </div>

            <a href="/app-dancer.php?tab=openclasses" class="df-cta-btn"><?= $T['explore'] ?></a>
        </div>

    <?php endif; ?>

    <div>
        <div class="section-title">
            <span>
                <?= $T['your_portfolio'] ?><?= count($pastOpenclasses) > 0 ? ' (' . count($pastOpenclasses) . ')' : '' ?>
            </span>
            <span style="font-size: 11px; text-decoration: line-through; opacity: 0.3;">Zobrazit →</span>
        </div>
        <div class="portfolio-list">
            <?php if (!empty($pastOpenclasses)): ?>
                <?php foreach ($pastOpenclasses as $oc): ?>
                    <div class="list-item">
                        <div class="thumb">
                            <img src="../uploads/openclasses/<?= htmlspecialchars($oc['cover_image']) ?>" alt="">
                        </div>
                        <div class="item-content">
                            <div class="top">
                                <span class="name-op"><?= htmlspecialchars($oc['title']) ?></span>
                            </div>
                            <div class="time">
                                <?= date('d.m.', strtotime($oc['date'])) ?>
                                <?= $oc['start_time'] ?> - <?= $oc['end_time'] ?>
                            </div>
                            <div class="desc">
                                <?= htmlspecialchars(substr($oc['description'], 0, 60)) ?>...
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="df-empty-secondary">
                    <div class="df-empty-title"><?= $T['portfolio_empty'] ?></div>
                    <div class="df-empty-sub"><?= $T['portfolio_empty_sub'] ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- 
    <div>
        <div class="section-title">Vaši tvůrci</div>
        <div class="creators-row">
            <div class="creator-card fav">
                <div class="fav-banner">VÁŠ OBLÍBENÝ</div>
                <img src="https://i.pravatar.cc/100?u=a1" class="creator-img">
                <span class="handle">mira_kosik</span>
                <span class="stats"><b>12x</b> lekcí absolvováno<br><b>2%</b> nejvěrnějších</span>
            </div>
            <div class="creator-card">
                <img src="https://i.pravatar.cc/100?u=a2" class="creator-img">
                <span class="handle">jenik_hornik</span>
                <span class="stats">8x lekcí absolvováno</span>
            </div>
            <div class="creator-card">
                <img src="https://i.pravatar.cc/100?u=a3" class="creator-img">
                <span class="handle">iiikaraaii</span>
                <span class="stats">3x lekce absolvováno</span>
            </div>
        </div>
    </div>

    <div>
        <div class="section-title">Vaši spolutanečníci</div>
        <div class="dancers-strip">
            <div class="dancer-item">
                <img src="https://i.pravatar.cc/50?u=b1" class="dancer-img">
                <div class="dancer-name">jarossova_n</div>
                <div class="dancer-count">6 společné lekce</div>
            </div>
            <div class="dancer-item">
                <img src="https://i.pravatar.cc/50?u=b2" class="dancer-img">
                <div class="dancer-name">diu</div>
                <div class="dancer-count">4 společné lekce</div>
            </div>
            <div class="dancer-item">
                <img src="https://i.pravatar.cc/50?u=b3" class="dancer-img">
                <div class="dancer-name">gabriela</div>
                <div class="dancer-count">4 společné lekce</div>
            </div>
            <div class="dancer-item">
                <img src="https://i.pravatar.cc/50?u=b4" class="dancer-img">
                <div class="dancer-name">drc_vojta</div>
                <div class="dancer-count">2 společné lekce</div>
            </div>
        </div>
    </div>
    -->
</div><br><br><br><br><br>
