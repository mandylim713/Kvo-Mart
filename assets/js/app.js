/* KVO Mart storefront scripts */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- drawers / sheets ---------- */
  function openPanel(id) {
    var p = document.getElementById(id); if (!p) return;
    p.classList.add('show');
    $$('.overlay[data-close="' + id + '"]').forEach(function (o) { o.classList.add('show'); });
    document.body.classList.add('no-scroll');
  }
  function closePanel(id) {
    var p = document.getElementById(id); if (!p) return;
    p.classList.remove('show');
    $$('.overlay[data-close="' + id + '"]').forEach(function (o) { o.classList.remove('show'); });
    if (!$('.drawer.show, .sheet.show, .filters.show')) document.body.classList.remove('no-scroll');
  }
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]'); if (o) { e.preventDefault(); openPanel(o.getAttribute('data-open')); return; }
    var c = e.target.closest('[data-close]'); if (c) { e.preventDefault(); closePanel(c.getAttribute('data-close')); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') $$('.drawer.show, .sheet.show, .filters.show').forEach(function (p) { closePanel(p.id); });
  });
  // swipe down to close bottom sheets
  $$('.sheet, .filters').forEach(function (s) {
    var y0 = null;
    s.addEventListener('touchstart', function (e) { if (s.scrollTop <= 0) y0 = e.touches[0].clientY; }, { passive: true });
    s.addEventListener('touchend', function (e) { if (y0 !== null && e.changedTouches[0].clientY - y0 > 80) closePanel(s.id); y0 = null; });
  });

  /* ---------- toast ---------- */
  var toastTimer;
  function toast(msg, withLink) {
    var t = $('#toast'); if (!t) return;
    t.innerHTML = '';
    var span = document.createElement('span'); span.textContent = msg; t.appendChild(span);
    if (withLink) { var a = document.createElement('a'); a.href = window.KVO.cartUrl; a.textContent = 'View cart'; t.appendChild(a); }
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove('show'); }, 3200);
  }
  window.kvoToast = toast;

  function setCartCount(n) {
    $$('.js-cart-count').forEach(function (el) { el.textContent = n; el.hidden = !n; });
  }

  /* ---------- add to cart (ajax with graceful fallback) ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.classList.contains('js-add-cart') || !window.fetch) return;
    e.preventDefault();
    var btn = f.querySelector('button[type=submit]');
    if (btn) { btn.disabled = true; }
    var fd = new FormData(f);
    var src = e.submitter && e.submitter.name ? e.submitter : null;
    if (src) fd.append(src.name, src.value);
    fetch(f.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.redirect) { window.location = d.redirect; return; }
        setCartCount(d.count || 0);
        toast(d.message || 'Added to cart', d.ok);
        if (btn && d.ok) {
          var old = btn.innerHTML;
          btn.innerHTML = '<i class="fa-solid fa-check"></i> Added';
          setTimeout(function () { btn.innerHTML = old; }, 1400);
        }
      })
      .catch(function () { f.submit(); })
      .finally(function () { if (btn) btn.disabled = false; });
  });

  /* ---------- qty steppers ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.qty button'); if (!b) return;
    e.preventDefault();
    var inp = b.parentNode.querySelector('input'); if (!inp) return;
    var min = parseInt(inp.min || '1', 10), max = parseInt(inp.max || '9999', 10);
    var v = (parseInt(inp.value, 10) || min) + (b.dataset.step === '-' ? -1 : 1);
    v = Math.max(min, Math.min(max, v));
    inp.value = v;
    inp.dispatchEvent(new Event('change', { bubbles: true }));
    // keep mirrored qty fields in sync (product page main + sticky bar)
    if (inp.dataset.sync) $$('[data-sync="' + inp.dataset.sync + '"]').forEach(function (o) { o.value = v; });
  });
  document.addEventListener('change', function (e) {
    var inp = e.target;
    if (inp.dataset && inp.dataset.sync) $$('[data-sync="' + inp.dataset.sync + '"]').forEach(function (o) { o.value = inp.value; });
    if (inp.classList && inp.classList.contains('js-autosubmit')) {
      clearTimeout(inp._t);
      inp._t = setTimeout(function () { inp.form.submit(); }, 450);
    }
  });

  /* ---------- hero slider ---------- */
  $$('.slider').forEach(function (sl) {
    var track = $('.slides', sl), dots = $$('.dots button', sl), n = dots.length, i = 0, timer;
    if (!track || n < 2) return;
    function go(k) { i = (k + n) % n; track.scrollTo({ left: track.clientWidth * i, behavior: 'smooth' }); }
    function mark() {
      var k = Math.round(track.scrollLeft / track.clientWidth);
      dots.forEach(function (d, j) { d.classList.toggle('active', j === k); }); i = k;
    }
    dots.forEach(function (d, j) { d.addEventListener('click', function () { go(j); restart(); }); });
    track.addEventListener('scroll', function () { clearTimeout(track._s); track._s = setTimeout(mark, 60); }, { passive: true });
    function restart() { clearInterval(timer); timer = setInterval(function () { go(i + 1); }, 5500); }
    track.addEventListener('touchstart', function () { clearInterval(timer); }, { passive: true });
    track.addEventListener('touchend', restart);
    restart();
  });

  /* ---------- product gallery ---------- */
  var gs = $('.gslides');
  if (gs) {
    var thumbs = $$('.thumbs button'), counter = $('.gcount');
    var total = gs.children.length;
    function mark() {
      var k = Math.round(gs.scrollLeft / gs.clientWidth);
      thumbs.forEach(function (t, j) { t.classList.toggle('active', j === k); });
      if (counter) counter.textContent = (k + 1) + ' / ' + total;
    }
    gs.addEventListener('scroll', function () { clearTimeout(gs._s); gs._s = setTimeout(mark, 50); }, { passive: true });
    thumbs.forEach(function (t, j) { t.addEventListener('click', function () { gs.scrollTo({ left: gs.clientWidth * j, behavior: 'smooth' }); }); });
  }

  /* ---------- tabs ---------- */
  $$('.tabs').forEach(function (tb) {
    var btns = $$('.tab-btns button', tb), panes = $$('.tab-pane', tb);
    btns.forEach(function (b, j) {
      b.addEventListener('click', function () {
        btns.forEach(function (x) { x.classList.remove('active'); });
        panes.forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active'); if (panes[j]) panes[j].classList.add('active');
      });
    });
  });

  /* ---------- sticky buy bar: show once main buy button scrolls away ---------- */
  var mainBuy = $('#main-buy'), sticky = $('.sticky-buy');
  if (mainBuy && sticky && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (en) { sticky.classList.toggle('show', !en[0].isIntersecting && en[0].boundingClientRect.top < 0); })
      .observe(mainBuy);
  }

  /* ---------- copy buttons ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]'); if (!b) return;
    var txt = b.getAttribute('data-copy');
    (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () { toast('Copied: ' + txt); })
      .catch(function () { window.prompt('Copy this:', txt); });
  });

  /* ---------- footer accordions collapsed on phones ---------- */
  if (window.matchMedia('(max-width: 899px)').matches) {
    $$('.footer-col[open]').forEach(function (d) { d.removeAttribute('open'); });
  }

  /* ---------- delivery method toggle on checkout ---------- */
  var co = $('#checkout-form');
  if (co) {
    var sub = parseInt(co.dataset.subtotal, 10) || 0, free = parseInt(co.dataset.free, 10) || 0, cur = co.dataset.currency || '₦';
    var fmt = function (n) { return cur + n.toLocaleString('en-NG'); };
    function recalc() {
      var method = (co.querySelector('input[name=delivery_method]:checked') || {}).value || 'delivery';
      var zone = co.querySelector('input[name=zone_id]:checked');
      var fee = 0;
      $$('.js-delivery-only', co).forEach(function (el) { el.hidden = method !== 'delivery'; });
      $$('.js-delivery-only [data-required]', co).forEach(function (el) { el.required = method === 'delivery'; });
      if (method === 'delivery' && zone) fee = parseInt(zone.dataset.fee, 10) || 0;
      if (method === 'delivery' && free > 0 && sub >= free) fee = 0;
      $$('.js-fee').forEach(function (el) { el.textContent = method === 'pickup' ? 'Free (pickup)' : (zone ? (fee ? fmt(fee) : 'Free') : 'Select zone'); });
      $$('.js-total').forEach(function (el) { el.textContent = fmt(sub + fee); });
      var pm = (co.querySelector('input[name=payment_method]:checked') || {}).value;
      var label = { flutterwave: 'Pay ' + fmt(sub + fee) + ' now', whatsapp: 'Send order on WhatsApp', transfer: 'Place order', pod: 'Place order' }[pm] || 'Place order';
      $$('.js-place-label').forEach(function (el) { el.textContent = label; });
    }
    co.addEventListener('change', recalc);
    recalc();
    co.addEventListener('submit', function () {
      $$('.js-place-btn').forEach(function (b) { b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing…'; });
    });
  }
})();
