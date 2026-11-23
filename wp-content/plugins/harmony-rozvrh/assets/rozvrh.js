/* Harmony – rozvrh: přepínání týdnů a dnů, filtr skupin, proběhlé lekce. Bez závislostí. */
(function () {
  'use strict';

  function ymd(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function select(buttons, attr, value) {
    buttons.forEach(function (b) { b.setAttribute('aria-selected', b.dataset[attr] === String(value) ? 'true' : 'false'); });
  }

  function init(root) {
    var weekBtns = Array.prototype.slice.call(root.querySelectorAll('.hr-weeks button'));
    var weeks = Array.prototype.slice.call(root.querySelectorAll('.hr-week'));
    var filterBtns = Array.prototype.slice.call(root.querySelectorAll('.hr-filters button'));
    // data-now (ISO) = simulovaný „teď“ pro náhledy a testy; jinak skutečný čas.
    var nowTs = root.dataset.now ? Date.parse(root.dataset.now) : Date.now();
    var today = ymd(new Date(nowTs));
    var group = null;

    function showWeek(i) {
      select(weekBtns, 'week', i);
      weeks.forEach(function (w) { w.classList.toggle('is-active', w.dataset.week === String(i)); });
    }

    function showDay(week, day) {
      select(Array.prototype.slice.call(week.querySelectorAll('.hr-days button')), 'day', day);
      week.querySelectorAll('.hr-day').forEach(function (d) { d.classList.toggle('is-active', d.dataset.day === String(day)); });
    }

    weeks.forEach(function (week) {
      var todayCol = week.querySelector('.hr-day[data-date="' + today + '"]');
      if (todayCol) todayCol.classList.add('is-today');
      showDay(week, todayCol ? todayCol.dataset.day : 1);
      week.querySelectorAll('.hr-days button').forEach(function (b) {
        b.addEventListener('click', function () { showDay(week, b.dataset.day); });
      });
    });

    weekBtns.forEach(function (b) {
      b.addEventListener('click', function () { showWeek(b.dataset.week); });
    });

    filterBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        group = group === b.dataset.group ? null : b.dataset.group;
        filterBtns.forEach(function (x) { x.setAttribute('aria-pressed', x.dataset.group === group ? 'true' : 'false'); });
        root.querySelectorAll('.hr-l').forEach(function (l) {
          var gs = (l.dataset.groups || '').split(' ');
          l.classList.toggle('is-dim', !!group && gs.indexOf(group) === -1);
        });
      });
    });

    function markPast() {
      var now = root.dataset.now ? nowTs : Date.now();
      root.querySelectorAll('.hr-l[data-end]').forEach(function (l) {
        l.classList.toggle('is-past', Date.parse(l.dataset.end) < now);
      });
    }
    markPast();
    setInterval(markPast, 60000);

    // Šipky ←/→ v tablistech.
    root.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      var tab = e.target.closest('[role=tab]');
      if (!tab) return;
      var tabs = Array.prototype.slice.call(tab.parentNode.querySelectorAll('[role=tab]'));
      var next = tabs[(tabs.indexOf(tab) + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
      next.focus();
      next.click();
    });

    showWeek(0);
    root.classList.add('is-ready');
  }

  function boot() {
    document.querySelectorAll('[data-hr-rozvrh]').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
