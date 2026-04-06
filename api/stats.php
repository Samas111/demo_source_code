<?php
require $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

if (!isset($_SESSION['public_id'])) exit;

$public_id = $_SESSION['public_id'];

ini_set('display_errors', 1);
error_reporting(E_ALL);

$ocs = [];
$stmt = $mysqli->prepare("SELECT openclass_id FROM openclasses WHERE public_id=?");
$stmt->bind_param("s", $public_id);
$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) $ocs[] = $r['openclass_id'];

if (empty($ocs)) $ocs[] = 'none';

$in = implode(',', array_fill(0, count($ocs), '?'));

$since = date('Y-m-d H:i:s', strtotime("-30 days"));
$params = array_merge($ocs, [$since]);

$regs30 = array_fill(0, 30, 0);

$stmt = $mysqli->prepare("
    SELECT DATE(r.created_at) d, COUNT(*) c
    FROM openclass_registrations r
    JOIN openclasses o ON r.openclass_id = o.openclass_id
    WHERE o.public_id = ?
    AND r.created_at >= ?
    AND r.storno = 0
    GROUP BY d
");
$stmt->bind_param("ss", $public_id, $since);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $index = (strtotime($row['d']) - strtotime(date('Y-m-d', strtotime("-29 days")))) / 86400;
    if ($index >= 0 && $index < 30) $regs30[(int)$index] = (int)$row['c'];
}

$views30 = array_fill(0, 30, 0);

$stmt = $mysqli->prepare("
    SELECT DATE(v.viewed_at) d, COUNT(*) c
    FROM openclass_views v
    JOIN openclasses o ON v.openclass_id = o.openclass_id
    WHERE o.public_id = ?
    AND v.viewed_at >= ?
    GROUP BY d
");

$stmt->bind_param("ss", $public_id, $since);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $index = (strtotime($row['d']) - strtotime(date('Y-m-d', strtotime("-29 days")))) / 86400;
    if ($index >= 0 && $index < 30) {
        $views30[(int)$index] = (int)$row['c'];
    }
}

// --- REGS ALL ---
$stmt = $mysqli->prepare("
    SELECT DATE(r.created_at) d, COUNT(*) c
    FROM openclass_registrations r
    JOIN openclasses o ON r.openclass_id = o.openclass_id
    WHERE o.public_id = ? AND r.storno = 0
    GROUP BY d
    ORDER BY d ASC
");
$stmt->bind_param("s", $public_id);
$stmt->execute();
$resRegs = $stmt->get_result(); // Use a unique variable name

$dataMapRegs = [];
while ($row = $resRegs->fetch_assoc()) {
    $dataMapRegs[$row['d']] = (int)$row['c'];
}

$regsAll = [];
if (!empty($dataMapRegs)) {
    $start = strtotime(array_key_first($dataMapRegs));
    $end = strtotime(date('Y-m-d'));
    for ($t = $start; $t <= $end; $t += 86400) {
        $d = date('Y-m-d', $t);
        $regsAll[] = $dataMapRegs[$d] ?? 0;
    }
}

// --- VIEWS ALL ---
$stmt = $mysqli->prepare("
    SELECT DATE(v.viewed_at) d, COUNT(*) c
    FROM openclass_views v
    JOIN openclasses o ON v.openclass_id = o.openclass_id
    WHERE o.public_id = ?
    GROUP BY d
    ORDER BY d ASC
");
$stmt->bind_param("s", $public_id);
$stmt->execute();
$resViews = $stmt->get_result(); // Use a unique variable name

$dataMapViews = [];
while ($row = $resViews->fetch_assoc()) {
    $dataMapViews[$row['d']] = (int)$row['c'];
}

$viewsAll = [];
if (!empty($dataMapViews)) {
    $start = strtotime(array_key_first($dataMapViews));
    $end = strtotime(date('Y-m-d'));
    for ($t = $start; $t <= $end; $t += 86400) {
        $d = date('Y-m-d', $t);
        $viewsAll[] = $dataMapViews[$d] ?? 0;
    }
}

$dataMap = [];

while ($row = $res->fetch_assoc()) {
    $dataMap[$row['d']] = (int)$row['c'];
}

if (!empty($dataMap)) {

    $start = strtotime(array_key_first($dataMap));
    $end = strtotime(date('Y-m-d')); // ← TODAY, not last data

    for ($t = $start; $t <= $end; $t += 86400) {
        $d = date('Y-m-d', $t);
        $regsAll[] = $dataMap[$d] ?? 0;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'regs30' => $regs30,
    'regsAll' => $regsAll,
    'views30' => $views30,
    'viewsAll' => $viewsAll
]);