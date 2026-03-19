const content = document.getElementById("content");
const items = Array.from(document.querySelectorAll(".nav-item"));

content.innerHTML = "";
const track = document.createElement("div");
track.classList.add("pages-track");
content.appendChild(track);

const pages = items.map(item => {
    const page = document.createElement("div");
    page.classList.add("page");
    page.dataset.page = item.dataset.page;
    page.style.height = "100%";
    page.style.overflowY = "auto";
    track.appendChild(page);
    page._pageName = item.dataset.page;
    page.addEventListener("scroll", () => {
        pageScrollPositions[page._pageName] = page.scrollTop;
    }, { passive: true });
    return page;
});

let currentIndex = items.findIndex(i => i.dataset.page === "openclasses");
if (currentIndex === -1) currentIndex = 0;

let startX = 0;
let startY = 0;
let currentTranslate = 0;
let prevTranslate = 0;
let dragging = false;
let isScrollingVert = false;
let isDraggingHoriz = false;
let dragStartTranslate = 0;
const pageCache = {};
const pageScrollPositions = {};
let predictedIndex = null;
let dragSafetyTimeout = null;
let isInternalNavigation = false;

const HORIZ_DEAD = 45;
const DOMINANCE = 3;
const VERT_DEAD = 12;
const PREDICT_THRESHOLD = 60;

function setActiveTab(index) {
    items.forEach(i => {
        i.classList.remove("active");
        const img = i.querySelector("img");
        if (img) {
            let src = img.getAttribute("src");
            src = src.replace("_active", "");
            img.setAttribute("src", src);
        }
    });
    const item = items[index];
    if (!item) return;
    item.classList.add("active");
    const img = item.querySelector("img");
    if (img) {
        const src = item.querySelector("img").getAttribute("src");
        if (!src.includes("_active")) {
            img.setAttribute("src", src.replace(".png", "_active.png"));
        }
    }
}

function restorePageScroll(pageEl) {
    const name = pageEl.dataset.page;
    const pos = pageScrollPositions[name];
    if (typeof pos === "number") {
        pageEl.scrollTop = pos;
    }
}

function savePageScroll(pageEl) {
    if (!pageEl) return;
    const name = pageEl.dataset.page;
    pageScrollPositions[name] = pageEl.scrollTop;
}

function loadPageInto(pageEl, showLoading) {
    const name = pageEl.dataset.page;
    if (pageEl.dataset.loading === "true") return;

    if (pageCache[name]) {
        pageEl.appendChild(pageCache[name]);
        pageEl.dataset.loading = "false";
        restorePageScroll(pageEl);
        return;
    }

    pageEl.dataset.loading = "true";
    if (showLoading) {
        pageEl.innerHTML = '<div class="loading"></div>';
    }

    fetch(`source-dancer/content/${name}.php?ts=${Date.now()}`)
        .then(res => res.text())
        .then(html => {
            const wrapper = document.createElement("div");
            wrapper.classList.add("page-wrapper");
            wrapper.innerHTML = html;
            pageCache[name] = wrapper;
            pageEl.replaceChildren(wrapper); 
            pageEl.dataset.loading = "false";
            restorePageScroll(pageEl);
        })
        .catch(err => {
            pageEl.innerHTML = `<h2 style="color:var(--primary-color);padding:16px">${err.message}</h2>`;
            pageEl.dataset.loading = "false";
        });
}

function loadProfileIntoCurrentPage(publicId) {
    if (!publicId) return;

    const page = pages[currentIndex];
    if (!page) return;

    const feedRoot =
        page.querySelector(".feed-root") ||
        page.querySelector(":scope > div")?.querySelector(".feed-root");

    if (!feedRoot) {
        console.warn("feed-root not found");
        return;
    }


    savePageScroll(page);

    feedRoot.remove();

    const profileContainer = document.createElement("div");
    profileContainer.classList.add("profile-root");
    profileContainer.innerHTML = '<div style="padding:16px;opacity:.6">Načítám profil…</div>';

    page.appendChild(profileContainer);

    fetch(`source-dancer/content/view-profile.php?public_id=${encodeURIComponent(publicId)}`)
        .then(r => r.text())
        .then(html => {
            profileContainer.innerHTML = html;
            page.scrollTop = 0;
        })
        .catch(err => {
            profileContainer.innerHTML = `<div style="color:red;padding:16px">${err.message}</div>`;
        });
}



