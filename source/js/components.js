/*! Cloud Sign Studio · Components v4 — 八大原语交互
 *  原生 JS 实现，无 jQuery 依赖，支持键盘 / a11y
 *  全局命名空间：window.CS
 *  依赖：v4 tokens.css / icons.svg / components.css
 */
(function (window, document) {
  'use strict';

  var CS = window.CS || (window.CS = {});

  /* ---------- 工具方法 ---------- */
  function on(el, type, handler) {
    if (el) { el.addEventListener(type, handler); }
    return el;
  }
  function delegate(root, selector, type, handler) {
    on(root, type, function (e) {
      var node = e.target.closest(selector);
      if (node && root.contains(node)) { handler.call(node, e, node); }
    });
  }
  function createEl(html) {
    var div = document.createElement('div');
    div.innerHTML = html.trim();
    return div.firstElementChild;
  }
  function focusable(el) {
    return el && typeof el.focus === 'function';
  }

  /* ============================================================
     1. Tabs — 标签页
     用法：CS.tabs.init(container)
     结构：.cs-tabs > .cs-tablist [role=tablist] > [role=tab][aria-selected]
           + .cs-tabpanel[id=panel-x]
     ============================================================ */
  CS.tabs = {
    init: function (container) {
      var list = container.querySelector('[role="tablist"]') || container.querySelector('.cs-tablist');
      if (!list) { return; }
      var tabs = list.querySelectorAll('[role="tab"], .cs-tab');
      var self = this;

      Array.prototype.forEach.call(tabs, function (tab) {
        if (tab.dataset.csInit === '1') { return; }
        tab.dataset.csInit = '1';
        on(tab, 'click', function () { self.activate(tab, container); });
        on(tab, 'keydown', function (e) {
          if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); self.move(tab, container, 1); }
          if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); self.move(tab, container, -1); }
          if (e.key === 'Home') { e.preventDefault(); self.activate(tabs[0], container); }
          if (e.key === 'End') { e.preventDefault(); self.activate(tabs[tabs.length - 1], container); }
        });
      });
    },
    activate: function (tab, container) {
      var list = container.querySelector('[role="tablist"]') || container.querySelector('.cs-tablist');
      var tabs = list.querySelectorAll('[role="tab"], .cs-tab');
      var target = tab.getAttribute('aria-controls') || tab.getAttribute('data-target') || tab.getAttribute('href');
      if (target && target.charAt(0) === '#') { target = target.slice(1); }

      Array.prototype.forEach.call(tabs, function (t) {
        t.setAttribute('aria-selected', 'false');
        t.setAttribute('tabindex', '-1');
      });
      tab.setAttribute('aria-selected', 'true');
      tab.setAttribute('tabindex', '0');
      if (focusable(tab)) { tab.focus(); }

      var panels = container.querySelectorAll('.cs-tabpanel, [role="tabpanel"]');
      Array.prototype.forEach.call(panels, function (p) {
        var isMatch = !target || p.id === target || p.getAttribute('aria-labelledby') === tab.id;
        p.setAttribute('hidden', !isMatch);
        if (isMatch) { p.removeAttribute('hidden'); }
      });
    },
    move: function (tab, container, dir) {
      var list = container.querySelector('[role="tablist"]') || container.querySelector('.cs-tablist');
      var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"], .cs-tab'));
      var idx = tabs.indexOf(tab);
      var next = tabs[(idx + dir + tabs.length) % tabs.length];
      if (next) { this.activate(next, container); }
    }
  };

  /* ============================================================
     2. Toast — 通知
     用法：CS.toast('消息')
          CS.toast('标题', { type: 'success', duration: 4000, body: '详细...' })
     ============================================================ */
  CS.toast = function (title, opts) {
    opts = opts || {};
    if (typeof title === 'object') { opts = title; title = opts.title || ''; }
    var type = opts.type || 'info';
    var duration = opts.duration || 3600;
    var body = opts.body || '';

    var container = document.querySelector('.cs-toast-container');
    if (!container) {
      container = createEl('<div class="cs-toast-container" aria-live="polite" aria-atomic="false"></div>');
      document.body.appendChild(container);
    }

    var iconMap = { info: '#i-info', success: '#i-check', warning: '#i-alert', error: '#i-alert' };
    var iconHref = iconMap[type] || iconMap.info;

    var toast = createEl(
      '<div class="cs-toast cs-toast-' + type + '" role="status">' +
        '<svg class="cs-toast-icon" aria-hidden="true"><use href="' + iconHref + '"/></svg>' +
        '<div class="cs-toast-body">' +
          (title ? '<span class="cs-toast-title"></span>' : '') +
          (body ? '<div class="cs-toast-text"></div>' : '') +
        '</div>' +
        '<button type="button" class="cs-toast-close" aria-label="关闭"><svg class="cs-icon" style="width:14px;height:14px" aria-hidden="true"><use href="#i-x"/></svg></button>' +
      '</div>'
    );
    if (title) { toast.querySelector('.cs-toast-title').textContent = title; }
    if (body) { toast.querySelector('.cs-toast-text').textContent = body; }

    container.appendChild(toast);

    var timer = null;
    function dismiss() {
      if (timer) { clearTimeout(timer); timer = null; }
      if (toast.classList.contains('cs-leaving')) { return; }
      toast.classList.add('cs-leaving');
      setTimeout(function () { if (toast.parentNode) { toast.parentNode.removeChild(toast); } }, 180);
    }
    on(toast.querySelector('.cs-toast-close'), 'click', dismiss);
    if (duration > 0) { timer = setTimeout(dismiss, duration); }
    return { el: toast, dismiss: dismiss };
  };

  /* ============================================================
     3. Modal — 对话框
     用法：CS.modal.open({ title, body, size, actions: [{label, type, onClick}] })
          CS.modal.close(el)
          CS.modal.confirm({ title, body, onConfirm })
     ============================================================ */
  CS.modal = {
    open: function (opts) {
      opts = opts || {};
      var modal = createEl(
        '<div class="cs-modal" role="dialog" aria-modal="true" aria-labelledby="cs-modal-title-' + Math.random().toString(36).slice(2, 8) + '">' +
          '<div class="cs-modal-backdrop" data-cs-modal-close></div>' +
          '<div class="cs-modal-dialog" role="document">' +
            '<div class="cs-modal-header">' +
              '<h2 class="cs-modal-title"></h2>' +
              '<button type="button" class="cs-modal-close" aria-label="关闭"><svg class="cs-icon" style="width:18px;height:18px" aria-hidden="true"><use href="#i-x"/></svg></button>' +
            '</div>' +
            '<div class="cs-modal-body"></div>' +
            (opts.actions ? '<div class="cs-modal-footer"></div>' : '') +
          '</div>' +
        '</div>'
      );

      if (opts.size) { modal.querySelector('.cs-modal-dialog').setAttribute('data-size', opts.size); }
      modal.querySelector('.cs-modal-title').textContent = opts.title || '';
      var bodyEl = modal.querySelector('.cs-modal-body');
      if (typeof opts.body === 'string') { bodyEl.textContent = opts.body; }
      else if (opts.body instanceof Node) { bodyEl.appendChild(opts.body); }

      var footer = modal.querySelector('.cs-modal-footer');
      if (footer && opts.actions) {
        opts.actions.forEach(function (act) {
          var btn = createEl('<button type="button" class="cs-btn cs-btn-' + (act.type || 'secondary') + '"></button>');
          btn.textContent = act.label || '确定';
          on(btn, 'click', function () {
            if (act.onClick) { act.onClick(modal, closeFn); }
            else { closeFn(); }
          });
          footer.appendChild(btn);
        });
      }

      function closeFn() { CS.modal.close(modal); }
      modal.querySelectorAll('[data-cs-modal-close], .cs-modal-close').forEach(function (el) {
        on(el, 'click', closeFn);
      });

      // 键盘：ESC 关闭
      function keyHandler(e) {
        if (e.key === 'Escape' && modal.getAttribute('data-open') === 'true') {
          closeFn();
          document.removeEventListener('keydown', keyHandler);
        }
      }
      document.addEventListener('keydown', keyHandler);

      document.body.appendChild(modal);
      modal.setAttribute('data-open', 'true');
      // 焦点跳到关闭按钮，便于键盘用户
      var closeBtn = modal.querySelector('.cs-modal-close');
      if (focusable(closeBtn)) { closeBtn.focus(); }
      return modal;
    },
    close: function (modal) {
      if (!modal) { return; }
      modal.removeAttribute('data-open');
      setTimeout(function () { if (modal.parentNode) { modal.parentNode.removeChild(modal); } }, 240);
    },
    confirm: function (opts) {
      opts = opts || {};
      return CS.modal.open({
        title: opts.title || '确认操作',
        body: opts.body || '确定要继续吗？',
        actions: [
          { label: '取消', type: 'secondary' },
          { label: opts.confirmLabel || '确定', type: opts.confirmType || 'primary', onClick: function (m, close) { close(); if (opts.onConfirm) { opts.onConfirm(); } } }
        ]
      });
    }
  };

  /* ============================================================
     4. Drawer — 抽屉
     用法：CS.drawer.open({ position, title, body, onClose })
          CS.drawer.close(el)
     ============================================================ */
  CS.drawer = {
    open: function (opts) {
      opts = opts || {};
      var position = opts.position || 'left';
      var drawer = createEl(
        '<div class="cs-drawer" data-position="' + position + '" role="dialog" aria-modal="true">' +
          '<div class="cs-drawer-backdrop" data-cs-drawer-close></div>' +
          '<div class="cs-drawer-panel">' +
            '<div class="cs-drawer-header">' +
              '<h2 class="cs-drawer-title"></h2>' +
              '<button type="button" class="cs-modal-close" aria-label="关闭"><svg class="cs-icon" style="width:18px;height:18px" aria-hidden="true"><use href="#i-x"/></svg></button>' +
            '</div>' +
            '<div class="cs-drawer-body"></div>' +
            (opts.footer ? '<div class="cs-drawer-footer"></div>' : '') +
          '</div>' +
        '</div>'
      );
      drawer.querySelector('.cs-drawer-title').textContent = opts.title || '';
      var bodyEl = drawer.querySelector('.cs-drawer-body');
      if (typeof opts.body === 'string') { bodyEl.textContent = opts.body; }
      else if (opts.body instanceof Node) { bodyEl.appendChild(opts.body); }
      if (opts.footer && opts.footer instanceof Node) { drawer.querySelector('.cs-drawer-footer').appendChild(opts.footer); }

      function closeFn() { CS.drawer.close(drawer); if (opts.onClose) { opts.onClose(); } }
      drawer.querySelectorAll('[data-cs-drawer-close], .cs-modal-close').forEach(function (el) {
        on(el, 'click', closeFn);
      });
      function keyHandler(e) {
        if (e.key === 'Escape') { closeFn(); document.removeEventListener('keydown', keyHandler); }
      }
      document.addEventListener('keydown', keyHandler);

      document.body.appendChild(drawer);
      drawer.setAttribute('data-open', 'true');
      return drawer;
    },
    close: function (drawer) {
      if (!drawer) { return; }
      drawer.removeAttribute('data-open');
      setTimeout(function () { if (drawer.parentNode) { drawer.parentNode.removeChild(drawer); } }, 240);
    }
  };

  /* ============================================================
     5. Button loading — 按钮加载态
     用法：CS.btnLoading(btn, true/false)
     ============================================================ */
  CS.btnLoading = function (btn, loading) {
    if (!btn) { return; }
    if (loading === undefined) { loading = true; }
    btn.setAttribute('data-loading', loading ? 'true' : 'false');
    btn.disabled = loading;
  };

  /* ============================================================
     6. Skeleton 占位（快速生成）
     用法：CS.skeleton({ type: 'card' | 'text' | 'block', count })
     ============================================================ */
  CS.skeleton = function (opts) {
    opts = opts || {};
    var type = opts.type || 'text';
    var count = opts.count || 1;
    var html = '';
    for (var i = 0; i < count; i++) {
      if (type === 'card') { html += '<div class="cs-skeleton cs-skeleton-card"></div>'; }
      else if (type === 'block') { html += '<div class="cs-skeleton cs-skeleton-block"></div>'; }
      else if (type === 'title') { html += '<div class="cs-skeleton cs-skeleton-title"></div>'; }
      else { html += '<div class="cs-skeleton cs-skeleton-text"></div>'; }
    }
    return createEl('<div>' + html + '</div>');
  };

  /* ============================================================
     7. EmptyState — 空状态
     用法：CS.empty({ icon, title, desc, action: {label, href, onClick} })
     ============================================================ */
  CS.empty = function (opts) {
    opts = opts || {};
    var icon = opts.icon || '#i-info';
    var empty = createEl(
      '<div class="cs-empty">' +
        '<svg class="cs-empty-icon" aria-hidden="true"><use href="' + icon + '"/></svg>' +
        '<h3 class="cs-empty-title"></h3>' +
        '<p class="cs-empty-desc"></p>' +
        (opts.action ? '<div class="cs-empty-action"></div>' : '') +
      '</div>'
    );
    empty.querySelector('.cs-empty-title').textContent = opts.title || '暂无数据';
    empty.querySelector('.cs-empty-desc').textContent = opts.desc || '';
    if (opts.action) {
      var btn;
      if (opts.action.href) {
        btn = createEl('<a class="cs-btn cs-btn-primary" href="' + opts.action.href + '"></a>');
      } else {
        btn = createEl('<button type="button" class="cs-btn cs-btn-primary"></button>');
        if (opts.action.onClick) { on(btn, 'click', opts.action.onClick); }
      }
      btn.textContent = opts.action.label || '刷新';
      empty.querySelector('.cs-empty-action').appendChild(btn);
    }
    return empty;
  };

  /* ============================================================
     8. DataTable — 简单客户端分页（增强型）
     用法：CS.table.paginate(table, { pageSize })
     ============================================================ */
  CS.table = {
    paginate: function (table, opts) {
      opts = opts || {};
      var pageSize = opts.pageSize || 10;
      var tbody = table.querySelector('tbody');
      if (!tbody) { return; }
      var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
      var total = rows.length;
      var pages = Math.max(1, Math.ceil(total / pageSize));
      var current = 1;

      // 若表格已存在分页区，先清理
      var wrap = table.closest('.cs-table-wrap') || table.parentNode;
      var oldPager = wrap.querySelector('.cs-table-pagination');
      if (oldPager) { oldPager.parentNode.removeChild(oldPager); }

      function render() {
        var start = (current - 1) * pageSize;
        var end = start + pageSize;
        rows.forEach(function (row, idx) {
          row.style.display = (idx >= start && idx < end) ? '' : 'none';
        });
        var pager = wrap.querySelector('.cs-table-pagination');
        if (!pager) {
          pager = createEl(
            '<div class="cs-table-pagination">' +
              '<span class="cs-table-pagination-info"></span>' +
              '<div class="cs-table-pagination-controls"></div>' +
            '</div>'
          );
          wrap.appendChild(pager);
        }
        pager.querySelector('.cs-table-pagination-info').textContent =
          '共 ' + total + ' 条 · 第 ' + current + '/' + pages + ' 页';
        var ctrl = pager.querySelector('.cs-table-pagination-controls');
        ctrl.innerHTML = '';
        ctrl.appendChild(makeBtn('‹', current > 1, function () { current--; render(); }));
        for (var p = 1; p <= pages; p++) {
          (function (pn) {
            var b = makeBtn(String(pn), true, function () { current = pn; render(); });
            if (pn === current) { b.setAttribute('aria-current', 'page'); }
            ctrl.appendChild(b);
          })(p);
        }
        ctrl.appendChild(makeBtn('›', current < pages, function () { current++; render(); }));
      }

      function makeBtn(label, enabled, onClick) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = label;
        b.disabled = !enabled;
        on(b, 'click', onClick);
        return b;
      }

      render();
      return { refresh: render, setPage: function (n) { current = n; render(); } };
    }
  };

  /* ============================================================
     自动初始化（DOMContentLoaded）
     ============================================================ */
  function autoInit() {
    // 初始化所有 .cs-tabs
    document.querySelectorAll('.cs-tabs').forEach(function (el) { CS.tabs.init(el); });

    // 委托：data-cs-toggle="tab"
    delegate(document, '[data-cs-toggle="tab"]', 'click', function (e) {
      e.preventDefault();
      var container = this.closest('.cs-tabs');
      if (container) { CS.tabs.activate(this, container); }
    });

    // 委托：data-cs-toggle="modal" 触发 modal（target 为 modal id 或 url）
    delegate(document, '[data-cs-toggle="modal"]', 'click', function (e) {
      e.preventDefault();
      var target = this.getAttribute('data-target') || this.getAttribute('href');
      if (target && target.charAt(0) === '#') {
        var el = document.querySelector(target);
        if (el) { el.setAttribute('data-open', 'true'); }
      } else {
        CS.modal.open({ title: this.getAttribute('title') || '提示', body: this.getAttribute('data-content') || '' });
      }
    });

    // 委托：data-cs-dismiss="modal" 关闭 modal
    delegate(document, '[data-cs-dismiss="modal"]', 'click', function () {
      var modal = this.closest('.cs-modal');
      if (modal) { CS.modal.close(modal); }
    });

    // 委托：data-cs-dismiss="drawer"
    delegate(document, '[data-cs-dismiss="drawer"]', 'click', function () {
      var drawer = this.closest('.cs-drawer');
      if (drawer) { CS.drawer.close(drawer); }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }

  // ============================================================
  // CS.chart — ECharts 统一管理工具
  // ============================================================
  var chartRegistry = []; // [{ chart, el, option, opts }]
  var darkThemeRegistered = false;

  // 注册深色主题（首次加载 ECharts 后调用一次）
  function ensureDarkTheme() {
    if (darkThemeRegistered || typeof echarts === 'undefined') return;
    darkThemeRegistered = true;
    echarts.registerTheme('cs-dark', {
      backgroundColor: 'transparent',
      textStyle: { color: '#a8b0c0', fontFamily: 'Inter, "PingFang SC", "Microsoft YaHei", sans-serif' },
      title: { textStyle: { color: '#e8eaf0' }, subtextStyle: { color: '#7e8896' } },
      legend: { textStyle: { color: '#a8b0c0' } },
      tooltip: {
        backgroundColor: 'rgba(30,32,40,.95)', borderColor: 'rgba(91,58,255,.4)',
        textStyle: { color: '#e8eaf0' }
      },
      xAxis: { axisLine: { lineStyle: { color: 'rgba(255,255,255,.12)' } }, axisLabel: { color: '#7e8896' }, splitLine: { lineStyle: { color: 'rgba(255,255,255,.06)' } } },
      yAxis: { axisLine: { lineStyle: { color: 'rgba(255,255,255,.12)' } }, axisLabel: { color: '#7e8896' }, splitLine: { lineStyle: { color: 'rgba(255,255,255,.06)' } } },
      color: ['#5b3aff', '#10b981', '#f59e0b', '#c0554f', '#3b82f6', '#a855f7', '#ec4899'],
      grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true }
    });
    echarts.registerTheme('cs-light', {
      backgroundColor: 'transparent',
      textStyle: { color: '#475569', fontFamily: 'Inter, "PingFang SC", "Microsoft YaHei", sans-serif' },
      legend: { textStyle: { color: '#64748b' } },
      tooltip: {
        backgroundColor: 'rgba(255,255,255,.96)', borderColor: 'rgba(91,58,255,.3)',
        textStyle: { color: '#1e293b' }
      },
      xAxis: { axisLine: { lineStyle: { color: 'rgba(15,23,42,.12)' } }, axisLabel: { color: '#64748b' }, splitLine: { lineStyle: { color: 'rgba(15,23,42,.06)' } } },
      yAxis: { axisLine: { lineStyle: { color: 'rgba(15,23,42,.12)' } }, axisLabel: { color: '#64748b' }, splitLine: { lineStyle: { color: 'rgba(15,23,42,.06)' } } },
      color: ['#5b3aff', '#10b981', '#f59e0b', '#c0554f', '#3b82f6', '#a855f7', '#ec4899'],
      grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true }
    });
  }

  function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'cs-dark' : 'cs-light';
  }

  CS.chart = {
    /**
     * 创建 ECharts 实例并自动注册到注册表
     * @param {HTMLElement} el DOM 容器
     * @param {Object} option ECharts setOption 参数
     * @param {Object} opts { theme?: 'auto'|'cs-light'|'cs-dark', loading?: boolean }
     * @returns {ECharts|null}
     */
    create: function (el, option, opts) {
      if (typeof echarts === 'undefined') {
        console.warn('[CS.chart] ECharts 未加载，无法创建图表');
        return null;
      }
      ensureDarkTheme();
      opts = opts || {};
      var theme = opts.theme === 'auto' || !opts.theme ? currentTheme() : opts.theme;
      var chart = echarts.init(el, theme);
      chart.setOption(option);
      chartRegistry.push({ chart: chart, el: el, option: option, opts: opts });
      return chart;
    },
    getChart: function (index) {
      var entry = chartRegistry[index];
      return entry ? entry.chart : null;
    },
    count: function () { return chartRegistry.length; },
    find: function (el) {
      for (var i = 0; i < chartRegistry.length; i++) {
        if (chartRegistry[i].el === el) return chartRegistry[i];
      }
      return null;
    },

    /**
     * 获取当前主题名称
     */
    themeName: currentTheme,

    /**
     * 切换所有已注册图表的主题（深色 ↔ 浅色）
     * 重新初始化每个图表以应用新主题
     */
    refreshTheme: function () {
      if (typeof echarts === 'undefined') return;
      ensureDarkTheme();
      var newTheme = currentTheme();
      var newRegistry = [];
      chartRegistry.forEach(function (entry) {
        if (!entry || !entry.el) return;
        if (entry.chart && !entry.chart.isDisposed()) {
          entry.chart.dispose();
        }
        // 重新使用新主题初始化
        var fresh = echarts.init(entry.el, newTheme);
        fresh.setOption(entry.option || {});
        newRegistry.push({ chart: fresh, el: entry.el, option: entry.option, opts: entry.opts });
      });
      chartRegistry = newRegistry;
    },

    /**
     * 通知所有图表 resize（窗口变化、主题切换时调用）
     */
    resizeAll: function () {
      chartRegistry.forEach(function (entry) {
        var chart = entry && entry.chart;
        if (chart && !chart.isDisposed()) {
          chart.resize();
        }
      });
    },

    /**
     * 销毁所有图表（页面卸载时调用）
     */
    disposeAll: function () {
      chartRegistry.forEach(function (entry) {
        var chart = entry && entry.chart;
        if (chart && !chart.isDisposed()) chart.dispose();
      });
      chartRegistry = [];
    },

    /**
     * 从 URL 加载 JSON 数据并回调
     * @param {string} url
     * @param {Function} onSuccess(data)
     * @param {Function} onError(error)
     */
    fetch: function (url, onSuccess, onError) {
      fetch(url, { credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json && json.ok) onSuccess(json.data);
          else onError(json && json.error ? json.error : '接口返回异常');
        })
        .catch(function (err) { onError(err.message || '网络错误'); });
    },

    /**
     * 简单 sparkline 选项工厂
     */
    sparklineOption: function (days, values, color) {
      return {
        grid: { left: 0, right: 0, top: 4, bottom: 0 },
        xAxis: { type: 'category', show: false, data: days },
        yAxis: { type: 'value', show: false, minInterval: 1 },
        tooltip: { trigger: 'axis', formatter: function(p){ return p[0].name + '：' + p[0].value; } },
        series: [{
          type: 'line', smooth: true, showSymbol: false, data: values,
          lineStyle: { width: 2, color: color || '#5b3aff' },
          areaStyle: { opacity: .12 }
        }]
      };
    }
  };

  // 监听窗口 resize 统一通知所有图表
  var resizeTimer = null;
  window.addEventListener('resize', function () {
    if (resizeTimer) clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () { CS.chart.resizeAll(); }, 150);
  });

  // 监听主题切换事件（由 js.js applyTheme 派发）— 重新初始化所有图表以应用新主题
  window.addEventListener('cs-theme-change', function () {
    if (window.CS && CS.chart && CS.chart.count() > 0) {
      CS.chart.refreshTheme();
    }
  });

})(window, document);
