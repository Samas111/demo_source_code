<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DANCEFY</title>
<link rel="stylesheet" href="../register-styles/register.css?version=2">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" href="../source/assets/logo.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap">
<style>
    .password-requirements {
        margin-top: -10px;
        margin-bottom: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        list-style: none;
        padding: 0;
    }

    .requirement {
        color: #ff4f70; 
        transition: color 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }

    .requirement.valid {
        color: #00ff88;
    }

    .requirement::before {
        content: '✕';
        font-weight: bold;
    }

    .requirement.valid::before {
        content: '';
        margin-left: -5px;
    }

    input.invalid-input {
        border: 1px solid #ff4f70;
    }
</style>
</head>
<body>


<div class="wrapper">

    <div id="message-box"></div>

    <div class="hero">
        <span>VSTUP DO</span>
        <span>TANEČNÍHO</span>
        <span>SVĚTA</span>
        <p>NA DANCEFY</p>
    </div>

    <form id="registerForm" action="../register-server-logic/register.php" method="POST" novalidate>

        <label for="username">Uživatelské Jméno</label>
        <input type="text" name="username" id="username" required>

        <label for="password">Heslo</label>
        <input type="password" name="password" id="password" required>

        <label for="password_confirm">Potvrzení hesla</label>
        <input type="password" name="password_confirm" id="password_confirm" required>

        <ul class="password-requirements" id="passwordRequirements">
            <li id="lengthReq" class="requirement">Minimálně 8 znaků</li>
            <li id="upperReq" class="requirement">Velké písmeno</li>
            <li id="specialReq" class="requirement">Symbol (?, !, @, #)</li>
        </ul>

        <input type="text" name="website" style="display:none">

        <button type="submit" class="btn-primary" id="submitBtn">
            <p>Registrovat se</p>
        </button>

        <button type="button" class="btn-secondary" onclick="window.location.href='login.php'">
            Přihlásit
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

const passwordInput = document.getElementById('password');
const reqLength = document.getElementById('lengthReq');
const reqUpper = document.getElementById('upperReq');
const reqSpecial = document.getElementById('specialReq');

passwordInput.addEventListener('input', () => {
    const val = passwordInput.value;

    const isLongEnough = val.length >= 8;
    toggleValid(reqLength, isLongEnough);

    const hasUpper = /[A-Z]/.test(val);
    toggleValid(reqUpper, hasUpper);

    const hasSpecial = /[^a-zA-Z0-9]/.test(val);
    toggleValid(reqSpecial, hasSpecial);
    
    const isValid = isLongEnough && hasUpper && hasSpecial;
    document.getElementById('submitBtn').disabled = !isValid && !submitted;
});

function toggleValid(element, isValid) {
    if (isValid) {
        element.classList.add('valid');
    } else {
        element.classList.remove('valid');
    }
}
</script>

</body>
</html>
