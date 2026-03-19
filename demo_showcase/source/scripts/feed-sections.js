console.log("feed-sections.js loaded");

(function () {
    const CONFIG = {
        TOP_BUFFER: 60,
        SCROLL_BEHAVIOR: "smooth",
        LOCK_DURATION: 450
    };

    let isProgrammaticScroll = false;
    let bound = false;

    function getFeedPage() {
        return document.querySelector('.page[data-page="feed"]');
    }

    function getButtons() {
        return document.querySelectorAll('.switch-btn');
    }

    function setActive(target) {
        getButtons().forEach(b => b.classList.remove("switch-active"));
        const btn = document.querySelector(`.switch-btn[data-target="${target}"]`);
        if (btn) btn.classList.add("switch-active");
    }

    function scrollToTarget(target) {
        const page = getFeedPage();
        if (!page) return;

        const discover = page.querySelector("#discover-anchor");
        if (!discover) return;

        isProgrammaticScroll = true;

        if (target === "following") {
            page.scrollTo({ top: 0, behavior: CONFIG.SCROLL_BEHAVIOR });
            setActive("following");
        }

        if (target === "discover") {
            page.scrollTo({
                top: discover.offsetTop - CONFIG.TOP_BUFFER,
                behavior: CONFIG.SCROLL_BEHAVIOR
            });
            setActive("discover");
        }

        setTimeout(() => {
            isProgrammaticScroll = false;
        }, CONFIG.LOCK_DURATION);
    }

    function bind() {
        if (bound) return;

        const page = getFeedPage();
        const discover = page?.querySelector("#discover-anchor");
        const buttons = getButtons();

        if (!page || !discover || !buttons.length) return;

        buttons.forEach(btn => {
            btn.onclick = () => scrollToTarget(btn.dataset.target);
        });

        page.addEventListener("scroll", () => {
            if (isProgrammaticScroll) return;

            const center = page.scrollTop + page.clientHeight / 2;
            if (center >= discover.offsetTop) {
                setActive("discover");
            } else {
                setActive("following");
            }
        }, { passive: true });

        bound = true;
        console.log("FeedSections bound");
    }

    const retry = setInterval(() => {
        bind();
        if (bound) clearInterval(retry);
    }, 50);
})();
