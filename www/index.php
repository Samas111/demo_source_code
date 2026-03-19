<?php

header("Location: registration/register-server-logic/auto.php")

?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dancefy — In Development</title>
  <link rel="icon" href="logo.png" />
  <style>
    :root{
      --bg:#0f1724;
      --card:#0b1220;
      --muted:#9aa6b2;
      --accent:#ff5a7c;
      --radius:14px;
      --shadow:0 6px 30px rgba(2,6,23,0.6);
      --max-width:480px;
    }

    *{box-sizing:border-box;margin:0;padding:0}

    body{
      font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto;
      background:
        radial-gradient(800px 400px at 8% 8%, rgba(255,90,124,0.06), transparent 60%),
        radial-gradient(900px 430px at 92% 88%, rgba(0,120,255,0.03), transparent 70%),
        var(--bg);
      color:#e4edf6;
      min-height:100vh;
      display:flex;
      justify-content:center;
      align-items:center;
      padding:24px;
    }

    .container{
      width:100%;
      max-width:var(--max-width);
      display:flex;
      justify-content:center;
      align-items:center;
    }

    .card{
      width:100%;
      background:rgba(255,255,255,0.03);
      border:1px solid rgba(255,255,255,0.04);
      border-radius:var(--radius);
      padding:32px;
      box-shadow:var(--shadow);
      display:flex;
      flex-direction:column;
      gap:20px;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:18px;
    }

    .logo{
      width:68px;
      height:68px;
      border-radius:12px;
      background:rgba(255,255,255,0.03);
      border:1px solid rgba(255,255,255,0.06);
      display:flex;
      justify-content:center;
      align-items:center;
      overflow:hidden;
    }

    .logo img{width:56px;height:56px;object-fit:contain}

    h1{
      font-size:1.9rem;
      font-weight:700;
      margin-bottom:4px;
    }

    .status{
      display:flex;
      align-items:center;
      gap:10px;
    }

    .badge{
      background:linear-gradient(90deg,var(--accent), #ff8aa8);
      padding:8px 12px;
      color:white;
      font-weight:600;
      border-radius:999px;
      font-size:0.9rem;
    }

    .dots{display:flex;gap:6px}
    .dot{
      width:8px;height:8px;border-radius:50%;
      background:rgba(255,255,255,0.14);
      animation:bounce 1.2s infinite;
    }
    .dot:nth-child(2){animation-delay:.12s}
    .dot:nth-child(3){animation-delay:.24s}
    @keyframes bounce{
      0%,100%{transform:translateY(0);opacity:.5}
      50%{transform:translateY(-5px);opacity:1}
    }

    p{color:var(--muted);line-height:1.55}

    .meta{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:4px;
    }

    .pill{
      padding:8px 10px;
      background:rgba(255,255,255,0.04);
      border-radius:10px;
      font-size:0.9rem;
      color:var(--muted);
      border:1px solid rgba(255,255,255,0.05);
    }

    .dev-box{
      background:rgba(255,255,255,0.02);
      border-radius:10px;
      border:1px dashed rgba(255,255,255,0.06);
      padding:14px;
      font-size:0.9rem;
      line-height:1.45;
    }

    @media(max-width:480px){
      body{
        padding:16px;
        align-items:flex-start;
      }

      .card{
        padding:24px;
        margin-top: 30%;
      }

      h1{
        font-size:1.6rem;
      }

      .logo{
        width:58px;
        height:58px;
      }

      .logo img{
        width:46px;
        height:46px;
      }
    }
  </style>
</head>
<body>
  <main class="container">
    <section class="card">
      <div class="brand">
        <div class="logo"><img src="logo.png" alt="Dancefy logo"></div>
        <div>
          <h1>Dancefy Solo</h1>
          <div class="status">
            <span class="badge">Ve Vývoji</span>
          </div>
        </div>
      </div>
      <p>
        Platforma Dancefy Solo je před spuštěním!<br>
      </p>
      <div class="meta">
        <span class="pill">Verze: <strong>v0.1.4</strong></span>
        <span class="pill">Status: <strong>Back-End</strong></span>
        <span class="pill">Aktualizováno: <strong>22.12.2025</strong></span>
      </div>

      <div class="dev-box">
        <strong>Notes:</strong><br>
        • Aproximate release date: March 2026<br>
      </div>
      <div class="dev-box">
        <strong>Version Updates:</strong><br>
        • Bug Fixing<br>
      </div>
      <p>Aplikace je dostupná pouze pro vývojáře a první tvůrce!</p>
      <a href="registration/register-server-logic/auto.php" style="opacity: 100%; color: white;">Předběžný přístup</a>
    </section>
  </main>
</body>
</html>
