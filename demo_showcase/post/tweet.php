<?php
session_start();
require __DIR__ . '/../secure/logic.php';
require __DIR__ . '/../languages/loader.php';


$text = $_GET['text'] ?? '';

$public_id = $_SESSION['public_id'] ?? null;

if (
    !$public_id ||
    $public_id === '0' ||
    !is_string($public_id) ||
    !preg_match('/^[A-Za-z0-9]{8,20}$/', $public_id)
) {
    header("Location: " . APP_URL);
    exit;
}

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    exit('Database connection failed.');
}
$mysqli->set_charset('utf8mb4');

$sel = $mysqli->prepare('
    SELECT u.user_id, u.username, up.pfp_path
    FROM users u
    LEFT JOIN user_profile up ON up.public_id = u.public_id
    WHERE u.public_id = ?
    LIMIT 1
');
$sel->bind_param('s', $public_id);
$sel->execute();
$sel->bind_result($userId, $username, $pfp_path_db);

if (!$sel->fetch()) {
    http_response_code(401);
    exit('User not found.');
}
$sel->close();


$pfp_safe_filename = 'default.png';
if ($pfp_path_db) {
    $pfp_safe_filename = basename($pfp_path_db);
}
$pfp = '../uploads/profile-pictures/' . $pfp_safe_filename;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $content = trim($_POST['content'] ?? '');
    if ($content === '' || mb_strlen($content, 'UTF-8') > 5000) {
        http_response_code(400);
        exit('Invalid content.');
    }

    $filtered_content = $content;

    $blacklist_path = __DIR__ . '/blacklist.txt';

    if (!is_readable($blacklist_path)) {
        http_response_code(500);
        exit('BLACKLIST NOT READABLE');
    }

    $filtered_content = $content;
    $blacklist = file($blacklist_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($blacklist as $badword) {
        $badword = trim($badword);

        $badword = preg_replace('/^\xEF\xBB\xBF/', '', $badword);

        if ($badword === '') {
            continue;
        }

        $filtered_content = str_ireplace($badword, '*****', $filtered_content);
    }


    $content_clean = htmlspecialchars($filtered_content, ENT_QUOTES, 'UTF-8');
    $tag = substr(md5($content_clean . microtime(true)), 0, 12);

    $ins = $mysqli->prepare('
        INSERT INTO posts (public_id, content, tag)
        VALUES (?, ?, ?)
    ');
    $ins->bind_param('sss', $public_id, $content_clean, $tag);

    if (!$ins->execute()) {
        http_response_code(500);
        exit('Insert failed.');
    }

    header('Location: ../app.php?tab=feed');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create Post</title>
    <link rel="stylesheet" href="tweet.css?version=2">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>

<nav>
    <span>Dancefy</span>
    <img class="nav-back" src="../source/assets/back.png" alt="back" onclick="window.location='/app.php?tab=profile'">
</nav>

<form method="POST" id="postForm" novalidate>
    <div class="gradient-container">
        <div class="post-window">
            <div class="user-pfp">
                <img src="<?php echo htmlspecialchars($pfp, ENT_QUOTES, 'UTF-8'); ?>" alt="pfp">
            </div>
            <div class="contnet">
                <div class="header-row">
                    <span><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="char-limit-container">
                        <svg class="progress-ring" width="20" height="20" style="visibility: hidden;">
                            <circle class="progress-ring__circle-bg" stroke="rgba(255,255,255,0.1)" stroke-width="2" fill="transparent" r="8" cx="10" cy="10"/>
                            <circle id="progress-bar" class="progress-ring__circle" stroke="#FF1B73" stroke-width="2" fill="transparent" r="8" cx="10" cy="10"/>
                        </svg>
                    </div>
                </div>
                <textarea id="main-input" name="content" placeholder="Co je u tebe nového?" maxlength="500" required autofocus><?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>
    </div>

    <div class="quick-tab">
        <div class="flex" id="quick-options-container">
            <div class="quick-option" data-text="Nový openclass! Registrace běží - běžte si zajistit místo" onclick="handleQuickClick(this)">Nový Class</div>
            <div class="quick-option" data-text="Openclass je dneska! Poslední volná místa. Těším, se na vás" onclick="handleQuickClick(this)">Dnes se koná</div>
            <div class="quick-option" data-text="Díky všem za skvělou lekci!" onclick="handleQuickClick(this)">Díky</div>
        </div>
        <button class="post-btn" type="submit" id="submit-btn">Sdílet</button>
    </div>
</form>

<script>
    const textarea = document.getElementById('main-input');
    const progressBar = document.getElementById('progress-bar');
    const options = document.querySelectorAll('.quick-option');
    const submitBtn = document.getElementById('submit-btn');
    const circumference = 2 * Math.PI * 8;

    function adjustUI() {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';

        const limit = 500;
        const length = textarea.value.length;
        const percentage = Math.min(length / limit, 1);
        const offset = circumference - (percentage * circumference);
        progressBar.style.strokeDashoffset = offset;
        
        progressBar.style.stroke = length > (limit * 0.9) ? "#ffbc00" : "#FF1B73";

        options.forEach(opt => {
            if (textarea.value.trim() === opt.getAttribute('data-text')) {
                opt.classList.add('active');
            } else {
                opt.classList.remove('active');
            }
        });

        submitBtn.disabled = length === 0;
    }

    function handleQuickClick(element) {
        const text = element.getAttribute('data-text');
        
        if (textarea.value === text) {
            textarea.value = '';
        } else {
            textarea.value = text;
        }
        
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        adjustUI();
    }

    textarea.addEventListener('blur', () => {
        setTimeout(() => textarea.focus(), 10);
    });

    textarea.addEventListener('input', adjustUI);

    window.onload = () => {
        textarea.focus();
        adjustUI();
    };
</script>
</body>
</html>