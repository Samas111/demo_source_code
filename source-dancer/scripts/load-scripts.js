(() => {
    if (window.ScriptLoader) return;

    const loaded = new Set();
    const loading = new Map();

    function resolveSrc(src) {
        return new URL(`source-dancer/scripts/${src}`, location.origin).href;
    }

    function load(src) {
        if (loaded.has(src)) return Promise.resolve();
        if (loading.has(src)) return loading.get(src);

        const p = new Promise((resolve, reject) => {
            const s = document.createElement("script");
            s.src = resolveSrc(src);
            s.async = false;

            s.onload = () => {
                loaded.add(src);
                loading.delete(src);
                resolve();
            };

            s.onerror = () => {
                loading.delete(src);
                reject(new Error("Failed to load " + src));
            };

            document.head.appendChild(s);
        });

        loading.set(src, p);
        return p;
    }

    async function loadMany(list) {
        for (const src of list) {
            await load(src);
        }
    }

    window.ScriptLoader = { load, loadMany };

    /* ===============================
       AUTO-LOAD FEED SCRIPTS
    =============================== */

    function isFeedPage() {
        return document.querySelector('.page[data-page="feed"]');
    }

    const feedBoot = setInterval(() => {
        if (!isFeedPage()) return;

        loadMany([
            "feed.js",
            "feed-sections.js",
            "follow.js",
            "likes.js"
        ]).then(() => {
            console.log("Feed scripts loaded");
        });

        clearInterval(feedBoot);
    }, 50);

    /* ===============================
       AUTO-LOAD MAP SCRIPTS
    =============================== */

    function isMapPage() {
        return document.querySelector('.page[data-page="map"]');
    }

    const mapBoot = setInterval(() => {
        if (!isMapPage()) return;

        loadMany([
            "map.js"
        ]).then(() => {
            console.log("Map script loaded via loader");

            if (typeof loadMap === "function") {
                loadMap();
            }
        });

        clearInterval(mapBoot);
    }, 50);

})();
