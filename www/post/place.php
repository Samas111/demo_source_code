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
$openclass_id = $_GET['id'];

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

// 1. Fetch data
$sel = $mysqli->prepare("SELECT title, description, cover_image, address, latitude, longitude FROM drafts WHERE openclass_id = ? AND public_id = ? LIMIT 1");
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

$val_address = ($draft['address'] !== 'none') ? $draft['address'] : '';
$val_lat = $draft['latitude'] ?? 50.0755;
$val_lng = $draft['longitude'] ?? 14.4378;
// Check if map was actually interacted with (not just default)
$has_interacted = ($draft['latitude'] !== null && $draft['latitude'] != 0);

// 2. Update logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address'] ?? '');
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;

    $upd = $mysqli->prepare("UPDATE drafts SET address = ?, latitude = ?, longitude = ? WHERE openclass_id = ? AND public_id = ?");
    $upd->bind_param('sddss', $address, $lat, $lng, $openclass_id, $public_id);
    
    if ($upd->execute()) {
        header("Location: preview.php?id=" . $openclass_id);
        exit;
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
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map-container { 
            height: 280px; 
            width: 100%; 
            border-radius: 16px; 
            margin-top: 10px; 
            z-index: 1; 
            border: 1px solid #333; 
            background: #1a1a1a;
        }

        .custom-pin {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .pin-main {
            width: 24px;
            height: 24px;
            background: #ff4757;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            border: 3px solid #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .pin-main::after {
            content: '';
            width: 8px;
            height: 8px;
            background: #fff;
            position: absolute;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            margin-left: -4px;
            margin-top: -4px;
        }
        .progress { transition: width 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    </style>
</head>
<body>

<nav class="top-nav">
    <span>Dancefy</span>
    <img src="/../source/assets/back.png" class="nav-back" onclick="window.location='logistic.php?id=<?= htmlspecialchars($openclass_id) ?>'">
</nav>

<div class="progress-bar">
    <div class="progress" id="mainProgress" style="width: 66%;"></div>
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

<form method="POST" id="placeForm">
    <div class="title">
        <label for="address">Adresa</label>
        <input type="text" name="address" id="addressInp" placeholder="Ulice, Město..." value="<?= htmlspecialchars($val_address) ?>" required>
        <div class="s-hint">Uveďte název studia nebo přesnou adresu</div>
    </div>
    
    <div class="split"></div>

    <div class="map">
        <label>Na mapě</label>
        <div id="map-container"></div>
        <div class="s-hint" id="mapHint">Kliknutím označte přesný vchod do budovy.</div>
    </div>

    <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars($val_lat) ?>">
    <input type="hidden" name="lng" id="lng" value="<?= htmlspecialchars($val_lng) ?>">

    <button type="submit" id="submitBtn">Uložit a náhled</button>
</form>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const addressInp = document.getElementById('addressInp');
    const mainProgress = document.getElementById('mainProgress');
    const submitBtn = document.getElementById('submitBtn');
    
    // Tracking interaction for progress
    let mapInteracted = <?= $has_interacted ? 'true' : 'false' ?>;

    function updateProgress() {
        let completed = 0;
        const total = 2; // 1. Address string, 2. Map pin placement
        const base = 66;
        const weight = 34;

        if (addressInp.value.trim().length > 3) completed++;
        if (mapInteracted) completed++;

        const currentWidth = base + ((completed / total) * weight);
        mainProgress.style.width = currentWidth + "%";

        submitBtn.disabled = !(completed === total);
        submitBtn.style.opacity = (completed === total) ? "1" : "0.5";
    }

    // Leaflet Logic
    const savedLat = <?= $val_lat ?>;
    const savedLng = <?= $val_lng ?>;
    
    const map = L.map('map-container', {
        zoomControl: false 
    }).setView([savedLat, savedLng], 15);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '© CARTO',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);

    const dancefyIcon = L.divIcon({
        className: 'custom-pin',
        html: '<div class="pin-main"></div>',
        iconSize: [30, 42],
        iconAnchor: [15, 42]
    });

    let marker = L.marker([savedLat, savedLng], {
        icon: dancefyIcon,
        draggable: true
    }).addTo(map);

    function updateCoords(lat, lng) {
        document.getElementById('lat').value = lat;
        document.getElementById('lng').value = lng;
        mapInteracted = true;
        document.getElementById('mapHint').innerText = "Vchod označen ✓";
        updateProgress();
    }

    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        updateCoords(e.latlng.lat, e.latlng.lng);
    });

    marker.on('dragend', function(e) {
        const position = marker.getLatLng();
        updateCoords(position.lat, position.lng);
    });

    addressInp.addEventListener('input', updateProgress);
    
    // Initial Run
    updateProgress();
</script>

</body>
</html>