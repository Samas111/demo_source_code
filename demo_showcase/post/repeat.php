<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(E_ALL);

session_start();
require __DIR__ . '/../secure/logic.php';

if (empty($_SESSION['public_id']) || !isset($_GET['id'])) {
    header("Location: /post/index.php");
    exit;
}

$public_id = $_SESSION['public_id'];
$source_id = $_GET['id'];
$error_msg = "";

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

$sel = $mysqli->prepare("SELECT * FROM openclasses WHERE openclass_id = ? AND public_id = ? LIMIT 1");
$sel->bind_param('ss', $source_id, $public_id);
$sel->execute();
$res = $sel->get_result();
$source = $res->fetch_assoc();
$sel->close();

if (!$source) {
    exit("Původní lekce nebyla nalezena.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_date_raw = $_POST['date'] ?? '';
    $inputDate = DateTime::createFromFormat('Y-m-d', $new_date_raw);
    $today = new DateTime('today');

    if (!$inputDate || $inputDate < $today) {
        $error_msg = "Nové datum musí být v budoucnosti.";
    } else {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $new_id = substr(str_shuffle(str_repeat($chars, 5)), 0, 20);
        $formatted_date = $inputDate->format('Y-m-d');

        $ins = $mysqli->prepare("INSERT INTO drafts 
            (openclass_id, public_id, title, description, cover_image, date, start_time, end_time, address, price, capacity, level, led_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $ins->bind_param("sssssssssiiss", 
            $new_id, 
            $public_id, 
            $source['title'], 
            $source['description'], 
            $source['cover_image'], 
            $formatted_date, 
            $source['start_time'], 
            $source['end_time'], 
            $source['address'], 
            $source['price'], 
            $source['capacity'], 
            $source['level'], 
            $source['led_by']
        );

        if ($ins->execute()) {
            header("Location: preview.php?id=" . $new_id);
            exit;
        } else {
            $error_msg = "Chyba při vytváření kopie.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Opakovat lekci</title>
    <link rel="stylesheet" href="/source/styles/main.css">
    <link rel="stylesheet" href="openclass.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .server-error { background: #ff4757; color: white; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; }
        .repeat-info { margin-bottom: 25px; padding: 15px; background: #1a1a1a; border-radius: 12px; border: 1px solid #333; }
        .repeat-info small { color: #888; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; }
        .repeat-info h2 { margin: 5px 0 0 0; font-size: 1.1rem; color: #fff; }
    </style>
</head>
<body>

<nav class="top-nav">
    <span>Dancefy</span>
    <img src="../../source/assets/back.png" class="nav-back" onclick="window.location='index.php'">
</nav>

<div class="progress-bar">
    <div class="progress" style="width: 50%;"></div>
</div>

<div class="event-card" style="margin-top: 20px;">
    <div class="flex">
        <div class="content">
            <span>Opakování lekce</span>
            <p>Všechny údaje kromě data budou zkopírovány.</p>
        </div>
    </div>
</div>

<?php if ($error_msg): ?>
    <div class="server-error"><?= $error_msg ?></div>
<?php endif; ?>

<form method="POST">
    <div class="repeat-info">
        <small>Kopíruji lekci:</small>
        <h2><?= htmlspecialchars($source['title']) ?></h2>
    </div>

    <div class="desc">
        <label>Vyberte nové datum</label>
        <input type="date" name="date" min="<?= date('Y-m-d') ?>" required>
        <div class="s-hint">Zadejte den, kdy se bude lekce opakovat.</div>
    </div>

    <button type="submit" id="submitBtn">Uložit a náhled</button>
</form>

</body>
</html>