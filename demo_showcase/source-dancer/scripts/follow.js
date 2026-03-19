
document.addEventListener('click', async (e) => {
    if (!e.target.classList.contains('follow')) return;

    const btn = e.target;
    if (btn.textContent === 'Sleduji') return;

    const followed = btn.dataset.publicId;

    const res = await fetch('/api/follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'followed=' + encodeURIComponent(followed)
    });

    if (res.ok) {
        btn.textContent = 'Sleduji';
    }
});
