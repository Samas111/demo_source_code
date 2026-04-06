<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Missing credentials';
    } else {

        $stmt = $mysqli->prepare("
            SELECT user_id, public_id, password
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($userId, $publicId, $passwordHash);
        $stmt->fetch();
        $stmt->close();

        if (!$userId || !password_verify($password, $passwordHash)) {
            $error = 'Špatné heslo nebo jméno.';
        } else {

            $mysqli->begin_transaction();

            try {
                $q = $mysqli->prepare("DELETE FROM user_tokens WHERE user_id = ?");
                $q->bind_param('i', $userId);
                $q->execute();

                $q = $mysqli->prepare("DELETE FROM posts WHERE public_id = ?");
                $q->bind_param('s', $publicId);
                $q->execute();

                $q = $mysqli->prepare("DELETE FROM openclasses WHERE public_id = ?");
                $q->bind_param('s', $publicId);
                $q->execute();

                $q = $mysqli->prepare("DELETE FROM user_profile WHERE user_id = ?");
                $q->bind_param('i', $userId);
                $q->execute();

                $q = $mysqli->prepare("DELETE FROM users WHERE user_id = ?");
                $q->bind_param('i', $userId);
                $q->execute();

                $mysqli->commit();
                $success = true;

            } catch (Exception $e) {
                $mysqli->rollback();
                $error = 'Smazání selhalo, zkuste to za chvíli.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delete Account – Dancefy</title>

<style>
:root {
    --bg: #0e0e0e;
    --card: #161616;
    --danger: #ff4d4d;
    --danger-soft: #2a1212;
    --border: #2a2a2a;
    --text-muted: #b3b3b3;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--bg);
    font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    color: #fff;
}

.wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.card {
    width: 100%;
    max-width: 420px;
    background: var(--card);
    border-radius: 18px;
    padding: 24px;
}

h1 {
    font-size: 22px;
    margin: 0 0 12px;
    color: var(--danger);
}

p {
    font-size: 14px;
    line-height: 1.5;
    color: var(--text-muted);
}

.warning {
    margin: 16px 0;
    padding: 14px;
    background: var(--danger-soft);
    border-radius: 12px;
    font-size: 14px;
}

.warning ul {
    margin: 8px 0 0;
    padding-left: 18px;
}

.warning li {
    margin-bottom: 6px;
}

input {
    width: 100%;
    padding: 14px;
    margin-top: 12px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: #0e0e0e;
    color: #fff;
    font-size: 15px;
}

input::placeholder {
    color: #777;
}

button {
    width: 100%;
    margin-top: 18px;
    padding: 16px;
    border-radius: 14px;
    border: none;
    background: var(--danger);
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}

button:active {
    opacity: 0.85;
}

.error {
    margin-top: 14px;
    font-size: 14px;
    color: #ff8080;
}

.success {
    text-align: center;
}

.success h1 {
    color: #7dff7d;
}
</style>
</head>

<body>
<div class="wrapper">
    <div class="card">

<?php if ($success): ?>

        <div class="success">
            <h1>Účet smazán</h1>
            <p>
                Váš Dancefy účet byl smazán a osobní údaje s ním spojené také. Tato akce už nelze vrátit.
            </p>
        </div>

<?php else: ?>

        <h1>Smazat účet</h1>

        <p>
            Tato akce permanentně smaže váš Dancefy účet
        </p>

        <div class="warning">
            <strong>Toto permanentně smaže:</strong>
            <ul>
                <li>Vaše osobní údaje pod profilem</li>
                <li>Vaše sledování a zájmy</li>
                <li>Soubory přiložené k profilu</li>
                <li>Preference a styly</li>
            </ul>
        </div>

        <form method="POST">
            <input
                name="username"
                placeholder="Confirm username"
                required
            >

            <input
                name="password"
                type="password"
                placeholder="Confirm password"
                required
            >

            <button>
                Nevratně smazat účet
            </button>
        </form>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

<?php endif; ?>

    </div>
</div>
</body>
</html>
