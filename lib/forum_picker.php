<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * 目标贴吧勾选框（插件设置页共用）
 * 列出当前用户在「贴吧与签到」同步的贴吧，勾选结果写入目标 textarea；
 * textarea 仍是表单保存的数据源，未同步的贴吧名可继续手动输入。
 */

if (!function_exists('forum_picker_forums')) {
    /** 当前用户已同步的贴吧名列表 */
    function forum_picker_forums()
    {
        global $m;
        $forums = array();
        $rs = $m->query("SELECT DISTINCT `tieba` FROM `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` WHERE `uid` = " . (int) UID . " ORDER BY `tieba` ASC");
        while ($row = $m->fetch_array($rs)) {
            $name = trim((string) $row['tieba']);
            if ($name !== '') {
                $forums[] = $name;
            }
        }
        return $forums;
    }
}

if (!function_exists('forum_picker_render')) {
    /** 输出一个勾选框，$targetName 为需要联动的 textarea 的 name 属性 */
    function forum_picker_render($targetName, array $forums)
    {
        if (empty($forums)) {
            echo '<div class="text-muted" style="font-size:12px;margin-bottom:6px">还没有同步贴吧列表，先到 <a href="index.php?mod=showtb">贴吧与签到</a> 页刷新贴吧列表，之后即可在这里勾选。</div>';
            return;
        }
        echo '<div class="kws-picker" data-target="' . htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') . '" style="margin-bottom:8px">';
        echo '<div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap">';
        echo '<input type="text" class="form-control kws-search" placeholder="搜索贴吧…" style="width:150px;height:28px;padding:2px 8px">';
        echo '<button type="button" class="cs-btn cs-btn-secondary cs-btn-sm kws-all">全选筛选结果</button>';
        echo '<button type="button" class="cs-btn cs-btn-ghost cs-btn-sm kws-none">清空</button>';
        echo '<span class="text-muted" style="font-size:12px">已选 <b class="kws-count">0</b> / 共 ' . count($forums) . ' 个（勾选自动写入下方文本框，也可手动补充）</span>';
        echo '</div>';
        echo '<div class="kws-chips" style="max-height:150px;overflow:auto;border:1px solid var(--color-line,#ddd);border-radius:6px;padding:8px;display:flex;flex-wrap:wrap;gap:6px">';
        foreach ($forums as $forumName) {
            echo '<label class="kws-chip" data-name="' . htmlspecialchars($forumName, ENT_QUOTES, 'UTF-8') . '" style="display:inline-flex;align-items:center;gap:4px;border:1px solid var(--color-line,#ddd);border-radius:999px;padding:2px 10px;cursor:pointer;font-size:12px;margin:0">'
                . '<input type="checkbox" class="kws-check" value="' . htmlspecialchars($forumName, ENT_QUOTES, 'UTF-8') . '" style="margin:0">'
                . '<span>' . htmlspecialchars($forumName, ENT_QUOTES, 'UTF-8') . '</span></label>';
        }
        echo '</div></div>';
    }
}

if (!function_exists('forum_picker_script')) {
    /** 输出联动脚本（每页只需调用一次，重复调用自动跳过） */
    function forum_picker_script()
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        echo '<script type="text/javascript">
/* 目标贴吧勾选框：chips 与 textarea 双向同步，textarea 是保存的数据源 */
(function () {
    document.querySelectorAll(\'.kws-picker\').forEach(function (picker) {
        var textarea = picker.parentElement.querySelector(\'textarea[name="\' + picker.getAttribute(\'data-target\') + \'"]\');
        if (!textarea) { return; }
        var chips = picker.querySelectorAll(\'.kws-chip\');
        var search = picker.querySelector(\'.kws-search\');
        var countEl = picker.querySelector(\'.kws-count\');

        var lines = function () {
            return textarea.value.split(/\\n/).map(function (s) { return s.trim(); }).filter(function (s) { return s !== \'\'; });
        };
        var write = function (arr) {
            var seen = {};
            var out = [];
            arr.forEach(function (s) {
                if (!seen[s]) { seen[s] = true; out.push(s); }
            });
            textarea.value = out.join(\'\\n\') + (out.length ? \'\\n\' : \'\');
            refresh();
        };
        function refresh() {
            var current = {};
            lines().forEach(function (s) { current[s] = true; });
            chips.forEach(function (chip) {
                chip.querySelector(\'.kws-check\').checked = !!current[chip.getAttribute(\'data-name\')];
            });
            if (countEl) { countEl.textContent = lines().length; }
        }
        chips.forEach(function (chip) {
            chip.querySelector(\'.kws-check\').addEventListener(\'change\', function () {
                var name = chip.getAttribute(\'data-name\');
                var arr = lines();
                if (this.checked) {
                    if (arr.indexOf(name) < 0) { arr.push(name); }
                } else {
                    arr = arr.filter(function (s) { return s !== name; });
                }
                write(arr);
            });
        });
        if (search) {
            search.addEventListener(\'input\', function () {
                var kw = this.value.trim().toLowerCase();
                chips.forEach(function (chip) {
                    chip.style.display = !kw || chip.getAttribute(\'data-name\').toLowerCase().indexOf(kw) >= 0 ? \'\' : \'none\';
                });
            });
        }
        var allBtn = picker.querySelector(\'.kws-all\');
        if (allBtn) {
            allBtn.addEventListener(\'click\', function () {
                var arr = lines();
                chips.forEach(function (chip) {
                    if (chip.style.display === \'none\') { return; }
                    var name = chip.getAttribute(\'data-name\');
                    if (arr.indexOf(name) < 0) { arr.push(name); }
                });
                write(arr);
            });
        }
        var noneBtn = picker.querySelector(\'.kws-none\');
        if (noneBtn) {
            noneBtn.addEventListener(\'click\', function () {
                textarea.value = \'\';
                refresh();
            });
        }
        textarea.addEventListener(\'input\', refresh);
        refresh();
    });
})();
</script>';
    }
}
