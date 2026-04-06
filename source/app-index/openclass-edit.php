<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/require_creator.php';

$openclassId = $_GET['id'] ?? $_POST['id'] ?? null;
if (!$openclassId) {
    http_response_code(404);
    exit('Missing OpenClass ID');
}

$public_id = $GLOBALS['AUTH_USER']['public_id'];

// 1. Get User IBAN status first to validate if they CAN use QR
$stmtIban = $mysqli->prepare("SELECT iban FROM user_profile WHERE public_id = ? LIMIT 1");
$stmtIban->bind_param('s', $public_id);
$stmtIban->execute();
$stmtIban->bind_result($userIban);
$stmtIban->fetch();
$stmtIban->close();

$hasIban = ($userIban && strtolower(trim($userIban)) !== 'none' && trim($userIban) !== '');

// 2. Handle POST Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $capacity = min(100, max(1, intval($_POST['capacity'] ?? 1)));
    $price = intval($_POST['price'] ?? 0);
    $level = $_POST['level'] ?? 'beginner';
    $address = trim($_POST['address'] ?? '');
    
    // Only allow setting is_qr to 1 if the user actually has an IBAN
    $is_qr = ($hasIban && isset($_POST['is_qr'])) ? 1 : 0;

    $update = $mysqli->prepare("
        UPDATE openclasses SET 
            title = ?, 
            description = ?, 
            capacity = ?, 
            price = ?, 
            level = ?, 
            address = ?, 
            is_qr = ?
        WHERE openclass_id = ?
    ");

    $update->bind_param(
        'ssiissis',
        $title,
        $description,
        $capacity,
        $price,
        $level,
        $address,
        $is_qr,
        $openclassId
    );

    if ($update->execute()) {
        header("Location: openclass-edit.php?id=" . urlencode($openclassId) . "&status=updated");
        exit;
    }
}

// 3. Fetch current data for display
$stmt = $mysqli->prepare("
    SELECT title, description, capacity, price, level, address, 
           date, start_time, end_time, cover_image, is_qr
    FROM openclasses 
    WHERE openclass_id = ? 
    LIMIT 1
");
$stmt->bind_param('s', $openclassId);
$stmt->execute();
$stmt->bind_result($title, $description, $capacity, $price, $level, $address, $date, $start, $end, $cover, $is_qr);

if (!$stmt->fetch()) {
    http_response_code(404);
    exit('Not found');
}
$stmt->close();

function safe($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit OpenClass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <style>
        :root { --bg: #000; --card-bg: #1c1c1e; --input-bg: #2c2c2e; --accent: #fff; --text-main: #fff; --text-dim: #8e8e93; --border: #3a3a3c; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; padding-bottom: 40px; }
        .header { position: sticky; top: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); padding: 16px 20px; z-index: 100; display: flex; align-items: center; border-bottom: 0.5px solid var(--border); }
        .header h1 { margin: 0; font-size: 17px; font-weight: 600; flex: 1; text-align: center; }
        .back-btn { color: var(--accent); text-decoration: none; font-size: 16px; position: absolute; }
        .container { padding: 16px; max-width: 500px; margin: auto; }
        .group { background: var(--card-bg); border-radius: 14px; overflow: hidden; margin-bottom: 24px; }
        .row { padding: 12px 16px; border-bottom: 0.5px solid var(--border); display: flex; flex-direction: column; }
        .row:last-child { border-bottom: none; }
        .label { font-size: 11px; font-weight: 600; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        input, textarea, select { background: transparent; border: none; color: var(--text-main); font-size: 16px; padding: 4px 0; width: 100%; outline: none; }
        input:disabled { color: var(--text-dim); opacity: 0.7; }
        textarea { height: 80px; resize: none; font-family: inherit; }
        .toggle-row { flex-direction: row; align-items: center; justify-content: space-between; padding: 14px 16px; }
        .toggle-row .label { margin-bottom: 0; font-size: 15px; text-transform: none; color: #fff; font-weight: 500; }
        .switch { position: relative; display: inline-block; width: 51px; height: 31px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #39393d; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 27px; width: 27px; left: 2px; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 3px 8px rgba(0,0,0,0.15); }
        input:checked + .slider { background-color: #34c759; }
        input:checked + .slider:before { transform: translateX(20px); }
        .save-btn { width: 100%; padding: 18px; border-radius: 14px; border: none; background: var(--accent); color: #000; font-size: 17px; font-weight: 700; margin-top: 10px; cursor: pointer; }
        .status-msg { text-align: center; color: #34c759; font-size: 14px; margin-bottom: 15px; font-weight: 600; }
        select {
            appearance: none;
            -webkit-appearance: none;
            background-color: #2a2a2a;
            color: #fff;
            border: none;
        }
    </style>
</head>
<body>

<div class="header">
    <a href="attendance.php?id=<?= urlencode($openclassId) ?>" class="back-btn">←</a>
    <h1>Editovat Lekci</h1>
</div>

<div class="container">
    <?php if (($_GET['status'] ?? '') === 'updated'): ?>
        <div class="status-msg">✓ Změny byly uloženy</div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="id" value="<?= safe($openclassId) ?>">

        <div class="group">
            <div class="row">
                <div class="label">Název Lekce</div>
                <input type="text" name="title" value="<?= safe($title) ?>" required>
            </div>
            <div class="row">
                <div class="label">Popis</div>
                <textarea name="description"><?= safe($description) ?></textarea>
            </div>
        </div>

        <div class="group">
            <div class="row">
                <div class="label">Adresa</div>
                <input type="text" name="address" value="<?= safe($address) ?>">
            </div>
            <div class="row">
                <div class="label">Úroveň</div>
                <select name="level">
                    <option value="beginner" <?= $level=='beginner'?'selected':'' ?>>Beginner</option>
                    <option value="intermediate" <?= $level=='intermediate'?'selected':'' ?>>Intermediate</option>
                    <option value="pro" <?= $level=='pro'?'selected':'' ?>>Professional</option>
                </select>
            </div>
        </div>

        <div class="group">
            <div class="row">
                <div class="label">Kapacita</div>
                <input type="number" name="capacity" value="<?= safe($capacity) ?>">
            </div>
            <div class="row">
                <div class="label">Cena (CZK)</div>
                <input type="number" name="price" value="<?= safe($price) ?>">
            </div>
        </div>

        <div class="group">
            <div class="row">
                <div class="label">Datum & Čas (Neměnné)</div>
                <input type="text" value="<?= safe($date) ?> @ <?= safe($start) ?> - <?= safe($end) ?>" disabled>
            </div>
        </div>

        <div class="group">
            <div class="row toggle-row">
                <div class="label">Povolit QR platby</div>
                <label class="switch">
                    <input type="checkbox" name="is_qr" <?= $is_qr ? 'checked' : '' ?> <?= !$hasIban ? 'disabled' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>
        
        <?php if (!$hasIban): ?>
            <div style="font-size:12px;color:#ff453a;margin: -15px 0 20px 5px; padding: 0 10px;">
                ⚠ Chybí IBAN v profilu. QR platby nelze zapnout.
            </div>
        <?php endif; ?>

        <button type="submit" class="save-btn">Uložit Změny</button>
    </form>
</div>

</body>
</html>