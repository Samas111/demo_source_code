<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dancefy</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="onboard.css">

<style>
.tutorial-image {
  width: 100%;
  display: block;
  opacity: 0;
  transition: opacity 300ms ease;
}
.tutorial-image.visible {
  opacity: 1;
}
</style>
</head>

<body>

<div class="onboarding tutorial">
  <div class="onboarding-tutorial">
    <img
      id="tutorialImage"
      class="tutorial-image"
      src="tutorial/1.png"
      alt="Tutorial"
    >
  </div>

  <div class="onboarding-bottom tutorial">
    <button
      class="onboarding-primary"
      id="nextBtn"
    >
      Pokračovat
    </button>
  </div>
</div>

<script>
const steps = [
  { img: "tutorial/1.png", cta: "Pokračovat" },
  { img: "tutorial/2.png", cta: "Rozumím" },
  { img: "tutorial/3.png", cta: "Chci růst" },
  { img: "tutorial/4.png", cta: "Vstoupit do Dancefy" }
];

let current = 0;
let locked = false;

const img = document.getElementById("tutorialImage");
const btn = document.getElementById("nextBtn");

function preload(src) {
  const i = new Image();
  i.src = src;
}

steps.forEach(s => preload(s.img));

window.onload = () => {
  img.classList.add("visible");
};

btn.onclick = () => {
  if (locked) return;
  locked = true;

  img.classList.remove("visible");

  setTimeout(() => {
    current++;

    if (current >= steps.length) {
      window.location.href = "download.php";
      return;
    }

    img.src = steps[current].img;
    btn.textContent = steps[current].cta;

    requestAnimationFrame(() => {
      img.classList.add("visible");
      locked = false;
    });
  }, 300);
};
</script>

</body>
</html>
