<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DANCEFY</title>
<link rel="stylesheet" href="../register-styles/register.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" href="../source/assets/logo.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
</head>
<body>
<div id="message-box"></div>

<div class="wrapper">

   <div class="hero">
        <span>HNED VÁS</span>
        <span>VRÁTÍME</span>
        <span>ZPĚT</span>
        <p>RESET HESLA</p>
    </div>

    <form id="resetForm" action="../register-server-logic/reset.php" method="POST" novalidate>

        <label for="email">Email</label>
        <input type="email" name="email" id="email" required>

        <button type="submit" class="btn-primary"><p>Resetovat</p></button>

        <button type="button" class="btn-secondary" onclick="window.location.href='login.html'">
            Přihlásit se
        </button>

    </form>
</div>

<script>
const params = new URLSearchParams(window.location.search)
const message = params.get("message")
const box = document.getElementById("message-box")

if (message === "sent") {
    box.textContent = "Pokud účet existuje, dostal email s odkazem."
    box.className = "message success"
}

if (message === "invalid_email") {
    box.textContent = "Zadej platnou emailovou adresu."
    box.className = "message error"
}
</script>

</body>
</html>
