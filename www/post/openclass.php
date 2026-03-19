<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require __DIR__ . '/../secure/logic.php';
require __DIR__ . '/../languages/loader.php';

if (empty($_SESSION['public_id'])) {
    http_response_code(401);
    header("Location: ../registration/register-server-logic/login.php");
    exit;
}

error_reporting(E_ALL);

$public_id = $_SESSION['public_id'];

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    exit('DB error');
}
$mysqli->set_charset('utf8mb4');

function generateRandomId(int $length = 20): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function generateUniqueOpenclassId(mysqli $mysqli): string {
    do {
        $id = generateRandomId(20);
        $stmt = $mysqli->prepare("SELECT 1 FROM openclasses WHERE openclass_id = ? LIMIT 1");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $id;
}

// UPDATED QUERY: Added up.iban
$sel = $mysqli->prepare('
    SELECT u.user_id, u.username, u.is_venue, up.pfp_path, up.iban
    FROM users u
    LEFT JOIN user_profile up ON up.public_id = u.public_id
    WHERE u.public_id = ?
    LIMIT 1
');
$sel->bind_param('s', $public_id);
$sel->execute();
$sel->bind_result($userId, $username, $is_venue, $pfp_path_db, $iban);
$sel->fetch();
$sel->close();

// Check if IBAN is missing
$ibanMissing = (strtolower($iban) === 'none' || empty($iban));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Security check: Don't allow POST if IBAN is missing

    $allowed_levels = ['beginner','intermediate','pro'];

    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $start = trim($_POST['start_time'] ?? '');
    $end = trim($_POST['end_time'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $price = intval($_POST['price'] ?? 0);
    $capacity = intval($_POST['capacity'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $level = $_POST['level'] ?? null;
    $led_by = trim($_POST['led_by'] ?? '');

    if ((int)$is_venue !== 1 || $led_by === '') {
        $led_by = 'NONE';
    }

    if (
        $title === '' ||
        $date === '' ||
        $start === '' ||
        $end === '' ||
        $address === '' ||
        $price < 0 ||
        $capacity < 1 ||
        !in_array($level, $allowed_levels, true)
    ) {
        http_response_code(400);
        exit('Invalid input');
    }

    if (!isset($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit('Image required');
    }

    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $mime = mime_content_type($_FILES['cover']['tmp_name']);
    if (!isset($allowed_types[$mime])) {
        http_response_code(400);
        exit('Invalid image type');
    }

    if ($_FILES['cover']['size'] > 25 * 1024 * 1024) {
        http_response_code(400);
        exit('Image too large');
    }

    $ext = $allowed_types[$mime];
    $filename = 'openclass_' . $public_id . '_' . time() . '.' . $ext;
    $upload_path = __DIR__ . '/../uploads/openclasses/' . $filename;

    if (!move_uploaded_file($_FILES['cover']['tmp_name'], $upload_path)) {
        http_response_code(500);
        exit('Upload failed');
    }

    $openclass_id = generateUniqueOpenclassId($mysqli);

    $stmt = $mysqli->prepare("
        INSERT INTO openclasses
        (openclass_id, public_id, title, date, start_time, end_time, address, price, capacity, description, level, cover_image, led_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'sssssssiissss',
        $openclass_id,
        $public_id,
        $title,
        $date,
        $start,
        $end,
        $address,
        $price,
        $capacity,
        $description,
        $level,
        $filename,
        $led_by,
    );

    if (!$stmt->execute()) {
        http_response_code(500);
        exit('Insert failed');
    }

    header('Location: ../openclass-listing.php?id=' . $openclass_id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create OpenClass</title>
    <link rel="stylesheet" href="/../source/styles/main.css">
    <link rel="stylesheet" href="openclass-post.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>

<nav class="top-nav">
    <span>Dancefy</span>
    <img src="../source/assets/back.png" class="nav-back" onclick="window.location='/post/post.php'">
</nav>

<main class="page">
        <form method="POST" enctype="multipart/form-data">
            <div class="hero">
                <h1><?= $T['visible']; ?></h1>
                <p><?= $T['open_register']; ?></p>
            </div>

            <div class="card">
                <h2><?= $T['basic_info']; ?></h2>
                <div class="basic-grid">
                    <div class="left">
                        <label><?= $T['title']; ?></label>
                        <input name="title" type="text" maxlength="20" required>
                        <div class="time-row">
                            <div>
                                <label><?= $T['date']; ?></label>
                                <input name="date" type="date" required>
                            </div>
                            <div>
                                <label><?= $T['start']; ?></label>
                                <input name="start_time" type="time" required>
                            </div>
                            <div>
                                <label><?= $T['end']; ?></label>
                                <input name="end_time" type="time" required>
                            </div>
                        </div>
                    </div>
                    <div class="right">
                        <span class="preview-label"><?= $T['cover']; ?></span>
                        <label style="opacity:1;" class="image-picker">
                            <div style="opacity:1;" class="preview-frame">
                                <img style="opacity:1;" id="coverPreview">
                            </div>
                            <input type="file" name="cover" required>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2><?= $T['logic']; ?></h2>
                <label><?= $T['adress']; ?></label>
                <input name="address" type="text" maxlength="45" required>
                <div class="two">
                    <div>
                        <label><?= $T['price']; ?></label>
                        <input name="price" type="number" min="0" max="9999" required>
                    </div>
                    <div>
                        <label><?= $T['capacity']; ?> (Max 20)</label>
                        <input name="capacity" type="number" min="1" max="20" required>
                    </div>
                    <?php if($is_venue == 1){ ?>
                        <div>
                            <label>Trainer</label>
                            <input name="led_by" maxlength="20" required>
                        </div>
                    <?php }?>
                </div>
            </div>

            <div class="card">
                <h2><?= $T['content']; ?></h2>
                <label><?= $T['description']; ?></label>
                <textarea name="description" rows="3" maxlength="450" required></textarea>
                <label><?= $T['level']; ?></label>
                <div class="chips single-select">
                    <button type="button" data-value="beginner"><?= $T['begginer']; ?></button>
                    <button type="button" data-value="intermediate"><?= $T['advanced']; ?></button>
                    <button type="button" data-value="pro"><?= $T['pro']; ?></button>
                </div>
                <input type="hidden" name="level" value="intermediate" required>
            </div>

            <button class="primary" type="submit"><?= $T['share']; ?></button>
            <p style="width: 100%; font-size: 0.7rem; text-align: center; opacity: 0.7; padding-top: 15px;"><?= $T['no_change']; ?></p>
        </form>
</main>

<script>
// Only run scripts if the form exists in the DOM
if (document.querySelector('form')) {
    document.querySelectorAll('.chips.single-select').forEach(group => {
        const hidden = group.nextElementSibling;
        group.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                group.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                hidden.value = btn.dataset.value;
            });
        });
    });

    const fileInput = document.querySelector('input[type="file"]');
    const preview = document.getElementById('coverPreview');

    if (fileInput) {
        fileInput.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = () => preview.src = reader.result;
            reader.readAsDataURL(file);
        });
    }

    const dateInput = document.querySelector('input[name="date"]')
    const startInput = document.querySelector('input[name="start_time"]')
    const endInput = document.querySelector('input[name="end_time"]')

    function validateDateTime() {
        const today = new Date()
        today.setHours(0,0,0,0)
        const selectedDate = new Date(dateInput.value)

        if (dateInput.value && selectedDate < today) {
            dateInput.setCustomValidity('Date cannot be in the past')
        } else {
            dateInput.setCustomValidity('')
        }

        if (dateInput.value && startInput.value && endInput.value) {
            const start = new Date(`${dateInput.value}T${startInput.value}`)
            const end = new Date(`${dateInput.value}T${endInput.value}`)
            if (start >= end) {
                endInput.setCustomValidity('End time must be later than start time')
            } else {
                endInput.setCustomValidity('')
            }
        }
    }

    dateInput.addEventListener('change', validateDateTime)
    startInput.addEventListener('change', validateDateTime)
    endInput.addEventListener('change', validateDateTime)
}
</script>

</body>
</html>