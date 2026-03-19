(() => {

    /* -----------------------------
       STYLES (ONCE)
    ----------------------------- */
    if (!document.getElementById("feed-like-style")) {
        const feedStyle = document.createElement("style");
        feedStyle.id = "feed-like-style";
        feedStyle.textContent = `
        .dbl-heart {
            position:absolute;
            width:48px;
            pointer-events:none;
            z-index:9999;
            transform-origin:center;
        }
        .like-pulse {
            animation: likePulse .32s cubic-bezier(.34,1.56,.64,1);
        }
        .unlike-pulse {
            animation: unlikePulse .26s cubic-bezier(.22,1.4,.52,1);
        }
        @keyframes likePulse {
            0% { transform: scale(1); }
            40% { transform: scale(1.35); }
            100% { transform: scale(1); }
        }
        @keyframes unlikePulse {
            0% { transform: scale(1); }
            50% { transform: scale(.75); }
            100% { transform: scale(1); }
        }`;
        document.head.appendChild(feedStyle);
    }

    /* -----------------------------
       DOUBLE TAP HEART
    ----------------------------- */
    function spawnHeart(card, x, y, img) {
        const rect = card.getBoundingClientRect();
        if (getComputedStyle(card).position === "static") {
            card.style.position = "relative";
        }

        const h = document.createElement("img");
        h.src = img;
        h.className = "dbl-heart";
        h.style.left = (x - rect.left) + "px";
        h.style.top = (y - rect.top) + "px";

        const rot = (Math.random() * 24 - 12).toFixed(1);
        const anim = "h_" + Math.random().toString(36).slice(2);

        const s = document.createElement("style");
        s.textContent = `
        @keyframes ${anim} {
            0%   { transform: translate(-50%,-50%) scale(.2) rotate(${rot}deg); opacity:0; }
            25%  { transform: translate(-50%,-50%) scale(1.1) rotate(${rot}deg); opacity:.9; }
            60%  { transform: translate(-50%,-50%) scale(.9) rotate(${rot}deg); opacity:.55; }
            100% { transform: translate(-50%,-50%) scale(.65) rotate(${rot}deg); opacity:0; }
        }`;
        document.head.appendChild(s);

        h.style.animation = `${anim} .38s ease-out forwards`;
        card.appendChild(h);

        setTimeout(() => {
            h.remove();
            s.remove();
        }, 400);
    }

    /* -----------------------------
       INIT SINGLE CARD
    ----------------------------- */
    function initCard(card) {
        if (card.dataset.feedInit === "1") return;
        card.dataset.feedInit = "1";

        const postId = card.dataset.postId;
        let syncPending = false;

        const btn = card.querySelector(".user-actions img:first-child");
        const label = card.querySelector(".user-actions .count");
        if (!btn || !label) return;

        const empty = "source/assets/empty-like.png";
        const active = "source/assets/empty-like_active.png";
        const tapHeart = "source/assets/like-tap.png";

        btn.dataset.liked ??= btn.src.includes("_active") ? "1" : "0";

        btn.style.opacity = btn.dataset.liked === "1" ? "1" : "0.6";
        label.textContent = btn.dataset.liked === "1" ? "Odesláno" : "Upvote";

        let lastTap = 0;

        function toggle(force) {
            const liked = btn.dataset.liked === "1";
            if (force && liked) return;

            const newState = force ? true : !liked;
            btn.dataset.liked = newState ? "1" : "0";

            btn.src = newState ? active : empty;
            btn.style.opacity = newState ? "1" : "0.6";
            label.textContent = newState ? "Odesláno" : "Upvote";

            btn.classList.remove("like-pulse", "unlike-pulse");
            void btn.offsetWidth;
            btn.classList.add(newState ? "like-pulse" : "unlike-pulse");

            syncLike(newState);
        }

        async function syncLike(isLiked) {
            if (syncPending) return;
            syncPending = true;

            try {
                await fetch("like-post.php", {
                    method: "POST",
                    credentials: "include",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: new URLSearchParams({
                        post_id: postId,
                        action: isLiked ? "like" : "unlike"
                    })
                });
            } finally {
                syncPending = false;
            }
        }

        btn.addEventListener("click", () => toggle(false));

        card.addEventListener("dblclick", e => {
            spawnHeart(card, e.clientX, e.clientY, tapHeart);
            toggle(true);
        });

        card.addEventListener("touchend", e => {
            const now = Date.now();
            const diff = now - lastTap;
            lastTap = now;

            if (diff < 250 && diff > 40) {
                const t = e.changedTouches[0];
                spawnHeart(card, t.clientX, t.clientY, tapHeart);
                toggle(true);
            }
        });
    }

    /* -----------------------------
       INIT FEED + OBSERVER
    ----------------------------- */
    function initFeed(root = document) {
        root
            .querySelectorAll(".feed-card:not([data-feed-init])")
            .forEach(initCard);
    }

    initFeed();

    const observer = new MutationObserver(mutations => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (node instanceof HTMLElement) {
                    initFeed(node);
                }
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

})();
