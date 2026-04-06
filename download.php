<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stáhněte si Dancefy!</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="download.css">
    <link rel="icon" href="logo.png">
    <meta property="og:title" content="Stáhni si Dancefy!" />
    <meta property="og:description" content="Aplikace pro tanečníky!" />
</head>
<body>
    <div class="loading-screen">
        <div class="spinner"></div>
        <p>Načítání...</p>
    </div>
    <div class="full-screen-container">
        <img src="../logo.png" alt="Dancefy Logo" class="logo fade-in">
        <h1 class="fade-in delay-1">Dancefy</h1>
        <div class="links fade-in delay-2">
            <a href="https://apps.apple.com/us/app/dancefy/id6738079685" class="link ios">
                <i class="fa fa-apple"></i>
                Stáhnout pro iOS
            </a>
            <a class="link android" href="https://play.google.com/store/apps/details?id=com.dancefy.online&hl=cs">
                <i class="fa fa-android"></i>
                Stáhnout pro Android
            </a>
            <!-- href="https://play.google.com/store/apps/details?id=com.zetect.andriod.app" -->
        </div>
    </div>
    <script>
        window.addEventListener("load", () => {
            document.querySelector(".loading-screen").style.display = "none";
            document.querySelector(".full-screen-container").style.display = "flex";
        });
    </script>
</body>
</html>
