/* KVO Mart — storefront behaviour. Everything works without JavaScript; this makes it smoother. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* toast */
  var tt;
  function toast(msg, link) {
    var t = $('.toast'); if (!t) return;
    t.textContent = msg;
    if (link) { var a = document.createElement('a'); a.href = window.KVO.cart; a.textContent = ' View cart'; a.style.cssText = 'color:var(--gold3);font-weight:600;margin-left:.5rem'; t.appendChild(a); }
    t.hidden = false; clearTimeout(tt); tt = setTimeout(function () { t.hidden = true; }, 3500);
  }

  /* drawers (menu, filters) */
  var lastFocus;
  function openDrawer(id) {
    var d = document.getElementById(id); if (!d) return;
    lastFocus = document.activeElement; d.hidden = false; document.body.style.overflow = 'hidden';
    var f = $('[data-close]', d); if (f) f.focus();
  }
  function closeDrawers() {
    $$('.drawer').forEach(function (d) { d.hidden = true; });
    $$('.modal').forEach(function (m) { m.remove(); });
    document.body.style.overflow = ''; if (lastFocus) try { lastFocus.focus(); } catch (e) {}
  }
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]'); if (o) { e.preventDefault(); openDrawer(o.getAttribute('data-open')); return; }
    if (e.target.closest('[data-close]') || e.target.classList.contains('drawer')) { e.preventDefault(); closeDrawers(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDrawers(); });

  /* confirm dialog (no browser alert boxes) */
  function confirmBox(msg, yes) {
    var m = document.createElement('div'); m.className = 'modal'; m.setAttribute('role', 'dialog'); m.setAttribute('aria-modal', 'true');
    m.innerHTML = '<div style="max-width:440px;margin-top:20vh"><div class="mb"><p class="b"></p><div class="row mt6"><button class="btn danger" type="button" data-y>Yes, continue</button><button class="btn out" type="button" data-n>Go back</button></div></div></div>';
    $('p', m).textContent = msg;
    m.addEventListener('click', function (e) { if (e.target.closest('[data-y]')) { m.remove(); yes(); } else if (e.target === m || e.target.closest('[data-n]')) m.remove(); });
    document.body.appendChild(m); $('[data-n]', m).focus();
  }
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.hasAttribute('data-confirm') && !f._ok) { e.preventDefault(); confirmBox(f.getAttribute('data-confirm'), function () { f._ok = true; f.requestSubmit ? f.requestSubmit() : f.submit(); }); return; }
    var b = e.submitter || $('button[data-busy]', f);
    if (b && b.hasAttribute('data-busy') && !f.classList.contains('js-add')) { setTimeout(function () { b.disabled = true; b.textContent = b.getAttribute('data-busy'); }, 0); }
  }, true);

  /* add to cart without leaving the page */
  function cartCount(n) { $$('.js-cart-n').forEach(function (el) { el.textContent = n; el.hidden = !n; }); }
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var isAdd = f.classList.contains('js-add') || f.id === 'buybox' || (f.closest('.sticky-buy') && true);
    if (!isAdd || !window.fetch) return;
    var sub = e.submitter;
    if (sub && sub.name === 'go' && sub.value === 'buy') return; // buy now → normal post to checkout
    e.preventDefault();
    var fd = new FormData(f); if (sub && sub.name) fd.append(sub.name, sub.value);
    var btn = sub || $('button', f); if (btn) btn.disabled = true;
    fetch(f.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.redirect) { location.href = d.redirect; return; } cartCount(d.count || 0); toast(d.message, d.ok); })
      .catch(function () { f.submit(); })
      .then(function () { if (btn) btn.disabled = false; });
  });

  /* quantity steppers + mirror into the sticky bar */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-qty]'); if (!b) return;
    var i = $('#qty'); if (!i) return;
    var v = Math.max(1, Math.min(Number(i.max) || 999, (Number(i.value) || 1) + Number(b.getAttribute('data-qty'))));
    i.value = v; i.dispatchEvent(new Event('change', { bubbles: true }));
  });
  document.addEventListener('change', function (e) {
    if (e.target.id === 'qty') $$('[data-qty-mirror]').forEach(function (m) { m.value = e.target.value; });
    if (e.target.hasAttribute('data-autosubmit')) e.target.form.submit();
    if (e.target.hasAttribute('data-nav')) location.href = e.target.value;
  });

  /* sticky buy bar on product pages */
  var sticky = $('[data-sticky]'), box = $('#buybox');
  if (sticky && box && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (en) { sticky.classList.toggle('show', !en[0].isIntersecting && en[0].boundingClientRect.top < 0); }).observe(box);
  }

  /* product gallery */
  var gal = $('[data-gallery]');
  if (gal) {
    var thumbs = $$('[data-thumb]'), cnt = $('[data-gcount]'), n = gal.children.length;
    var mark = function () { var k = Math.round(gal.scrollLeft / gal.clientWidth); thumbs.forEach(function (t, j) { t.classList.toggle('on', j === k); }); if (cnt) cnt.textContent = (k + 1) + ' / ' + n; };
    gal.addEventListener('scroll', function () { clearTimeout(gal._t); gal._t = setTimeout(mark, 60); }, { passive: true });
    thumbs.forEach(function (t) { t.addEventListener('click', function () { gal.scrollTo({ left: gal.clientWidth * Number(t.getAttribute('data-thumb')), behavior: 'smooth' }); }); });
  }

  /* print, copy */
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-print]')) { e.preventDefault(); window.print(); }
    var c = e.target.closest('[data-copy]');
    if (c) { e.preventDefault(); var v = c.getAttribute('data-copy'); (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { toast('Copied ' + v); }, function () { window.prompt('Copy this reference:', v); }); }
  });

  /* checkout live totals */
  var co = $('#co');
  if (co && window.fetch) {
    var endpoint = co.getAttribute('data-summary');
    var refresh = function (extra) {
      var p = new URLSearchParams(extra || {});
      fetch(endpoint + '?' + p.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
        $('#co-delivery').innerHTML = d.delivery; $('#co-summary').innerHTML = d.summary; $('#co-expected').value = d.total;
        var pm = $('#co-promomsg'); pm.textContent = d.promo; pm.className = d.promo && !d.promoOk ? 'err' : 'hint'; pm.style.color = d.promoOk ? 'var(--ok)' : '';
      });
    };
    co.addEventListener('change', function (e) {
      var t = e.target;
      if (t.hasAttribute('data-co-state')) refresh({ state: t.value });
      if (t.hasAttribute('data-co')) { $$('#co-delivery .radio').forEach(function (r) { r.classList.toggle('on', $('input', r).checked); }); refresh({ method: t.value }); }
      if (t.hasAttribute('data-co-pay')) $$('[data-co-pay]').forEach(function (i) { i.closest('.radio').classList.toggle('on', i.checked); });
      if (t.hasAttribute('data-co-email') && $('#promo').value) refresh({ email: t.value, promo: $('#promo').value });
      if (t.hasAttribute('data-co-saved')) {
        var o = t.options[t.selectedIndex];
        co.line1.value = o.getAttribute('data-line1') || ''; co.city.value = o.getAttribute('data-city') || ''; co.state.value = o.getAttribute('data-state') || '';
        refresh({ state: co.state.value });
      }
    });
    var ap = $('[data-co-promo]');
    if (ap) ap.addEventListener('click', function () { refresh({ promo: $('#promo').value.trim().toUpperCase(), email: co.email.value }); });
    $('#promo').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); ap.click(); } });
  }
})();
