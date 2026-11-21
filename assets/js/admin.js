/* KVO Mart admin helpers */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  // mobile menu
  var aside = $('#aside'), btn = $('[data-aside]');
  if (aside && btn) btn.addEventListener('click', function () { var o = aside.classList.toggle('open'); btn.setAttribute('aria-expanded', o ? 'true' : 'false'); });
  // quotation lines
  var lines = $('#qlines'), tpl = $('#qline-tpl');
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-ladd]') && lines && tpl) { lines.appendChild(tpl.content.cloneNode(true)); }
    var rm = e.target.closest('[data-lrm]');
    if (rm && lines) { var row = rm.closest('[data-line]'); if (lines.querySelectorAll('[data-line]').length > 1) row.remove(); else row.querySelectorAll('input').forEach(function (i) { i.value = i.name === 'l_qty[]' ? 1 : ''; }); }
  });
  document.addEventListener('change', function (e) {
    var s = e.target.closest('[data-lpick]'); if (!s) return;
    var o = s.options[s.selectedIndex], row = s.closest('[data-line]');
    if (o.value) {
      var d = row.querySelector('[name="l_desc[]"]'), u = row.querySelector('[name="l_unit[]"]');
      if (!d.value) d.value = o.getAttribute('data-name');
      if (!u.value && o.getAttribute('data-price')) u.value = o.getAttribute('data-price');
    }
  });
})();
