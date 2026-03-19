<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

if (empty($_SESSION['public_id']) || empty($_COOKIE['dancefy_token'])) {
    http_response_code(401);
    exit('AUTH_FAILED');
}

$publicId = $_SESSION['public_id'];

require __DIR__ . '/../secure/logic.php';
require __DIR__ . '/../languages/loader.php';

/* =========================
   SAVE PROFILE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username    = trim($_POST['username'] ?? '');
    $bio         = trim($_POST['bio'] ?? '');
    $name        = trim($_POST['name'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $dance_group = trim($_POST['dance_group'] ?? ''); 

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();

    try {
        /* 1. UPDATE USERNAME (users table) */
        if (!empty($username)) {
            $u = $conn->prepare("UPDATE users SET username = ? WHERE public_id = ?");
            $u->bind_param("ss", $username, $publicId);
            $u->execute();
            $u->close();
        }

        /* 2. PROFILE DATA (user_profile table) */
        $p = $conn->prepare("
            INSERT INTO user_profile (public_id, name, bio, location, city, dance_group)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                bio = VALUES(bio),
                location = VALUES(location),
                city = VALUES(city),
                dance_group = VALUES(dance_group)
        ");
        $p->bind_param("ssssss", $publicId, $name, $bio, $location, $city, $dance_group);
        $p->execute();
        $p->close();

        /* 3. PROFILE PICTURE */
        if (isset($_FILES['pfp']) && is_uploaded_file($_FILES['pfp']['tmp_name'])) {
            if ($_FILES['pfp']['error'] === UPLOAD_ERR_OK) {

                if (!extension_loaded('imagick')) {
                    exit;
                }

                $tmp = $_FILES['pfp']['tmp_name'];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmp);

                if (!in_array($mime, ['image/jpeg','image/png','image/webp'])) {
                    exit;
                }

                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profile-pictures';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $fileName = 'pfp_' . $publicId . '_' . time() . '.jpg';
                $fullPath = $uploadDir . '/' . $fileName;

                $MAX = 1200;
                $JPEG_QUALITY = 82;

                $img = new Imagick($tmp);

                $img->autoOrient();
                $img->stripImage();

                $w = $img->getImageWidth();
                $h = $img->getImageHeight();

                if ($w > $MAX || $h > $MAX) {
                    $img->resizeImage(
                        $MAX,
                        $MAX,
                        Imagick::FILTER_LANCZOS,
                        1,
                        true
                    );
                }

                $img->setImageColorspace(Imagick::COLORSPACE_SRGB);
                $img->setImageFormat('jpeg');
                $img->setImageCompression(Imagick::COMPRESSION_JPEG);
                $img->setImageCompressionQuality($JPEG_QUALITY);
                $img->setInterlaceScheme(Imagick::INTERLACE_JPEG);

                $img->writeImage($fullPath);
                $img->destroy();

                $old = $conn->prepare("SELECT pfp_path FROM user_profile WHERE public_id = ?");
                $old->bind_param("s", $publicId);
                $old->execute();
                $oldRes = $old->get_result()->fetch_assoc();
                $old->close();

                if (!empty($oldRes['pfp_path'])) {
                    $oldFile = $_SERVER['DOCUMENT_ROOT'] . $oldRes['pfp_path'];
                    if (file_exists($oldFile) && !str_contains($oldFile, 'default')) {
                        unlink($oldFile);
                    }
                }

                $path = '/uploads/profile-pictures/' . $fileName;

                $pfp = $conn->prepare("UPDATE user_profile SET pfp_path = ? WHERE public_id = ?");
                $pfp->bind_param("ss", $path, $publicId);
                $pfp->execute();
                $pfp->close();
            }
        }



        $conn->commit();
        header("Location: profile.php?success=saved");
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        exit;
    }
}

