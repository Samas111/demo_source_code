
console.log("popup js loaded");

(function () {

    const STORAGE_KEY = "dancefy_blocked_users";

    function getBlockedUsers() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        } catch {
            return [];
        }
    }

    function setBlockedUsers(list) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    }

    function closeAllReports() {
        document
            .querySelectorAll('.report-popup.open')
            .forEach(p => p.classList.remove('open'));
    }

    function hideBlockedPosts(root = document) {
        const blocked = getBlockedUsers();
        if (!blocked.length) return;

        root.querySelectorAll('.feed-card[data-author-id]')
            .forEach(card => {
                if (blocked.includes(card.dataset.authorId)) {
                    card.remove();
                }
            });
    }

    document.addEventListener('click', function (e) {

        const reportBtn   = e.target.closest('.report-btn');
        const reportAction = e.target.closest('.report-action');
        const blockAction  = e.target.closest('.block-action');

        /* OPEN / CLOSE POPUP */
        if (reportBtn) {
            e.stopPropagation();

            const popup = reportBtn.nextElementSibling;
            if (!popup || !popup.classList.contains('report-popup')) return;

            const isOpen = popup.classList.contains('open');
            closeAllReports();

            if (!isOpen) popup.classList.add('open');
            return;
        }

        /* REPORT = UI ONLY */
        if (reportAction) {
            e.stopPropagation();

            reportAction.textContent = 'Nahlášeno';
            reportAction.disabled = true;

            setTimeout(() => {
                closeAllReports();
            }, 600);

            return;
        }

        /* BLOCK USER = REAL EFFECT */
        if (blockAction) {
            e.stopPropagation();

            const card = blockAction.closest('.feed-card');
            const authorId = card?.dataset.authorId;
            if (!authorId) return;

            const blocked = getBlockedUsers();
            if (!blocked.includes(authorId)) {
                blocked.push(authorId);
                setBlockedUsers(blocked);
            }

            closeAllReports();
            hideBlockedPosts();
            return;
        }

        closeAllReports();
    });

    hideBlockedPosts();

    const observer = new MutationObserver(mutations => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (node.nodeType === 1) {
                    hideBlockedPosts(node);
                }
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

})();