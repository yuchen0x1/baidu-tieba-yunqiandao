(function () {
    function closest(element, selector) {
        while (element && element.nodeType === 1) {
            if (element.matches && element.matches(selector)) {
                return element;
            }
            element = element.parentElement;
        }
        return null;
    }

    function toggleSidebar(open) {
        var shell = document.getElementById('app-shell');
        if (!shell) {
            return;
        }
        if (open) {
            shell.classList.add('sidebar-open');
        } else {
            shell.classList.remove('sidebar-open');
        }
    }

    document.addEventListener('click', function (event) {
        var toggle = closest(event.target, '[data-sidebar-toggle]');
        var close = closest(event.target, '[data-sidebar-close]');
        if (toggle) {
            toggleSidebar(true);
        }
        if (close) {
            toggleSidebar(false);
        }
    });

    document.addEventListener('change', function (event) {
        var select = closest(event.target, '[data-locale-select]');
        if (!select) {
            return;
        }
        var value = select.value === 'zh-CN' || select.value === 'en-US' ? select.value : 'auto';
        try {
            document.cookie = 'tc_locale=' + encodeURIComponent(value) + '; path=/; max-age=31536000; samesite=lax';
        } catch (e) {
            // Private browsing may reject cookies; the current page can still continue.
        }
        window.location.reload();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            toggleSidebar(false);
        }
    });
}());
