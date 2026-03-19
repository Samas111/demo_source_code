<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require __DIR__ . '/../secure/logic.php';

if (empty($_SESSION['public_id'])) {
    http_response_code(401);
    exit;
}

$public_id = $_SESSION['public_id'];
$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

$draft = null;
$edit_mode = false;
$error_msg = "";

if (!empty($_GET['id'])) {
    $openclass_id = $_GET['id'];
    $stmt = $mysqli->prepare("SELECT * FROM drafts WHERE openclass_id = ? AND public_id = ? LIMIT 1");
    $stmt->bind_param("ss", $openclass_id, $public_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $draft = $result->fetch_assoc();
        $edit_mode = true;
    } else {
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
}

function resizeAndSaveImage($tmpPath, $destPath, $maxWidth = 1920) {
    list($width, $height, $type) = getimagesize($tmpPath);
    
    $srcImg = null;
    switch ($type) {
        case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($tmpPath); break;
        case IMAGETYPE_PNG:  $srcImg = imagecreatefrompng($tmpPath);  break;
        case IMAGETYPE_WEBP: $srcImg = imagecreatefromwebp($tmpPath); break;
        default: return false;
    }

    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmpPath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $srcImg = imagerotate($srcImg, 180, 0);
                    break;
                case 6:
                    $srcImg = imagerotate($srcImg, -90, 0);
                    $tmp = $width; $width = $height; $height = $tmp;
                    break;
                case 8:
                    $srcImg = imagerotate($srcImg, 90, 0);
                    $tmp = $width; $width = $height; $height = $tmp;
                    break;
            }
        }
    }

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    $newImg = imagecreatetruecolor($newWidth, $newHeight);
    
    imagealphablending($newImg, false);
    imagesavealpha($newImg, true);

    imagecopyresampled($newImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    $success = imagejpeg($newImg, $destPath, 85);
    
    imagedestroy($newImg);
    imagedestroy($srcImg);
    return $success;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['desc'] ?? '');

    $contact_regex = '/([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,})|https?:\/\/|www\.|[a-z0-9-]+\.(?:cz|com|net|org|sk|eu|info|me|biz|io|tv|dance)|(\b\d{3}[-.\s]?\d{3}[-.\s]?\d{3,}\b)|instagram|facebook|fb\b|ig\b|@|tik\s?tok/i';
    
    if (!preg_match('/^[a-zA-ZáčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ ]+$/u', $title) || mb_strlen($title) > 20) {
        $error_msg = "Název musí obsahovat pouze písmena a mít max 20 znaků.";
    } elseif (mb_strlen($description) > 500) {
        $error_msg = "Popis je příliš dlouhý (max 500 symbolů).";
    } elseif (preg_match($contact_regex, $description)) {
        $error_msg = "externí odkazy nejsou povoleny";
    }

    if (empty($error_msg)) {
        if ($edit_mode) {
            $openclass_id = $draft['openclass_id'];
        } else {
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $openclass_id = substr(str_shuffle(str_repeat($chars, 5)), 0, 20);
        }

        $filename = $draft['cover_image'] ?? 'default-openclass.png';

        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = mime_content_type($_FILES['cover']['tmp_name']);
            if (isset($allowed_types[$mime])) {
                $filename = 'openclass_' . $public_id . '_' . time() . '.jpg';
                $upload_path = __DIR__ . '/../uploads/openclasses/' . $filename;
                resizeAndSaveImage($_FILES['cover']['tmp_name'], $upload_path);
            }
        }

        if ($edit_mode) {
            $stmt = $mysqli->prepare("UPDATE drafts SET title = ?, description = ?, cover_image = ? WHERE openclass_id = ? AND public_id = ?");
            $stmt->bind_param("sssss", $title, $description, $filename, $openclass_id, $public_id);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO drafts (openclass_id, public_id, title, description, cover_image, date, start_time, end_time, address, price, capacity, level, led_by) VALUES (?, ?, ?, ?, ?, '00.00.0000', '00:00', '00:00', 'none', 0, 0, 'beginner', 'NONE')");
            $stmt->bind_param("sssss", $openclass_id, $public_id, $title, $description, $filename);
        }

        if ($stmt->execute()) {
            header("Location: logistic.php?id=" . $openclass_id);
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
        .error-active { color: #ff4757 !important; font-weight: 600; }
        .char-count { float: right; font-size: 0.75rem; color: #888; }
        .server-error { background: #ff4757; color: white; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; text-align: center; }
        #submitBtn:disabled { background: #ccc; cursor: not-allowed; opacity: 0.5; }
        .progress { transition: width 0.4s ease; }
    </style>
</head>
<body>

<nav class="top-nav">
    <span>Dancefy</span>
    <img src="/../source/assets/back.png" class="nav-back" onclick="window.location='/post/post.php'">
</nav>

<div class="progress-bar">
    <div class="progress" id="mainProgress" style="width: 0%;"></div>
</div>

<div class="cover-photo" id="coverContainer" style="cursor: pointer; position: relative;">
    <?php 
        $has_image = (!empty($draft['cover_image']) && $draft['cover_image'] !== 'default-openclass.png'); 
        $img_src = $has_image ? '/uploads/openclasses/' . htmlspecialchars($draft['cover_image']) : 'default-openclass.png';
    ?>
    <img src="<?= $img_src ?>" alt="img" id="coverPreview" data-initial-img="<?= $has_image ? 'true' : 'false' ?>">
    
    <div id="changeOverlay" style="display: <?= $has_image ? 'block' : 'none' ?>; position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.6); color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem;">
        Změnit fotku
    </div>
</div>

<?php if ($error_msg): ?>
    <div class="server-error"><?= $error_msg ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="mainForm">
    <input type="file" name="cover" id="coverInput" style="display:none;" accept="image/*">
    
    <div class="title">
        <label for="title">Název Openclass</label>
        <input type="text" id="titleInp" name="title" maxlength="25" required value="<?= htmlspecialchars($draft['title'] ?? '') ?>">
        <div class="s-hint" id="titleHint">Max 20 písmen (bez čísel a symbolů)</div>
    </div>

    <div class="desc">
        <label for="desc">Popis <span class="char-count" id="count">0/500</span></label>
        <textarea rows="4" id="descInp" name="desc" required><?= htmlspecialchars($draft['description'] ?? '') ?></textarea>
        <div class="s-hint" id="descHint">Popište lekci bez kontaktních údajů.</div>
    </div>

    <button type="submit" id="submitBtn">Pokračovat</button>
</form>

<script>
const titleInp = document.getElementById('titleInp');
const descInp = document.getElementById('descInp');
const coverInput = document.getElementById('coverInput');
const coverPreview = document.getElementById('coverPreview');
const mainProgress = document.getElementById('mainProgress');
const titleHint = document.getElementById('titleHint');
const descHint = document.getElementById('descHint');
const submitBtn = document.getElementById('submitBtn');
const countLabel = document.getElementById('count');

const contactRegex = /([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,})|https?:\/\/|www\.|[a-z0-9-]+\.(?:cz|com|net|org|sk|eu|info|me|biz|io|tv|dance)|(\b\d{3}[-.\s]?\d{3}[-.\s]?\d{3,}\b)|instagram|facebook|fb\b|ig\b|@|tik\s?tok/i;
const titleRegex = /^[a-zA-ZáčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ ]*$/;

function validate() {
    let completedPoints = 0;
    const totalPoints = 3; 
    const maxStagePercent = 33;

    if (coverInput.files.length > 0 || coverPreview.getAttribute('data-initial-img') === 'true') {
        completedPoints++;
    }

    let isTitleValid = true;
    const tVal = titleInp.value.trim();
    if (tVal === "") {
        isTitleValid = false;
    } else if (tVal.length > 20) {
        titleHint.innerText = "Příliš dlouhé! Max 20 znaků.";
        titleHint.classList.add('error-active');
        isTitleValid = false;
    } else if (!titleRegex.test(tVal)) {
        titleHint.innerText = "Pouze písmena, žádná čísla ani symboly!";
        titleHint.classList.add('error-active');
        isTitleValid = false;
    } else {
        titleHint.innerText = "Výstižný název lekce (styl/směr/záměr)";
        titleHint.classList.remove('error-active');
        completedPoints++;
    }

    let isDescValid = true;
    const dVal = descInp.value;
    countLabel.innerText = `${dVal.length}/500`;
    
    if (dVal.trim() === "") {
        isDescValid = false;
    } else if (dVal.length > 500) {
        descHint.innerText = "Popis překročil limit 500 znaků!";
        descHint.classList.add('error-active');
        isDescValid = false;
    } else if (contactRegex.test(dVal)) {
        descHint.innerText = "externí odkazy nejsou povoleny";
        descHint.classList.add('error-active');
        isDescValid = false;
    } else {
        descHint.innerText = "Stručně popište plán lekce.";
        descHint.classList.remove('error-active');
        completedPoints++;
    }

    const calculatedWidth = (completedPoints / totalPoints) * maxStagePercent;
    mainProgress.style.width = calculatedWidth + "%";

    submitBtn.disabled = !(completedPoints === totalPoints);
}

titleInp.addEventListener('input', validate);
descInp.addEventListener('input', validate);

coverInput.onchange = function (evt) {
    const [file] = this.files;
    if (file) {
        coverPreview.src = URL.createObjectURL(file);
        validate();
    }
}

validate();

const coverContainer = document.getElementById('coverContainer');
const changeOverlay = document.getElementById('changeOverlay');

coverContainer.onclick = function(e) {
    const hasImg = coverInput.files.length > 0 || coverPreview.getAttribute('data-initial-img') === 'true';
    
    if (!hasImg) {
        coverInput.click();
    } else {
        if (e.target === changeOverlay || changeOverlay.contains(e.target)) {
            coverInput.click();
        }
    }
};

coverInput.onchange = function (evt) {
    const [file] = this.files;
    if (file) {
        coverPreview.src = URL.createObjectURL(file);
        coverPreview.setAttribute('data-initial-img', 'true');
        changeOverlay.style.display = 'block'; 
        validate();
    }
}
</script>

</body>
</html>