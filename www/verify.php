<?php
require __DIR__ . '/secure/logic.php';

$token = $_GET['id'] ?? '';
$success = false;

if (!empty($token)) {
    $tokenHash = hash('sha256', $token);

    // Check token
    $stmt = $conn->prepare("
        SELECT public_id 
        FROM email_verification_tokens 
        WHERE token_hash = ? 
          AND used_at IS NULL 
          AND expires_at > NOW() 
        LIMIT 1
    ");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $pid = $res['public_id'];
        
        // Update User & Token
        $u1 = $conn->prepare("UPDATE users SET is_verified = 1 WHERE public_id = ?");
        $u1->bind_param("s", $pid);
        $u1->execute();

        $u2 = $conn->prepare("UPDATE email_verification_tokens SET used_at = NOW() WHERE token_hash = ?");
        $u2->bind_param("s", $tokenHash);
        $u2->execute();
        
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ověření Emailu | Dancefy</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: #0f0f13;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
}

.card {
    background: #16161d;
    padding: 50px 40px;
    border-radius: 20px;
    max-width: 420px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
}

.icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.success {
    color: #2ecc71;
}

.error {
    color: #ff4d4f;
}

h1 {
    margin: 0 0 15px;
    font-size: 24px;
    font-weight: 600;
}

p {
    color: #aaa;
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 25px;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    background: #ff2e63;
    color: white;
    transition: 0.2s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(255,46,99,0.3);
}
</style>
</head>
<body>

<div class="card">
    <?php if ($success): ?>
        <div class="icon success">✔</div>
        <h1>Email ověřen 🔥</h1>
        <p>
            Tvůj účet je teď plně aktivní.<br>
            Můžeš zavřít toto okno a pokračovat na Dancefy.
        </p>
    <?php else: ?>
        <div class="icon error">✖</div>
        <h1>Ověření se nepodařilo</h1>
        <p>
            Odkaz je neplatný nebo vypršel.<br>
            Zkus požádat o nový ověřovací email.
        </p>
    <?php endif; ?>
</div>

</body>
</html>