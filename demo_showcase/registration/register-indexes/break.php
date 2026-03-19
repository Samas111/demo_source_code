<?php
require $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';

$stmt = $conn->prepare("SELECT status, message, estimated_end, updated_at FROM system_status WHERE id = 1 LIMIT 1");
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if ((int)$data['status'] !== 1) {
    header("Location: /");
    exit;
}

http_response_code(503);

$estimatedEnd = $data['estimated_end'];
$updatedAt = $data['updated_at'];
$reason = !empty($data['message']) ? htmlspecialchars($data['message']) : null;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Dancefy – Pauza</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --background-color: #0D0314; 
            --gradient-color: linear-gradient(to right, #FF5143, #FF1B73); 
        }

        * { box-sizing: border-box; }

        html, body { 
            margin: 0; 
            height: 100%; 
            background: var(--background-color); 
            font-family: 'Inter', sans-serif; 
            color: white; 
            overflow: hidden; 
        }

        .screen { 
            height: 100%; 
            width: 100%; 
            padding: 60px 40px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            position: relative; 
        }

        .glow { 
            position: absolute; 
            width: 800px; 
            height: 800px; 
            background: radial-gradient(circle, rgba(255,27,115,0.18) 0%, transparent 70%); 
            top: -200px; 
            right: -200px; 
            filter: blur(120px); 
            z-index: 0; 
        }

        .content { z-index: 1; max-width: 600px; }

        .logo { 
            font-weight: 800; 
            font-size: 22px; 
            letter-spacing: 2px; 
            margin-bottom: 60px; 
        }

        .title { 
            font-size: 13px; 
            font-weight: 700; 
            margin-bottom: 12px; 
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #FF1B73;
        }

        .main-text {
            font-size: 38px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
            letter-spacing: -1px;
        }

        .subtitle { 
            font-size: 16px; 
            opacity: 0.6; 
            line-height: 1.6; 
            margin-bottom: 40px; 
        }

        .reason-text {
            border-left: 2px solid #FF1B73;
            padding-left: 15px;
            margin: 20px 0 40px 0;
            font-style: italic;
            opacity: 0.9;
        }

        .progress-wrapper { 
            width: 100%; 
            height: 4px; 
            background: rgba(255,255,255,0.1); 
            border-radius: 2px; 
            overflow: hidden; 
        }

        .progress-bar { 
            height: 100%; 
            width: 0%; 
            background: var(--gradient-color); 
            transition: width 0.8s cubic-bezier(0.22, 1, 0.36, 1); 
        }

        .countdown { 
            margin-top: 16px; 
            font-size: 14px; 
            font-weight: 600; 
            font-variant-numeric: tabular-nums;
        }

        .footer { 
            font-size: 11px; 
            opacity: 0.25; 
            z-index: 1; 
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

<div class="screen">
    <div class="glow"></div>

    <div class="content">
        <div class="logo">DANCEFY APP</div>

        <div class="title">Status: Údržba</div>
        <div class="main-text">Právě vylepšujeme aplikaci.</div>

        <div class="subtitle">
            Dočasně jsme pozastavili provoz, abychom nasadili novou verzi a zvýšili stabilitu.
        </div>

        <?php if ($reason): ?>
        <div class="reason-text">
            "<?= $reason ?>"
        </div>
        <?php endif; ?>

        <div class="progress-wrapper">
            <div class="progress-bar" id="progressBar"></div>
        </div>

        <div class="countdown" id="countdown">Synchronizace...</div>
    </div>

    <div class="footer" id="serverTime">Připojeno k systému</div>
</div>

<script>
let updatedAt = <?= $updatedAt ? '"' . date('c', strtotime($updatedAt)) . '"' : 'null' ?>;
let estimatedEnd = <?= $estimatedEnd ? '"' . date('c', strtotime($estimatedEnd)) . '"' : 'null' ?>;
let serverOffset = 0; 

const bar = document.getElementById("progressBar");
const countdownEl = document.getElementById("countdown");
const serverTimeDisplay = document.getElementById("serverTime");

function updateProgress() {
    if (!updatedAt || !estimatedEnd) return;

    const start = new Date(updatedAt).getTime();
    const end = new Date(estimatedEnd).getTime();
    const now = Date.now() + serverOffset;

    const total = end - start;
    const passed = now - start;

    let percent = (passed / total) * 100;
    percent = Math.min(Math.max(percent, 0), 100); 

    bar.style.width = percent + "%";

    const remaining = end - now;
    if (remaining <= 0) {
        countdownEl.innerText = "DOKONČUJEME POSLEDNÍ KROKY...";
        return;
    }

    const mins = Math.floor(remaining / 60000);
    const secs = Math.floor((remaining % 60000) / 1000);
    countdownEl.innerText = `ODHADOVANÝ ČAS: ${mins}m ${secs}s`;
}

function checkStatus() {
    fetch('status.php')
    .then(r => r.json())
    .then(data => {
        if (data.status === 0) {
            window.location.href = "/";
            return;
        }
        const sTime = new Date(data.server_time).getTime();
        serverOffset = sTime - Date.now();
        updatedAt = data.updated_at;
        estimatedEnd = data.estimated_end;
        if(serverTimeDisplay) serverTimeDisplay.innerText = "AKTUALIZOVÁNO";
    })
    .catch(() => {
        if(serverTimeDisplay) serverTimeDisplay.innerText = "PŘIPOJOVÁNÍ...";
    });
}

setInterval(updateProgress, 1000);
setInterval(checkStatus, 10000);

checkStatus(); 
updateProgress();
</script>

</body>
</html>