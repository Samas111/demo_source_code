<?php
$version = "1.0.3"; 
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Dancefy Promo</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <link rel="stylesheet" href="/style-variables/global.css?v=<?= APP_VERSION ?>">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
    <style>

        * {
            margin: 0; padding: 0; box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
            font-family: var(--font-family-main);
        }

        body {
            width: 100%; height: 100vh;
            background-color: var(--bg-deep);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Inter", sans-serif;
            overflow: hidden;
            display: flex; flex-direction: column;
            font-family: var(--font-family-main);
        }

        /* --- DYNAMICKÉ POZADÍ --- */
        .bg-canvas {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            background: radial-gradient(circle at 0% 0%, #1a0521 0%, transparent 50%),
                        radial-gradient(circle at 100% 100%, #2a0815 0%, transparent 50%);
        }

        .blob {
            position: absolute; width: 300px; height: 300px;
            background: var(--primary-pink);
            filter: blur(80px); opacity: 0.15; border-radius: 50%;
            animation: move 20s infinite alternate;
        }

        @keyframes move {
            from { transform: translate(-10%, -10%) scale(1); }
            to { transform: translate(20%, 30%) scale(1.2); }
        }

        .nav { height: 64px; display: flex; align-items: center; padding: 0 16px; position: relative; z-index: 10; }
        .nav-btn { width: 44px; height: 44px; background: var(--glass); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; border: 1px solid rgba(255,255,255,0.05); }

        .timer-track { width: 100%; height: 4px; background: rgba(255,255,255,0.05); }
        .timer-bar { height: 100%; width: 100%; background: var(--accent-gradient); transform-origin: left; animation: drain 60s linear infinite; }

        .content { flex: 1; padding: 30px 24px; display: flex; flex-direction: column; position: relative; z-index: 5; }

        .badge {
            background: rgba(255, 27, 115, 0.15);
            color: var(--primary-pink);
            padding: 6px 12px; border-radius: 100px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; width: fit-content; margin-bottom: 20px;
            border: 1px solid rgba(255, 27, 115, 0.2);
        }

        h1 { font-size: 14vw; font-weight: 900; line-height: 0.85; letter-spacing: -3px; }
        .glow-text { 
            background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            font-size: 22vw; display: block; margin: 5px 0;
            /* STÍN ODSTRANĚN */
        }

        .sub-headline { font-size: 18px; font-weight: 600; opacity: 0.9; margin-bottom: 40px; }

        .conditions { display: flex; flex-direction: column; gap: 12px; }
        .cond-card {
            background: var(--glass);
            padding: 16px; border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.05);
            display: flex; align-items: center; gap: 12px;
            backdrop-filter: blur(10px);
        }
        .cond-card svg { color: var(--primary-pink); width: 22px; height: 22px; flex-shrink: 0; }
        .cond-card span { font-size: 14px; font-weight: 500; color: rgba(255,255,255,0.8); }
        .cond-card b { color: #fff; font-weight: 700; }

        .timer-display { margin-top: 30px; }
        .timer-label { font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.4); letter-spacing: 2px; margin-bottom: 6px; display: block; }
        #clock { font-size: 24px; font-weight: 800; color: #fff; }

        .footer { margin-top: auto; padding-bottom: 120px; font-size: 11px; color: rgba(255,255,255,0.3); line-height: 1.5; }

        .cta-box {
            position: fixed; bottom: 0; left: 0; width: 100%; padding: 20px 24px calc(20px + env(safe-area-inset-bottom));
            background: var(--bg-deep); z-index: 20;
        }
        .btn-glow {
            height: 60px; background: var(--accent-gradient); border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            color: white; text-decoration: none; font-weight: 800; font-size: 17px;
            font-family: var(--font-family-main);
            transition: transform 0.2s;
        }
        .btn-glow:active { transform: scale(0.96); }

        @keyframes drain { from { transform: scaleX(1); } to { transform: scaleX(0); } }
    </style>
</head>
<body>

    <div class="bg-canvas">
        <div class="blob"></div>
        <div class="blob" style="right: -10%; top: 40%; animation-delay: -5s; background: #8b00ff;"></div>
    </div>

    <nav class="nav">
        <a href="app.php?tab=dashboard" class="nav-btn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </a>
    </nav>

    <div class="timer-track"><div class="timer-bar"></div></div>

    <main class="content">
        <div class="badge">Limitovaná akce</div>
        <h1>VYDĚLEJ<br><span class="glow-text">500 Kč</span></h1>
        <p class="sub-headline">Navíc za každou openclass.</p>

        <div class="conditions">
            <div class="cond-card">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                <span>Alespoň <b>5 registrací</b> na lekci</span>
            </div>
            <div class="cond-card" style="border: 1px solid rgba(255, 27, 115, 0.4); background: rgba(255, 27, 115, 0.1);">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                <span><b>Pouze Dancefy link</b> (žádné DM/maily)</span>
            </div>
            <div class="cond-card">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                <span>Měj vyplněný IBAN v profilu</span>
            </div>
        </div>

        <div class="timer-display">
            <span class="timer-label">KONČÍ ZA</span>
            <div id="clock">Načítání...</div>
        </div>

        <div class="footer">
            Vztahuje se na lekce publikované od 10.03. do 25.03. 2026.<br>
            Bonus bude vyplacen automaticky po splnění podmínek.
            Lze uplatnit pouze 2x pod jedním účtem
            Nesmí být použit žádný externí registrační systém (např. email, zprávy, google forms).
        </div>
    </main>

    <div class="cta-box">
        <a href="post/listing.php" class="btn-glow">Vytvořit lekci a získat bonus</a>
    </div>

<script>
    function updateClock() {
        const target = new Date(2026, 2, 25, 23, 59, 59).getTime(); 
        const now = new Date().getTime();
        const diff = target - now;
        const el = document.getElementById('clock');
        
        if (diff <= 0) {
            el.innerText = "Akce skončila";
            return;
        }

        const d = Math.floor(diff / (1000 * 60 * 60 * 24));
        const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

        let dayText = "dní";
        if (d === 1) dayText = "den";
        else if (d >= 2 && d <= 4) dayText = "dny";

        el.innerText = `${d} ${dayText} ${h} hod`;
    }
    
    setInterval(updateClock, 60000); 
    updateClock();
</script>
</body>
</html>