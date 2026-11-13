(function () {
    'use strict';
    var list = document.getElementById('cron-task-list');
    if (!list) { return; }
    var rows = Array.prototype.slice.call(list.querySelectorAll('[data-cron-task]'));
    var search = document.getElementById('cron-search');
    var filter = document.getElementById('cron-filter');
    function update() {
        var query = search.value.trim().toLowerCase();
        var state = filter.value;
        var visible = 0;
        rows.forEach(function (row) {
            var matches = state === 'all' ||
                (state === 'enabled' && row.dataset.enabled === '1') ||
                (state === 'disabled' && row.dataset.enabled === '0') ||
                (state === 'running' && row.dataset.running === '1') ||
                (state === 'error' && row.dataset.status === 'error') ||
                (state === 'due' && row.dataset.due === '1' && row.dataset.running === '0');
            row.hidden = !matches || row.querySelector('.cron-description').textContent.toLowerCase().indexOf(query) < 0;
            // 筛选时禁用拖拽，避免隐藏行参与排序；数字输入仍可用。
            row.draggable = !query && state === 'all';
            if (!row.hidden) { visible++; }
        });
        document.getElementById('cron-result-count').textContent = '显示 ' + visible + ' / ' + rows.length + ' 项任务';
        document.getElementById('cron-empty').hidden = visible > 0;
    }
    search.addEventListener('input', update);
    filter.addEventListener('change', update);
    update();
    var dragged = null;
    list.addEventListener('dragstart', function (event) {
        if (event.target.closest('input,button,a,summary,pre')) { event.preventDefault(); return; }
        dragged = event.target.closest('[data-cron-task]');
        if (!dragged || !dragged.draggable) { event.preventDefault(); dragged = null; return; }
        dragged.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', 'cron-task');
    });
    list.addEventListener('dragover', function (event) { if (dragged) { event.preventDefault(); } });
    list.addEventListener('drop', function (event) {
        if (!dragged) { return; }
        event.preventDefault();
        var target = event.target.closest('[data-cron-task]');
        if (target && target !== dragged) {
            var rect = target.getBoundingClientRect();
            list.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
            Array.prototype.forEach.call(list.querySelectorAll('.cron-order-input'), function (input, index) { input.value = index + 1; });
        }
    });
    list.addEventListener('dragend', function () { if (dragged) { dragged.classList.remove('is-dragging'); } dragged = null; });
    document.getElementById('cron-action-form').addEventListener('submit', function (event) {
        var button = event.submitter;
        if (button && button.dataset.confirm && !window.confirm(button.dataset.confirm)) { event.preventDefault(); return; }
        window.setTimeout(function () {
            document.querySelectorAll('[form="cron-action-form"]').forEach(function (item) { item.disabled = true; });
            if (button) { button.textContent = '处理中…'; button.setAttribute('aria-busy', 'true'); }
        }, 0);
    });
})();
