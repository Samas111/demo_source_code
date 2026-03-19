<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require __DIR__ . '/../secure/logic.php';

if (empty($_SESSION['public_id']) || !isset($_GET['id'])) {
    header("Location: listing.php");
    exit;
}

$public_id = $_SESSION['public_id'];
$openclass_id = $_GET['id'] ?? '';
$error_msg = "";

if (!preg_match('/^[A-Za-z0-9]{10,40}$/', $openclass_id)) {
    http_response_code(400);
    exit('Invalid ID');
}

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');


// 1. Load data + Fetch IBAN from user_profile
$sel = $mysqli->prepare("
    SELECT d.*, u.iban 
    FROM drafts d 
    LEFT JOIN user_profile u ON d.public_id = u.public_id 
    WHERE d.openclass_id = ? AND d.public_id = ? 
    LIMIT 1
");
$sel->bind_param('ss', $openclass_id, $public_id);
$sel->execute();
$res = $sel->get_result();
$draft = $res->fetch_assoc();
$sel->close();

if (!$draft) { 
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="/../source/styles/main.css">
        <style>
            body { background: #000; color: #fff; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
            .card { padding: 40px; max-width: 400px; }
            h1 { color: #ff4757; font-size: 1.8rem; margin-bottom: 10px; }
            p { color: #888; margin-bottom: 30px; line-height: 1.5; }
            .btn { background: #ff4757; color: #fff; padding: 12px 24px; border-radius: 12px; text-decoration: none; font-weight: 700; display: inline-block; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>🔒 Přístup odepřen</h1>
            <p>Tato lekce patří jinému tanečníkovi. Nemáš oprávnění k jejím úpravám.</p>
            <a href="/app.php" class="btn">Zpět do aplikace</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- Prefill Logic ---
$val_start    = ($draft['start_time'] !== '00:00') ? $draft['start_time'] : '';
$val_end      = ($draft['end_time'] !== '00:00')   ? $draft['end_time'] : '';
$val_date = '';
if ($draft['date'] !== '0000-00-00' && !empty($draft['date'])) {
    $dateObj = DateTime::createFromFormat('d.m.Y', $draft['date']);
    if ($dateObj) { $val_date = $dateObj->format('Y-m-d'); }
}
$val_capacity = ($draft['capacity'] != 0) ? $draft['capacity'] : '';
$val_price    = ($draft['price'] != 0)    ? $draft['price'] : '';
$val_is_qr    = $draft['is_qr'] ?? 0;
$user_iban    = $draft['iban'] ?? 'none';

// 2. Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start = $_POST['start_time'] ?? '';
    $end = $_POST['end_time'] ?? '';
    $raw_date = $_POST['date'] ?? '';
    $capacity = intval($_POST['capacity'] ?? 0);
    $price = intval($_POST['price'] ?? 0);
    
    // Logic for QR code checkbox
    $is_qr = (isset($_POST['is_qr']) && $user_iban !== 'none') ? 1 : 0;

    $today = new DateTime('today');
    $inputDate = DateTime::createFromFormat('Y-m-d', $raw_date);
    $startTime = new DateTime($start);
    $endTime = new DateTime($end);
    $diff = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60;

    if (!$inputDate || $inputDate < $today) {
        $error_msg = "Datum musí být v budoucnosti.";
    } elseif ($diff < 20) {
        $error_msg = "Lekce musí trvat alespoň 20 minut.";
    } elseif ($capacity > 40) {
        $error_msg = "Maximální kapacita je 40 osob.";
    } elseif ($price > 1000) {
        $error_msg = "Maximální cena je 1000 Kč.";
    }

    if (empty($error_msg)) {
        $formatted_date = $inputDate->format('Y-m-d');
        // Added is_qr to the update statement
        $upd = $mysqli->prepare("UPDATE drafts SET start_time = ?, end_time = ?, date = ?, capacity = ?, price = ?, is_qr = ? WHERE openclass_id = ? AND public_id = ?");
        $upd->bind_param('sssiisss', $start, $end, $formatted_date, $capacity, $price, $is_qr, $openclass_id, $public_id);
        if ($upd->execute()) {
            header("Location: place.php?id=" . $openclass_id);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create OpenClass</title>
    <link rel="stylesheet" href="/../source/styles/main.css">
    <link rel="stylesheet" href="openclass.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .error-hint { color: #ff4757 !important; font-weight: 600; }
        .server-error { background: #ff4757; color: white; padding: 10px; border-radius: 8px; margin-bottom: 15px; text-align: center; }
        .progress { transition: width 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

        /* QR feature styles */
        .qr-option-container { margin-top: 40px; padding: 15px 15px 5px 15px; border-radius: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); }
        .qr-checkbox-wrapper { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .qr-checkbox-wrapper input { width: 20px; height: 20px; accent-color: #ff4757; cursor: pointer; }
        .qr-checkbox-wrapper span { font-size: 0.95rem; font-weight: 500; color: #fff; }
        .qr-no-iban {color: #ffffff9b; font-size: 0.85rem; font-weight: 400; display: flex; align-items: center; gap: 5px; }
    </style>
</head>
<body>

<nav class="top-nav">
    <span>Dancefy</span>
    <img src="/../source/assets/back.png" class="nav-back" onclick="window.location='listing.php?id=<?= htmlspecialchars($openclass_id) ?>'">
</nav>

<div class="progress-bar">
    <div class="progress" id="mainProgress" style="width: 33%;"></div>
</div>

<div class="event-card">
    <div class="flex">
        <div class="content">
            <span><?= htmlspecialchars($draft['title']) ?></span>
            <p><?= htmlspecialchars($draft['description']) ?></p>
        </div>
        <img src="/../uploads/openclasses/<?= htmlspecialchars($draft['cover_image']) ?>">
    </div>
</div>

<?php if ($error_msg): ?>
    <div class="server-error"><?= $error_msg ?></div>
<?php endif; ?>

<form method="POST" id="logisticForm">
    <div class="title">
        <div class="flex">
            <label>Začátek</label>
            <label>Konec</label>
        </div>
        <div class="flex">
            <input type="time" name="start_time" value="<?= htmlspecialchars($val_start) ?>" required>
            <input type="time" name="end_time" value="<?= htmlspecialchars($val_end) ?>" required>
        </div>
        <div class="s-hint" id="durationHint">Bude trvat -- minut</div>
    </div>

    <div class="desc">
        <label>Datum akce</label>
        <input type="date" name="date" value="<?= htmlspecialchars($val_date) ?>" min="<?= date('Y-m-d') ?>" required>
        <div class="s-hint" id="dayHint">Tento den je --</div>
    </div>

    <div class="split"></div>

    <div class="title">
        <div class="flex">
            <label>Kapacita</label>
            <label>Cena</label>
        </div>
        <div class="flex">
            <input type="number" name="capacity" min="1" max="40" placeholder="Kapacita" value="<?= htmlspecialchars($val_capacity) ?>" required>
            <input type="number" name="price" min="0" max="1000" placeholder="Cena" value="<?= htmlspecialchars($val_price) ?>" required>
        </div>
        <div class="s-hint" id="revenueHint">0 x 0 = 0 Kč</div>
    </div>

    <div class="qr-option-container">
        <?php if ($user_iban !== 'none' && !empty($user_iban)): ?>
            <label class="qr-checkbox-wrapper">
                <input type="checkbox" name="is_qr" value="1" <?= ($val_is_qr == 1) ? 'checked' : '' ?>>
                <span>Použít Dancefy QR platby?</span>
            </label>
        <?php else: ?>
            <div class="qr-no-iban">
                <span>💡 Přidej si IBAN k účtu na generování QR kódů pro automatické platby.</span>
            </div>
        <?php endif; ?>
    </div>

    <button type="submit" id="submitBtn" style="margin-top: 20px;">Pokračovat</button>
</form>

<script>
const form = document.getElementById('logisticForm');
const startInp = form.start_time;
const endInp = form.end_time;
const dateInp = form.date;
const capInp = form.capacity;
const priceInp = form.price;
const submitBtn = document.getElementById('submitBtn');
const mainProgress = document.getElementById('mainProgress');

function updateLogic() {
    let completedMilestones = 0;
    const totalMilestones = 5; 
    const baseProgress = 33; 
    const stageWeight = 33; 

    if (startInp.value) {
        completedMilestones++;
    }

    let timeValid = false;
    if (endInp.value) {
        if (startInp.value) {
            const start = new Date(`2000-01-01T${startInp.value}`);
            const end = new Date(`2000-01-01T${endInp.value}`);
            let diff = (end - start) / 60000;
            
            if (diff >= 20) {
                document.getElementById('durationHint').innerText = `Bude trvat ${diff} minut`;
                document.getElementById('durationHint').classList.remove('error-hint');
                completedMilestones++;
                timeValid = true;
            } else {
                document.getElementById('durationHint').innerText = "Chyba: Minimálně 20 minut!";
                document.getElementById('durationHint').classList.add('error-hint');
            }
        } else {
            completedMilestones++;
        }
    }

    let dateValid = false;
    if (dateInp.value) {
        const selected = new Date(dateInp.value);
        selected.setHours(0,0,0,0);
        const today = new Date();
        today.setHours(0,0,0,0);

        if (selected < today) {
            document.getElementById('dayHint').innerText = "Chyba: Datum musí být v budoucnosti!";
            document.getElementById('dayHint').classList.add('error-hint');
        } else {
            const days = ['Neděle', 'Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota'];
            document.getElementById('dayHint').innerText = `Tento den je ${days[selected.getDay()]}`;
            document.getElementById('dayHint').classList.remove('error-hint');
            completedMilestones++;
            dateValid = true;
        }
    }

    let capValid = false;
    const cVal = parseInt(capInp.value);
    if (capInp.value && cVal > 0 && cVal <= 40) {
        completedMilestones++;
        capValid = true;
    }

    let priceValid = false;
    const pVal = parseInt(priceInp.value);
    if (priceInp.value && pVal >= 0 && pVal <= 1000) {
        completedMilestones++;
        priceValid = true;
    }

    if (capValid && priceValid) {
        const rev = cVal * pVal;
        document.getElementById('revenueHint').innerText = `Při plné kapacitě: ${rev} Kč`;
        document.getElementById('revenueHint').classList.remove('error-hint');
    } else if (cVal > 40 || pVal > 1000) {
        document.getElementById('revenueHint').innerText = "Chyba: Kapacita max 40, Cena max 1000 Kč!";
        document.getElementById('revenueHint').classList.add('error-hint');
    }

    const currentProgress = baseProgress + ((completedMilestones / totalMilestones) * stageWeight);
    mainProgress.style.width = currentProgress + "%";

    const isReady = (timeValid && dateValid && capValid && priceValid && startInp.value && endInp.value);
    submitBtn.disabled = !isReady;
    submitBtn.style.opacity = isReady ? "1" : "0.5";
}

[startInp, endInp, dateInp, capInp, priceInp].forEach(el => {
    el.addEventListener('input', updateLogic);
});

updateLogic();
</script>

</body>
</html>