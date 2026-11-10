(function () {
    'use strict';
    function initializeNavigation() {
        const nav = document.querySelector('.kcl-nav');
        const toggle = nav?.querySelector('.kcl-menu-toggle');
        const links = nav?.querySelector('#kcl-primary-links');
        if (!toggle || !links || typeof window.matchMedia !== 'function') { return; }
        const mobile = window.matchMedia('(max-width: 767px)');

        function setExpanded(expanded) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            if (!expanded && links.contains(document.activeElement)) { toggle.focus(); }
            links.hidden = !expanded;
        }
        function resetLayout() {
            toggle.setAttribute('aria-expanded', 'false');
            // Move focus before a mobile reset hides its current link.
            if (mobile.matches && links.contains(document.activeElement)) { toggle.focus(); }
            links.hidden = mobile.matches;
            if (!mobile.matches && document.activeElement === toggle) {
                links.querySelector('a[href]')?.focus();
            }
        }

        toggle.addEventListener('click', function () {
            if (mobile.matches) { setExpanded(toggle.getAttribute('aria-expanded') !== 'true'); }
        });
        nav.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || !mobile.matches || links.hidden) { return; }
            setExpanded(false);
            toggle.focus();
            event.preventDefault();
            event.stopPropagation();
        });
        links.addEventListener('click', function (event) {
            if (mobile.matches && event.target.closest('a[href]')) { setExpanded(false); }
        });
        mobile.addEventListener('change', resetLayout);
        toggle.hidden = false;
        resetLayout();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeNavigation, {once:true});
    } else {
        initializeNavigation();
    }
})();
