<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Přístup omezen · Dancefy</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<style>
:root {
  --background-color: #0D0314;
  --gradient-color: linear-gradient(to right, #FF5143, #FF1B73);
  --font-family-main: 'Inter', sans-serif;
  --text-color: #ffffff;
  --card-color: #180028;
  --primary-color: #EB4F8B;

  --nav-border: rgba(255, 255, 255, 0.06);
  --neon-pink: #ff3b7a;
}

* {
  box-sizing: border-box;
}

html, body {
  margin: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: var(--background-color);
  color: var(--text-color);
  font-family: var(--font-family-main);
}

.screen {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.box {
  width: 100%;
  max-width: 340px;
  background: var(--card-color);
  border-radius: 18px;
  padding: 24px 20px 22px;
  text-align: center;

  box-shadow:
    0 0 0 1px var(--nav-border),
    0 14px 32px rgba(0,0,0,0.45);

  margin-top: 40%;
}

.icon {
  width: 48px;
  height: 48px;
  border-radius: 15px;
  margin: 0 auto 14px;
  background: var(--gradient-color);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;

}

h1 {
  font-size: 17px;
  font-weight: 600;
  margin: 0 0 6px;
}

p {
  font-size: 13.5px;
  line-height: 1.45;
  opacity: 0.72;
  margin: 0 0 18px;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.btn {
  width: 100%;
  padding: 12px 14px;
  border-radius: 13px;
  font-size: 14.5px;
  font-weight: 600;
  text-decoration: none;
  text-align: center;
}

.btn.primary {
  background: var(--gradient-color);
  color: #fff;
}

.btn.secondary {
  background: transparent;
  color: var(--neon-pink);
  border: 1px solid var(--nav-border);
  opacity: 60%;
}
</style>
</head>

<body>
<div class="screen">
  <div class="box">
    <div class="icon">✦</div>

    <h1>Přístup pouze pro Tvůrce</h1>

    <p>
      Tato část aplikace je dostupná jen pro Tvůrce.
      Tvůj účet je aktuálně nastavený jako tanečník.
    </p>

    <div class="actions">
      <a href="/app-dancer.php" class="btn primary">
        Pokračovat jako tanečník
      </a>

      <a href="/creator-program.php" class="btn secondary">
        Požádat o účet tvůrce
      </a>
    </div>
  </div>
</div>
</body>
</html>
