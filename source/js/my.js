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

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            toggleSidebar(false);
        }
    });
}());
