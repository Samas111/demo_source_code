<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DANCEFY</title>
<link rel="stylesheet" href="../register-styles/register.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" href="../source/assets/logo.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
</head>
<body>

<div class="wrapper">

    <div id="message-box"></div>

    <div class="hero">
        <span>OPENCLASSES</span>
        <span>UDÁLOSTI</span>
        <span>TVŮRCI</span>
        <p>Z ČESKÉ TANEČNÍ SCÉNY</p>
    </div>

    <form id="loginForm" action="../register-server-logic/login.php" method="POST" novalidate>

        <label for="username">Jméno</label>
        <input type="text" name="username" id="username" required>

        <label for="password">Heslo</label>
        <input type="password" name="password" id="password" required>

        <input type="text" name="website" style="display:none">

        <button type="submit" class="btn-primary"><p>Přihlásit se</p></button>

        <button type="button" onclick="window.location.href='register.php'" class="btn-secondary">
            Registrovat
        </button>

        <p class="reset">Zapomněli jste heslo? <a href="reset.php">Resetovat</a></p>

    </form>
</div>

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
