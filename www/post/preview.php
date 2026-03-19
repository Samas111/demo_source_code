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

$stmt = $mysqli->prepare("SELECT * FROM drafts WHERE openclass_id = ? AND public_id = ? LIMIT 1");
$stmt->bind_param('ss', $openclass_id, $public_id);
$stmt->execute();
$result = $stmt->get_result();
$openclass = $result->fetch_assoc();
$stmt->close();

if (!$openclass) { exit('Draft nebyl nalezen.'); }

// Publish Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_confirmed'])) {
    $mysqli->begin_transaction();
    try {
        // 1. Move from drafts to openclasses (copies all columns exactly)
        $pub = $mysqli->prepare("
            INSERT INTO openclasses (
                public_id,
                openclass_id,
                title,
                date,
                start_time,
                end_time,
                address,
                price,
                capacity,
                description,
                level,
                cover_image,
                created_at,
                checked,
                led_by,
                canceled,
                latitude,
                longitude,
                is_qr
            )
            SELECT
                public_id,
                openclass_id,
                title,
                date,
                start_time,
                end_time,
                address,
                price,
                capacity,
                description,
                level,
                cover_image,
                created_at,
                checked,
                led_by,
                canceled,
                latitude,
                longitude,
                is_qr
            FROM drafts
            WHERE openclass_id = ? AND public_id = ?
        ");
        $pub->bind_param("ss", $openclass_id, $public_id);
        $pub->execute();

        // 2. Delete from drafts
        $del = $mysqli->prepare("DELETE FROM drafts WHERE openclass_id = ?");
        $del->bind_param('s', $openclass_id);
        $del->execute();

        $mysqli->commit();
        // Redirect to the final listing or success page
        header("Location: /../openclass-listing.php?id=" . $openclass_id . "&animation=1");
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        exit("Chyba při zveřejnění: " . $e->getMessage());
    }
}

// Duration for UI
$start = new DateTime($openclass['start_time']);
$end = new DateTime($openclass['end_time']);
$durationMinutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;

$levelMap = ['beginner' => 'Začátečník', 'intermediate' => 'Pokročilý', 'pro' => 'Profi'];
$led_by = $openclass['led_by'];
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?php echo htmlspecialchars($openclass['title']); ?></title>
    <link rel="stylesheet" href="/../openclass-web.css">
    <link rel="stylesheet" href="/../register.css">
    <link rel="stylesheet" href="openclass.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <style>
        #map {
            height: 400px;
            width: 100%;
            z-index: 1;
            background: #0D0314;
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
            transform: rotate(-40deg);
            border: 3px solid #fff;
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
    </style>
</head>
<body>

<div class="modal-overlay" id="confirmModal">
    <div class="modal-card">
        <h2>Zveřejnit lekci?</h2>
        <p>Jakmile lekci publikujete, uvidí ji tanečníci na Dancefy a budou se moci registrovat.</p>
        <form method="POST">
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModal()">Zrušit</button>
                <button type="submit" name="publish_confirmed" class="btn-confirm">Ano, publikovat</button>
            </div>
        </form>
    </div>
</div>

<nav class="top-nav" style="background-color: var(--card-color) !important; opacity: 1 !important;">
    <span>Pouze náhled</span>
    <img src="/../source/assets/back.png" class="nav-back" onclick="window.location='post.php'"  style="opacity: 1 !important;">
</nav>

<div class="app-shell">
    <div class="her-img">
        <img src="<?php echo '/uploads/openclasses/' . htmlspecialchars($openclass['cover_image']); ?>" alt="Cover">
    </div>

    <div class="main-content">
        <div class="workshop-card">
            <div class="workshop-header">
                <div class="workshop-title"><?php echo htmlspecialchars($openclass['title']); ?></div>
            </div>

            <div class="workshop-location"><?php echo htmlspecialchars($openclass['address']); ?></div>

            <div class="workshop-tags">
                <div class="tag"><?php echo date('d.m.Y', strtotime($openclass['date'])); ?></div>
                <div class="tag"><?php echo substr($openclass['start_time'], 0, 5); ?> – <?php echo substr($openclass['end_time'], 0, 5); ?></div>
                <div class="tag"><?php echo (int)$durationMinutes; ?> Minut</div>
                <div class="tag"><?php echo htmlspecialchars($levelMap[$openclass['level']] ?? $openclass['level']); ?></div>
                <?php if($led_by != "NONE" && !empty($led_by)){ ?>
                    <div class="tag">Vede @<?php echo htmlspecialchars($led_by);?></div>
                <?php } ?>
            </div>

            <div class="workshop-desc">
                <?php echo nl2br(htmlspecialchars($openclass['description'])); ?>
            </div>

            <div class="workshop-actions">
                <div class="btn-group">
                    <button class="share-btn" onclick="openModal()">Publikovat</button>
                    <button class="promote-btn" onclick="window.location='place.php?id=<?php echo htmlspecialchars($openclass_id) ?>'">Upravit</button>
                </div>
                <div class="price">
                    Cena<br><span><?php echo (int)$openclass['price']; ?> Kč</span>
                </div>
            </div>
        </div>
        <?php if (!empty($openclass['latitude']) && !empty($openclass['longitude'])): ?>
            <div id="map"></div>
        <?php endif; ?>
    </div>
</div>

<script>
    const modal = document.getElementById('confirmModal');
    function openModal() { modal.style.display = 'flex'; }
    function closeModal() { modal.style.display = 'none'; }
    
    window.onclick = function(event) {
        if (event.target == modal) { closeModal(); }
    }
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const lat = <?= $openclass['latitude'] ?? 0 ?>;
    const lng = <?= $openclass['longitude'] ?? 0 ?>;

    if (lat !== 0 && lng !== 0) {
        const map = L.map('map', {
            center: [lat, lng],
            zoom: 15,
            zoomControl: false,
            boxZoom: false,
            attributionControl: false,
            doubleClickZoom: false,
            boxZoom: false
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 20
        }).addTo(map);

        const dancefyIcon = L.divIcon({
            className: 'custom-pin', 
            html: '<div class="pin-main"></div>',
            iconSize: [24, 24],
            iconAnchor: [12, 24] 
        });

        L.marker([lat, lng], { icon: dancefyIcon }).addTo(map);
    }
});
</script>

</body>
</html>