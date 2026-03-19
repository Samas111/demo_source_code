<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once __DIR__ . '/secure/auth.php';
require_once __DIR__ . '/secure/require_creator.php';

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

$openclassId = $_GET['id'] ?? $_POST['openclass_id'] ?? null;
if (!$openclassId) {
    die("Missing ID");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $absentUsers = $_POST['absent_users'] ?? []; 

    if (!empty($absentUsers)) {
        $stmt = $mysqli->prepare("
            UPDATE openclass_registrations 
            SET storno = 1, canceled_at = NOW() 
            WHERE openclass_id = ? AND public_id = ?
        ");

        foreach ($absentUsers as $pid) {
            $stmt->bind_param('ss', $openclassId, $pid);
            $stmt->execute();
        }
        $stmt->close();
    }

    $updateOc = $mysqli->prepare("UPDATE openclasses SET checked = 1 WHERE openclass_id = ?");
    $updateOc->bind_param('s', $openclassId);
    $updateOc->execute();
    $updateOc->close();
    
    header("Location: app.php");
    exit;
}

$oc = $mysqli->prepare("SELECT title FROM openclasses WHERE openclass_id = ? LIMIT 1");
$oc->bind_param('s', $openclassId);
$oc->execute();
$oc->bind_result($oc_title);
$oc->fetch();
$oc->close();

$regs = [];
$r = $mysqli->prepare("
    SELECT u.username, up.pfp_path, u.public_id
    FROM openclass_registrations r
    JOIN users u ON u.public_id = r.public_id
    LEFT JOIN user_profile up ON up.public_id = u.public_id
    WHERE r.openclass_id = ? AND r.storno = 0
");
$r->bind_param('s', $openclassId);
$r->execute();
$r->bind_result($username, $pfp, $pid);
while ($r->fetch()) {
    $regs[] = ['username' => $username, 'pfp' => $pfp ?: 'pfp.png', 'pid' => $pid];
}
$r->close();
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docházka | <?= htmlspecialchars($oc_title) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="after-class.css">
</head>
<body>

<div class="container">
    <div class="header">
        <span class="badge-end">OpenClass Proběhla</span>
        <h2 style="margin: 15px 0 5px;"><?= htmlspecialchars($oc_title) ?></h2>
    </div>

    <p class="came">Přišli všichni?</p>

    <form id="attendanceForm" method="POST">
        <input type="hidden" name="openclass_id" value="<?= htmlspecialchars($openclassId) ?>">
        
        <div class="user-list">
            <?php if (empty($regs)): ?>
                <p style="text-align: center; color: #666;">Žádní aktivní účastníci.</p>
            <?php else: ?>
                <?php foreach ($regs as $u): ?>
                    <div class="user-row" onclick="toggleUser(this, '<?= $u['pid'] ?>')">
                        <img src="<?= htmlspecialchars($u['pfp']) ?>">
                        <span><?= htmlspecialchars($u['username']) ?></span>
                        <div class="status-tag">Nepřítomný</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <button type="submit" name="submit_attendance" class="btn-submit">Hotovo</button>
    </form>
</div>

<script>
function toggleUser(el, pid) {
    el.classList.toggle('absent');
    
    let existingInput = document.querySelector(`input[name="absent_users[]"][value="${pid}"]`);
    
    if (el.classList.contains('absent')) {
        if (!existingInput) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'absent_users[]';
            input.value = pid;
            document.getElementById('attendanceForm').appendChild(input);
        }
    } else {
        if (existingInput) existingInput.remove();
    }
}
</script>

</body>
</html>