<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

require __DIR__ . '/../../secure/logic.php';
require_once __DIR__ . '/../../secure/auth.php';
require_once __DIR__ . '/../../secure/require_creator.php';
require_once __DIR__ . '/../../languages/loader.php';


if (!isset($_SESSION['user_id'])) {
    header("/registration/register-server-logic/auto.php");
    exit;
}

$auth_user_id = (int)$_SESSION['user_id'];

$defaultPfp = 'uploads/profile-pictures/default.png';

/*
 |------------------------------------------------------------
 | FETCH CREATORS (NO ORDERING HERE)
 |------------------------------------------------------------
*/
$sql = "
    SELECT 
        u.user_id,
        u.public_id,
        u.username,
        u.password,
        COALESCE(up.pfp_path, ?) AS pfp_path
    FROM users u
    LEFT JOIN user_profile up ON up.public_id = u.public_id
    WHERE u.is_creator = 1
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $defaultPfp);
$stmt->execute();
$result = $stmt->get_result();

/*
 |------------------------------------------------------------
 | MANUAL ORDER (EDIT THIS)
 |------------------------------------------------------------
*/
$manualOrder = [
    'fananbanan',
    'mira_kosik',
    'zizoe',
    'jenik_hornik',
    'd0pustim_',
    'simon_sinka',
    'iiikaraaiii',
    'tynkathedancer',
    'sonyamilova',
    '_dancechallenge_',
    'milastap',
    'mary_svetlova',
    'michal_ninja',
    'kaci',
    'juicylucy',
];

$creators = [];

$hiddenUserIds = [
  38, 50, 47
];

while ($row = $result->fetch_assoc()) {

    if (
        in_array((int)$row['user_id'], $hiddenUserIds, true) &&
        (int)$row['user_id'] !== (int)$auth_user_id
    ) {
        continue;
    }

    $creators[] = $row;
}

usort($creators, function ($a, $b) use ($auth_user_id, $manualOrder) {

    if ($a['user_id'] === $auth_user_id) return -1;
    if ($b['user_id'] === $auth_user_id) return 1;

    $posA = array_search(strtolower($a['username']), $manualOrder);
    $posB = array_search(strtolower($b['username']), $manualOrder);

    $posA = $posA === false ? PHP_INT_MAX : $posA;
    $posB = $posB === false ? PHP_INT_MAX : $posB;

    return $posA <=> $posB;
});

?>

<div class="user-campain">
    <h1><?= $T['launch'] ?></h1>
    <div class="creator-program-card">
        <div class="creator-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M20 6L9 17L4 12" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="creator-text">
            <h2><?= $T['part_of'] ?></h2>
            <p><?= $T['early_access'] ?></p>
        </div>
    </div>
</div>

<div class="user-campain">
    <h1><?= $T['wave'] ?></h1>
    <p><?= $T['form'] ?></p>
</div>

<div class="creators-grid">
<?php
$first = true;

foreach ($creators as $creator):

    $isHighlight = $first && $creator['user_id'] === $auth_user_id;
    $first = false;

    if (trim($creator['password']) === '') {
        $statusText = 'Brzy Přijde';
        $isDisabled = true;
    } else {
        $statusText = 'Registrován';
        $isDisabled = false;
    }

    $username = htmlspecialchars($creator['username']);
    if (mb_strlen($username) > 10) {
        $username = mb_substr($username, 0, 10) . '...';
    }

    $pfp = htmlspecialchars($creator['pfp_path']);
?>
    <div class="creator-card <?= $isHighlight ? 'highlight' : '' ?> <?= $isDisabled ? 'disabled' : '' ?>" <?= $isDisabled ? '' : "onclick=\"window.location.href='/view-profile.php?public_id=" . htmlspecialchars($creator['public_id']) . "'\"" ?>>
        <img src="<?= $pfp ?>" alt="">
        <div class="creator-info">
            <h3><?= $username ?></h3>
            <span><?= $statusText ?></span>
        </div>
    </div>
<?php endforeach; ?>
</div>
