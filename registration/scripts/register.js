  const container = document.getElementById("pfp-container");
  const isMobile = window.innerWidth < 768;
  const COLUMNS = isMobile ? 12 : 24; 
  const ITEMS_PER_COL = isMobile ? 22 : 35; 
  const shuffled = pfps.length ? [...pfps].sort(() => Math.random() - 0.5) : [];

  for (let i = 0; i < COLUMNS; i++) {
    const col = document.createElement("div");
    col.className = "pfp-column " + (i % 2 === 0 ? "col-even" : "col-odd");
    col.style.animationDelay = `-${Math.random() * 120}s`;
    let h = "";
    for (let j = 0; j < ITEMS_PER_COL; j++) {
      h += `<div class="pfp" style="background-image: url('${shuffled[(i * ITEMS_PER_COL + j) % shuffled.length] || ''}')"></div>`;
    }
    col.innerHTML = h + h;
    container.appendChild(col);
  }

  let curr = 1;
  const total = 5;

  function validateCurrentStep() {
    if (curr === 1) {
      const name = document.getElementById('name-input').value.trim();
      const err = document.getElementById('err-1');
      err.style.display = name.length < 2 ? 'block' : 'none';
      return name.length >= 2;
    }

    if (curr === 2) {
      const p1 = document.getElementById('p1').value;
      const p2 = document.getElementById('p2').value;
      const isLong = p1.length >= 8;
      const hasCaps = /[A-Z]/.test(p1);
      const hasSpec = /[0-9!@#$%^&*]/.test(p1);
      const matches = p1 === p2 && p1 !== "";
      return isLong && hasCaps && hasSpec && matches;
    }

    if (curr === 3) {
      const user = document.getElementById('user-input').value;
      const err = document.getElementById('err-3');
      const status = document.getElementById('username-status');
      const valid = /^[a-z0-9_]{3,20}$/.test(user);
      err.style.display = valid ? 'none' : 'block';
      if (!valid) return false;
      if (status.dataset.state !== 'available') return false;
      return true;
    }

    return true; 
  }

    function changeStep(d) {
        // 1. If we are on Step 1 and user clicks back, redirect to login
        if (curr === 1 && d === -1) {
            window.location.href = 'login.php';
            return;
        }

        // 2. Validation check before moving forward
        if (d > 0 && !validateCurrentStep()) {
            return; 
        }

        let n = curr + d;
        if (n < 1 || n > total) return;
        
        // Hide old step, show new step
        document.getElementById(`step-${curr}`).classList.remove('active');
        curr = n;
        document.getElementById(`step-${curr}`).classList.add('active');

        // Update Progress Bar
        document.getElementById('progress-fill').style.width = (curr / total) * 100 + "%";

        // 3. UI Polish: Hide back button only on the very last welcome screen (Step 5)
        // because at that point the account is created and they should just "Enter".
        const backBtn = document.getElementById('btn-back');
        if (curr === 5) {
            backBtn.style.visibility = 'hidden';
        } else {
            backBtn.style.visibility = 'visible';
        }
    }

  function val() {
    let v1 = document.getElementById('p1').value;
    let v2 = document.getElementById('p2').value;
    
    document.getElementById('r1').className = v1.length >= 8 ? 'valid' : '';
    document.getElementById('r2').className = /[A-Z]/.test(v1) ? 'valid' : '';
    document.getElementById('r3').className = /[0-9!@#$%^&*]/.test(v1) ? 'valid' : '';
    document.getElementById('r4').className = (v1 === v2 && v1 !== "") ? 'valid' : '';
  }

  function handlePFP(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('pfp-icon').style.display = 'none';
        const prevImg = document.getElementById('pfp-preview-img');
        prevImg.src = e.target.result;
        prevImg.style.display = 'block';
        document.getElementById('card-pfp-img').src = e.target.result;
      }
      reader.readAsDataURL(input.files[0]);
    }
  }

  function updateCard() {
    const val = document.getElementById('name-input').value;
    document.getElementById('card-name').innerText = val || "Jan Novák";
  }

  let usernameTimer = null;

  function onUsernameInput() {
    const input = document.getElementById('user-input').value;
    const err = document.getElementById('err-3');
    const status = document.getElementById('username-status');

    clearTimeout(usernameTimer);

    const valid = /^[a-z0-9_]{3,20}$/.test(input);
    if (!valid) {
      err.style.display = input.length > 0 ? 'block' : 'none';
      status.style.display = 'none';
      status.dataset.state = '';
      return;
    }

    err.style.display = 'none';
    status.style.display = 'block';
    status.style.color = '#888';
    status.textContent = 'Kontroluji...';
    status.dataset.state = 'checking';

    usernameTimer = setTimeout(async () => {
      try {
        const res = await fetch(`/api/check_username.php?username=${encodeURIComponent(input)}`);
        const data = await res.json();
        if (document.getElementById('user-input').value !== input) return;
        if (data.available) {
          status.style.color = '#4CAF50';
          status.textContent = '\u2713 Uživatelské jméno je volné';
          status.dataset.state = 'available';
        } else {
          status.style.color = '#FF3D68';
          status.textContent = '\u2717 Toto uživatelské jméno je již obsazené';
          status.dataset.state = 'taken';
        }
      } catch {
        status.style.display = 'none';
        status.dataset.state = '';
      }
    }, 500);
  }