function cleanupNonCurrent() {
    pages.forEach((p, idx) => {
        if (idx !== currentIndex) {
            savePageScroll(p);
        }
    });
}

function getWidth() {
    return content.getBoundingClientRect().width || window.innerWidth;
}

function snapToIndex(index, initial = false) {
if (index < 0 || index >= pages.length) return;
    const prevPage = pages[currentIndex];
    if (prevPage) savePageScroll(prevPage);
    currentIndex = index;
    const width = getWidth();
    prevTranslate = -index * width;
    currentTranslate = prevTranslate;
    if (initial) track.style.transition = "none";
    else track.style.transition = "transform 0.25s ease-out";
    track.style.transform = `translateX(${currentTranslate}px)`;
    setActiveTab(index);
    const pageName = pages[index].dataset.page;
    if (!initial && !isInternalNavigation) { 
        history.pushState({ index: index }, "", `?tab=${pageName}`);
    }
    isInternalNavigation = false; 
    const page = pages[index];
    if (page) {
        loadPageInto(page, true);
    }
    if (initial) cleanupNonCurrent();
    else {
        const handler = () => {
            track.removeEventListener("transitionend", handler);
            cleanupNonCurrent();
        };
        track.addEventListener("transitionend", handler);
    }
}

function handleNavClick(index) {
    if (index === currentIndex) return;
    snapToIndex(index);
}

items.forEach((item, index) => {
    item.addEventListener("click", () => handleNavClick(index));
});

function getPosition(e) {
    if (e && e.type && typeof e.type === "string" && e.type.startsWith("mouse")) return { x: e.pageX, y: e.pageY };
    if (e && e.type && typeof e.type === "string" && e.type.startsWith("pointer")) return { x: e.clientX, y: e.clientY };
    const t = e.touches && e.touches[0];
    if (t) return { x: t.clientX, y: t.clientY };
    const changed = e.changedTouches && e.changedTouches[0];
    if (changed) return { x: changed.clientX, y: changed.clientY };
    return { x: 0, y: 0 };
}

function startDragSafetyTimer() {
    clearDragSafetyTimer();
    dragSafetyTimeout = setTimeout(() => {
        if (dragging && isDraggingHoriz) onTouchEnd();
        else {
            dragging = false;
            isDraggingHoriz = false;
            isScrollingVert = false;
        }
    }, 220);
}

function clearDragSafetyTimer() {
    if (dragSafetyTimeout) {
        clearTimeout(dragSafetyTimeout);
        dragSafetyTimeout = null;
    }
}

content.addEventListener("pointerup", e => {
    if (isDraggingHoriz || isScrollingVert) return;

    const target = e.target.closest(".profile-link");
    if (!target) return;

    const publicId = target.dataset.publicId;
    if (!publicId) return;

    e.preventDefault();

    loadProfileIntoCurrentPage(publicId);
});



function onTouchStart(e) {
    const pos = getPosition(e);
    startX = pos.x;
    startY = pos.y;
    dragging = true;
    isScrollingVert = false;
    isDraggingHoriz = false;
    predictedIndex = null;
    dragStartTranslate = prevTranslate;
    track.style.transition = "none";
    if (e.pointerId && e.target && e.target.setPointerCapture) {
        try { e.target.setPointerCapture(e.pointerId); } catch (_) {}
    }
    track.style.willChange = "transform";
}

