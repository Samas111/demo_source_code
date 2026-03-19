<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once __DIR__ . '/secure/auth.php';
require_once __DIR__ . '/secure/require_creator.php';

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    exit;
}
$mysqli->set_charset('utf8mb4');

$openclassId = $_GET['id'] ?? $_POST['id'] ?? null;
if (!$openclassId) {
    http_response_code(404);
    exit('Missing OpenClass ID');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newTitle = trim($_POST['title'] ?? '');
    $newDescription = trim($_POST['description'] ?? '');
    $newCapacity = intval($_POST['capacity'] ?? 0);

    if ($newCapacity > 40) $newCapacity = 40;

    if (!empty($newTitle)) {
        $update = $mysqli->prepare("
            UPDATE openclasses 
            SET title = ?, description = ?, capacity = ? 
            WHERE openclass_id = ?
        ");
        $update->bind_param('ssis', $newTitle, $newDescription, $newCapacity, $openclassId);
        
        if ($update->execute()) {
            header("Location: openclass-edit.php?id=" . urlencode($openclassId) . "&status=updated");
            exit;
        }
        $update->close();
    }
}

$stmt = $mysqli->prepare("SELECT title, description, capacity FROM openclasses WHERE openclass_id = ? LIMIT 1");
$stmt->bind_param('s', $openclassId);
$stmt->execute();
$stmt->bind_result($currentTitle, $currentDescription, $currentCapacity);

if (!$stmt->fetch()) {
    http_response_code(404);
    exit('OpenClass not found');
}
$stmt->close();

function safe($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Upravit OpenClass</title>
    <link rel="stylesheet" href="attendance.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <style>
        body { background: #000; color: #fff; font-family: 'Inter', sans-serif; }
        .edit-container { padding: 20px; max-width: 500px; margin: 0 auto; }
        section { margin-bottom: 25px; }
        .label-title { font-size: 13px; color: #888; margin-bottom: 8px; font-weight: 500; text-transform: uppercase; }
        input[type="text"], input[type="number"], textarea {
            width: 100%;
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 14px;
            color: #fff;
            font-size: 16px;
            box-sizing: border-box;
            outline: none;
        }
        input:focus, textarea:focus { border-color: #444; }
        textarea { height: 120px; resize: none; font-family: inherit; }
        .save-bar { margin-top: 40px; }
        .save-btn {
            width: 100%;
            background: #fff;
            color: #000;
            border: none;
            padding: 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<nav class="nav">
    <a href="openclass-listing.php?id=<?= urlencode($openclassId) ?>" class="nav-btn nav-left">
        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" /></svg>
    </a>
    <h1 class="nav-title">Upravit lekci</h1>
</nav>

<div class="edit-container">
    <form method="POST" id="editForm">
        <input type="hidden" name="id" value="<?= safe($openclassId) ?>">

        <section>
            <div class="label-title">Název lekce</div>
            <input type="text" name="title" value="<?= safe($currentTitle) ?>" required placeholder="Např. Contemporary Choreo">
        </section>

        <section>
            <div class="label-title">Kapacita (Max 40)</div>
            <input type="number" name="capacity" value="<?= safe($currentCapacity) ?>" min="1" max="40" required>
        </section>

        <section>
            <div class="label-title">Popis</div>
            <textarea name="description" placeholder="Informace pro tanečníky..."><?= safe($currentDescription) ?></textarea>
        </section>

        <div class="save-bar">
            <button type="submit" class="save-btn">Uložit změny</button>
        </div>
    </form>
</div>

</body>
</html>