/* =========================
   LOAD PROFILE DATA
========================= */
$q = $conn->prepare("
    SELECT u.username, p.name, p.bio, p.location, p.city, p.pfp_path, p.dance_group 
    FROM users u 
    LEFT JOIN user_profile p ON p.public_id = u.public_id 
    WHERE u.public_id = ?
");
$q->bind_param("s", $publicId);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();

function safe($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }


?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Nastavení Profilu</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
</head>
<body>

<nav class="nav">
    <a href="../settings.php" class="nav-btn nav-left" aria-label="Back">
        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" /></svg>
    </a>
</nav>

<form method="POST" enctype="multipart/form-data" id="profileForm">

<div class="profile-section">
    <div class="pfp-wrapper">
        <img src="../<?php echo safe($r['pfp_path'] ?? '/source/assets/default.png'); ?>" class="pfp" id="pfpPreview">
        <label class="pfp-pen">✎
            <input type="file" name="pfp" accept="image/*" hidden id="pfpInput">
        </label>
    </div>
</div>

<section>
    <div class="title"><?= $T['username'] ?? 'Uživatelské jméno'; ?></div>
    <input type="text" name="username" value="<?php echo safe($r['username']); ?>" disabled style="opacity: 30%;">
</section>

<section>
    <div class="title">Jméno</div>
    <input name="name" maxlength="20" value="<?php echo safe($r['name']); ?>">
</section>

<section id="openStyles">
    <div class="title">Taneční styly</div>
    <div id="stylesPreview" class="styles-preview"></div>
    <input type="hidden" name="dance_group" id="styleValue" value="<?php echo safe($r['dance_group']); ?>">
</section>

<section>
    <div class="title"><?= $T['bio'] ?? 'O mně'; ?></div>
    <textarea name="bio" rows="3" maxlength="200"><?php echo safe($r['bio']); ?></textarea>
</section>

<section>
    <div class="title"><?= $T['studio'] ?? 'Studio'; ?></div>
    <input type="text" name="location" maxlength="20" value="<?php echo safe($r['location']); ?>">
</section>

<section>
    <div class="title">Město</div>
    <input type="text" name="city" maxlength="20" value="<?php echo safe($r['city']); ?>">
</section>

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="save-bar" id="saveBar" style="display: none;">
    <button type="submit" class="save-btn"><?= $T['save'] ?? 'Uložit'; ?></button>
</div>

</form>

<div class="sheet-backdrop" id="sheetBackdrop"></div>
<div class="style-sheet" id="styleSheet">
    <div class="sheet-header">
        <span id="styleCounter" style="color:#888;">0 / 3 vybráno</span>
        <button type="button" id="closeSheet">Hotovo</button>
    </div>
    <div class="sheet-pills" id="sheetPills"></div>
</div>

<script>
    
    window.CSRF_TOKEN = "<?= $_SESSION['csrf_token'] ?>";

    const originalFetch = window.fetch;

    window.fetch = function (url, options = {}) {

        options.headers = options.headers || {};

        if (!options.headers['X-CSRF-Token']) {
            options.headers['X-CSRF-Token'] = window.CSRF_TOKEN;
        }

        return originalFetch(url, options);
    };
</script>

<script>
    const form = document.getElementById('profileForm');
    const saveBar = document.getElementById('saveBar');
    const pfpInput = document.getElementById('pfpInput');
    const pfpPreview = document.getElementById('pfpPreview');

    form.querySelectorAll('input, textarea').forEach(el => {
        el.addEventListener('input', () => {
            saveBar.style.display = 'block';
        });
    });

    pfpInput.addEventListener('change', () => {
        const file = pfpInput.files[0];
        if (file) {
            pfpPreview.src = URL.createObjectURL(file);
            saveBar.style.display = 'block';
        }
    });

    const STYLES = [
        "Hip Hop", "Breaking", "House", "Locking", "Popping", "Waacking", 
        "Voguing", "Krump", "Litefeet", "Tutting", "Animation", "Roboting",
        
        "Afro", "Afrobeats", "Amapiano", "Dancehall", "Kuduro", "Coupe Decale", 
        "Ndombolo", "Azonto", "Gqom", "Pantsula",

        "Street Jazz", "Storytelling choreo", "Fusion", 
        
        "Choreography", "Commercial", "Heels", "Jazz Funk", "Lyrical Jazz", 
        "Contemporary", "Modern", "Ballet", "Floorwork", "Burlesque",
        
        "Salsa", "Bachata", "Zouk", "Kizomba", "Cha-Cha", "Rumba", "Samba", 
        "Merengue", "Reggaeton", "Forró", "Tango",
        
        "Electro", "Tecktonik", "Jumpstyle", "Hardstyle", "Shuffle", 
        "Cutting Shapes", "Melbourne Shuffle", "Jersey Club",
        
        "Experimental", "Improvisation", "Contact Improv", "Folk", "Flamenco", 
        "Capoeira", "Acro Dance", "Pole Dance", "Bollywood"
    ];    
    
    const preview = document.getElementById("stylesPreview");
    const hidden = document.getElementById("styleValue");
    const sheet = document.getElementById("styleSheet");
    const backdrop = document.getElementById("sheetBackdrop");
    const pillsEl = document.getElementById("sheetPills");
    const counter = document.getElementById("styleCounter");

    let selected = hidden.value ? hidden.value.split(",").filter(Boolean) : [];

    function syncStyles() {
        hidden.value = selected.join(",");
        preview.innerHTML = "";
        if (selected.length === 0) {
            preview.innerHTML = '<span style="color:#444; font-size:0.85rem;">Klikněte pro výběr...</span>';
        } else {
            selected.forEach(s => {
                const p = document.createElement("div");
                p.className = "style-pill selected";
                p.textContent = s;
                preview.appendChild(p);
            });
        }
    }

    function renderSheet() {
        pillsEl.innerHTML = "";
        counter.textContent = `${selected.length} / 4 vybráno`;
        STYLES.forEach(s => {
            const isSel = selected.includes(s);
            const p = document.createElement("div");
            p.className = "style-pill" + (isSel ? " selected" : "");
            if (selected.length >= 4 && !isSel) p.classList.add("disabled");
            
            p.textContent = s;
            p.onclick = () => {
                if (isSel) {
                    selected = selected.filter(x => x !== s);
                } else if (selected.length < 4) {
                    selected.push(s);
                }
                saveBar.style.display = 'block';
                syncStyles();
                renderSheet();
            };
            pillsEl.appendChild(p);
        });
    }

    document.getElementById("openStyles").onclick = () => {
        sheet.classList.add("active");
        backdrop.style.display = "block";
        renderSheet();
    };

    document.getElementById("closeSheet").onclick = backdrop.onclick = () => {
        sheet.classList.remove("active");
        setTimeout(() => { backdrop.style.display = "none"; }, 300);
    };

    syncStyles();
</script>

</body>
</html>