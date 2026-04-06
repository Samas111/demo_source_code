<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/web-logic.php';

$sql = "
SELECT up.pfp_path
FROM users u
JOIN user_profile up ON u.public_id = up.public_id
WHERE u.is_creator = 1
AND up.pfp_path IS NOT NULL
LIMIT 100
";

$result = $conn->query($sql);

$pfps = [];
while ($row = $result->fetch_assoc()) {
    $pfps[] = $row['pfp_path'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
<link rel="stylesheet" href="/registration/register-styles/register-flow.css">
<title>Register</title>
</head>
<body>

<div class="pfp-viewport">
  <div class="pfp-bg" id="pfp-container"></div>
  <div class="overlay"></div>
</div>

<div class="ui-container">
  <div class="progress-bar-wrap"><div id="progress-fill"></div></div>
  <header>
    <button type="button" class="back-btn" id="btn-back" onclick="changeStep(-1)">&#8249;</button>
    <div class="logo">Dancefy Registrace</div>
  </header>

  <form id="regForm" action="/registration/register-server-logic/process_registration.php" method="POST" enctype="multipart/form-data" style="height: 100%; display: flex; flex-direction: column;">
    
    <div class="form-wrap">
      
      <div class="step-content active" id="step-1">
        <div class="input-group">
          <label>Vaše Jméno</label>
          <input type="text" name="full_name" id="name-input" placeholder="Jméno" oninput="updateCard()" required>
          <span class="err-msg" id="err-1" style="color: #FF3D68; font-weight: 600;  font-size: 13px; margin-top: -15px; margin-bottom: 15px; display: none;">Zadejte prosím své jméno.</span>
        </div>
        <button type="button" class="btn-next" onclick="changeStep(1)">Pokračovat</button>
      </div>

      <div class="step-content" id="step-2">
        <div class="input-group">
          <label>Heslo</label>
          <input type="password" name="password" id="p1" oninput="val()" required>
          <label>Heslo znovu</label>
          <input type="password" name="password_confirm" id="p2" oninput="val()" required>
          <ul class="reqs">
            <li id="r1">Minimálně 8 znaků</li>
            <li id="r2">Velké písmeno</li>
            <li id="r3">Číslo / Symbol</li>
            <li id="r4">Hesla se shodují</li>
          </ul>
        </div>
        <button type="button" class="btn-next" onclick="changeStep(1)">Pokračovat</button>
      </div>

      <div class="step-content" id="step-3">
        <div class="input-group">
          <label>Uživatelské jméno</label>
          <input type="text" name="username" id="user-input" placeholder="uzivatel_123" oninput="onUsernameInput()" required>
          <span class="err-msg" id="err-3" style="color: #FF3D68; font-weight: 600; font-size: 13px; margin-top: -15px; margin-bottom: 15px; display: none;">Pouze malá písmena, čísla a podtržítka (3-20 znaků).</span>
          <span id="username-status" style="font-size: 13px; font-weight: 600; margin-top: -15px; margin-bottom: 15px; display: none;"></span>
          <input type="text" name="website" style="display:none !important" tabindex="-1" autocomplete="off">
        </div>
        <button type="button" class="btn-next" onclick="changeStep(1)">Pokračovat</button>
      </div>

      <div class="step-content" id="step-4">
        <div class="input-group">
          <div class="pfp-preview-container" onclick="document.getElementById('pfp-input').click()">
            <svg id="pfp-icon" viewBox="0 0 24 24"><path d="M4 4h3l2-2h6l2 2h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2m8 3a5 5 0 0 0-5 5 5 5 0 0 0 5 5 5 5 0 0 0 5-5 5 5 0 0 0-5-5m0 2a3 3 0 0 1 3 3 3 3 0 0 1-3 3 3 3 0 0 1-3-3 3 3 0 0 1 3-3Z"/></svg>
            <img id="pfp-preview-img" src="" alt="Preview">
          </div>
          <input type="file" name="pfp" id="pfp-input" hidden accept="image/*" onchange="handlePFP(this)">
        </div>
        <button type="button" class="btn-next" onclick="changeStep(1)">Pokračovat</button>
        <button type="button" class="btn-skip" onclick="changeStep(1)">Přeskočit &#10141;</button>
      </div>

      <div class="step-content" id="step-5">
        <div class="input-group">
          <div class="welcome-title">Vítejte na Dancefy</div>
          <p class="welcome-sub">Váš účet je ready!</p>
          <div class="glass-card">
            <img src="/uploads/profile-pictures/default.png" id="card-pfp-img" class="final-pfp" alt="Profile">
            <div class="final-name" id="card-name">Jméno</div>
            <div class="final-role">Tanečník</div>
          </div>
        </div>
        <button type="submit" class="btn-next">Vstoupit do Dancefy</button>
      </div>

    </div>
  </form>
</div>

<script>
  const pfps = <?php echo json_encode($pfps, JSON_UNESCAPED_SLASHES); ?> || [];
</script>
<script src="/registration/scripts/register.js"></script>
</body>
</html>