function onTouchMove(e) {
    if (!dragging) return;
    const pos = getPosition(e);
    const dx = pos.x - startX;
    const dy = pos.y - startY;
    const absX = Math.abs(dx);
    const absY = Math.abs(dy);
    if (!isDraggingHoriz && !isScrollingVert) {
        if (absY > 6) {
            isScrollingVert = true;
            isDraggingHoriz = false;
            return;
        }
        if (absX > HORIZ_DEAD && absX > absY * DOMINANCE) {
            isDraggingHoriz = true;
            isScrollingVert = false;
            track.classList.add("force-horizontal");
            document.body.classList.add("body-scroll-locked");
            const curPage = pages[currentIndex];
            if (curPage) curPage.style.overflowY = "hidden";
            startDragSafetyTimer();
        }
    }
    if (isScrollingVert) return;
    if (!isDraggingHoriz) return;
    if (predictedIndex === null && absX > PREDICT_THRESHOLD) {
        let candidate = currentIndex;
        if (dx < 0 && currentIndex < pages.length - 1) candidate = currentIndex + 1;
        else if (dx > 0 && currentIndex > 0) candidate = currentIndex - 1;
        if (candidate !== currentIndex) {
            predictedIndex = candidate;
        if (predictedIndex === null && absX > PREDICT_THRESHOLD) {
            predictedIndex = candidate;
        }
        }
    }
    e.preventDefault();
    const width = getWidth();
    const maxTranslate = 0;
    const minTranslate = -width * (pages.length - 1);
    currentTranslate = dragStartTranslate + dx;
    if (currentTranslate > maxTranslate) currentTranslate = maxTranslate + (currentTranslate - maxTranslate) * 0.3;
    if (currentTranslate < minTranslate) currentTranslate = minTranslate + (currentTranslate - minTranslate) * 0.3;
    track.style.transform = `translateX(${currentTranslate}px)`;
    startDragSafetyTimer();
}

function finishDragAfterInterruption() {
    if (!dragging) return;
    dragging = false;
    const width = getWidth();
    const movedBy = currentTranslate - dragStartTranslate;
    const threshold = Math.min(80, width * 0.18);
    let newIndex = currentIndex;
    if (movedBy < -threshold && currentIndex < pages.length - 1) newIndex = currentIndex + 1;
    else if (movedBy > threshold && currentIndex > 0) newIndex = currentIndex - 1;
    track.classList.remove("force-horizontal");
    document.body.classList.remove("body-scroll-locked");
    const curPage = pages[currentIndex];
    if (curPage) curPage.style.overflowY = "auto";
    clearDragSafetyTimer();
    isDraggingHoriz = false;
    isScrollingVert = false;
    snapToIndex(newIndex);
}

function onTouchEnd(e) {
    if (!dragging) return;
    dragging = false;
    clearDragSafetyTimer();
    track.classList.remove("force-horizontal");
    document.body.classList.remove("body-scroll-locked");
    const curPage = pages[currentIndex];
    if (curPage) curPage.style.overflowY = "auto";
    if (!isDraggingHoriz) {
        isDraggingHoriz = false;
        isScrollingVert = false;
        return;
    }
    const width = getWidth();
    const movedBy = currentTranslate - dragStartTranslate;
    const threshold = Math.min(80, width * 0.18);
    let newIndex = currentIndex;
    if (movedBy < -threshold && currentIndex < pages.length - 1) newIndex = currentIndex + 1;
    else if (movedBy > threshold && currentIndex > 0) newIndex = currentIndex - 1;
    isDraggingHoriz = false;
    isScrollingVert = false;
    snapToIndex(newIndex);
}

track.addEventListener("touchstart", onTouchStart, { passive: false });
track.addEventListener("touchmove", onTouchMove, { passive: false });
track.addEventListener("touchend", onTouchEnd);
track.addEventListener("pointerdown", e => onTouchStart(e), { passive: false });
window.addEventListener("pointermove", e => onTouchMove(e), { passive: false });
window.addEventListener("pointerup", e => { if (dragging) onTouchEnd(e); });
track.addEventListener("pointercancel", finishDragAfterInterruption);
track.addEventListener("mousedown", e => {
    onTouchStart(e);
    const move = ev => onTouchMove(ev);
    const up = () => {
        onTouchEnd();
        window.removeEventListener("mousemove", move);
        window.removeEventListener("mouseup", up);
    };
    window.addEventListener("mousemove", move);
    window.addEventListener("mouseup", up);
});

window.addEventListener("resize", () => {
    prevTranslate = -currentIndex * getWidth();
    track.style.transition = "none";
    track.style.transform = `translateX(${prevTranslate}px)`;
});

const urlParams = new URLSearchParams(window.location.search);
const tabParam = urlParams.get("tab");
if (tabParam) {
    const idx = items.findIndex(i => i.dataset.page === tabParam);
    if (idx !== -1) currentIndex = idx;
}

setActiveTab(currentIndex);
loadPageInto(pages[currentIndex], false);
snapToIndex(currentIndex, true);

window.addEventListener("popstate", (event) => {
    if (event.state && event.state.index !== undefined) {
        isInternalNavigation = true; 
        snapToIndex(event.state.index, true); 
    }
});

