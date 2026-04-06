(function () {
    var KEY = 'dancer_profile_nudge_shown';
    if (!sessionStorage.getItem(KEY)) {
        sessionStorage.setItem(KEY, '1');
        var el = document.getElementById('profile-nudge-overlay');
        el.style.display = 'flex';
        setTimeout(function () { el.classList.add('visible'); }, 800);
    }
})();
function dismissProfileNudge() {
    var el = document.getElementById('profile-nudge-overlay');
    el.classList.remove('visible');
    setTimeout(function () { el.style.display = 'none'; }, 320);
}
