<?php require_once __DIR__ . '/../../../private/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DANCEFY</title>
<link rel="stylesheet" href="../register-styles/register.css?v=<?= APP_VERSION ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" href="../source/assets/logo.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
</head>
<body>


<div class="wrapper">

    <div id="message-box"></div>
    <div class="logo-block">
        <div class="logo-glow"></div>
        <img src="../../assets/creator.png" class="logo" alt="logo">
        <h1 class="title">DANCEFY</h1>
        <p class="subtitle" style="opacity: 60%; font-weight: 700;">Pro Tanečníky</p>
    </div>

    <form id="registerForm" action="../register-server-logic/register.php" method="POST" >

        <label for="username" style="opacity: 100%;">Uživatelské Jméno</label>
        <input type="text" name="username" id="username" required style="opacity: 100%;">

        <label for="password" style="opacity: 100%;">Heslo</label>
        <input type="password" name="password" id="password" required style="opacity: 100%;">

        <label for="password_confirm" style="opacity: 100%;">Potvrzení hesla</label>
        <input type="password" name="password_confirm" id="password_confirm" required style="opacity: 100%;">

        <input type="text" name="website" style="display:none">

        <label class="eula-consent">
            <input type="checkbox" required>
            <span>
                Souhlasím s <a href="../onboard/terms-conditions.html" target="_blank" rel="noopener">Podmínky o užívání a Ochrana osobních údajů</a>
            </span>
        </label>

        <button type="submit" class="btn-primary" id="submitBtn" style="opacity: 100%;">
            Registrovat se
        </button>

        <button type="submit" class="btn-secondary" onclick="window.location.href='login.php'">
            Přihlásit se
        </button>
    </form>
</div>

<script>
const form = document.getElementById("registerForm");
const submitBtn = document.getElementById("submitBtn");
let submitted = false;

form.addEventListener("submit", (e) => {
    if (submitted) {
        e.preventDefault();
        return;
    }
    submitted = true;
    submitBtn.disabled = true;
    submitBtn.textContent = "Registruji...";
});
</script>

<script>
const box = document.getElementById("message-box");
const params = new URLSearchParams(window.location.search);
if (params.has("e")) {
    box.textContent = params.get("e");
    box.style.position = "fixed";
    box.style.top = "0";
    box.style.left = "0";
    box.style.width = "100%";
    box.style.padding = "16px";
    box.style.background = "#2a0000";
    box.style.color = "#ff4f70";
    box.style.fontSize = "15px";
    box.style.fontWeight = "600";
    box.style.textAlign = "center";
    box.style.zIndex = "9999";
}
</script>

</body>
</html>
