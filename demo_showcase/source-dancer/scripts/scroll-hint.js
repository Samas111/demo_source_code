(function () {
    if (typeof window === "undefined") return;

    const STORAGE_KEY = "dancefy_swipe_hint_seen";
    const NUDGE_PX = 30;
    const MOVE_DURATION = 700;
    const PAUSE = 0;
    const CYCLES = 2;

    if (localStorage.getItem(STORAGE_KEY)) return;

    function ready(fn) {
        if (document.readyState !== "loading") fn();
        else document.addEventListener("DOMContentLoaded", fn);
    }

    ready(() => {
        if (typeof currentIndex !== "number") return;
        if (!Array.isArray(pages)) return;

        const overlay = document.createElement("div");
        overlay.className = "swipe-hint-overlay";

        const left = document.createElement("div");
        left.className = "swipe-edge left";

        const right = document.createElement("div");
        right.className = "swipe-edge right";

        overlay.appendChild(left);
        overlay.appendChild(right);
        document.body.appendChild(overlay);

        const track = document.querySelector(".pages-track");
        if (!track) return;

        const computed = window.getComputedStyle(track);
        const matrix = computed.transform;

        let baseTranslate = 0;
        if (matrix && matrix !== "none") {
            const match = matrix.match(/matrix.*\((.+)\)/);
            if (match) {
                baseTranslate = parseFloat(match[1].split(", ")[4]) || 0;
            }
        }

        track.style.transition = `transform ${MOVE_DURATION}ms ease`;

        const sequence = [];

        if (currentIndex > 0) {
            sequence.push({
                translate: baseTranslate + NUDGE_PX,
                edge: left
            });
        }

        if (currentIndex < pages.length - 1) {
            sequence.push({
                translate: baseTranslate - NUDGE_PX,
                edge: right
            });
        }

        let cycle = 0;

        function hideEdges() {
            left.style.display = "none";
            right.style.display = "none";
        }

        function showEdge(edge) {
            hideEdges();
            edge.style.display = "block";
        }

        hideEdges();

        function runSequence(i = 0) {
            if (i >= sequence.length) {
                track.style.transform = `translateX(${baseTranslate}px)`;
                hideEdges();

                cycle++;
                if (cycle < CYCLES) {
                    setTimeout(() => runSequence(0), MOVE_DURATION);
                } else {
                    finish();
                }
                return;
            }

            const step = sequence[i];
            showEdge(step.edge);

            track.style.transform = `translateX(${step.translate}px)`;

            setTimeout(() => {
                track.style.transform = `translateX(${baseTranslate}px)`;
                hideEdges();
                setTimeout(() => runSequence(i + 1), PAUSE);
            }, MOVE_DURATION);
        }

        function finish() {
            overlay.classList.add("fade-out");
            setTimeout(() => overlay.remove(), 300);
            localStorage.setItem(STORAGE_KEY, "1");
        }

        setTimeout(() => runSequence(0), 220);

        ["touchstart", "pointerdown", "mousedown"].forEach(evt => {
            window.addEventListener(evt, () => {
                track.style.transform = `translateX(${baseTranslate}px)`;
                finish();
            }, { once: true, passive: true });
        });
    });
})();
