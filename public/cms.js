/* Applies the owner's saved texts (and logo) on top of the built-in site texts.
   The page already contains the default texts, so if the saved copy cannot be loaded
   (internet cut, server down) the site simply shows the last copy kept in this browser,
   or the built-in texts. Nothing breaks. */
(function () {
  'use strict';
  var KEY = 'cms:content:v1';
  var lang = document.documentElement.lang === 'en' ? 'en' : 'fa';

  function apply(data) {
    if (!data || !data.content) return;
    var L = data.content[lang] || {};
    var S = data.content.all || {};
    var els = document.querySelectorAll('[data-cms]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      var k = el.getAttribute('data-cms');
      if (el.__orig === undefined) el.__orig = el.textContent;
      var v = L[k];
      el.textContent = typeof v === 'string' && v !== '' ? v : el.__orig;
    }
    // Contact details (shared by both languages). Only values of the expected shape are used.
    var RE = { phone: /^\+?[0-9]{6,15}$/, telegram: /^[A-Za-z0-9_]{3,64}$/, whatsapp: /^[0-9]{6,15}$/, bale: /^\+?[A-Za-z0-9_]{3,64}$/ };
    var BASE = { phone: 'tel:', telegram: 'https://t.me/', whatsapp: 'https://wa.me/', bale: 'https://ble.ir/' };
    function contact(ti, ck) {
      var v = S['team.' + ti + '.' + ck];
      return typeof v === 'string' && RE[ck] && RE[ck].test(v) ? v : null;
    }
    var links = document.querySelectorAll('a[data-ti][data-ck]');
    for (var m = 0; m < links.length; m++) {
      var a = links[m], ck = a.getAttribute('data-ck');
      if (a.__orig === undefined) a.__orig = a.getAttribute('href');
      var cv = contact(a.getAttribute('data-ti'), ck);
      a.setAttribute('href', cv ? BASE[ck] + cv : a.__orig);
    }
    var shown = document.querySelectorAll('[data-ti][data-ct]');
    for (var n = 0; n < shown.length; n++) {
      var e2 = shown[n], ck2 = e2.getAttribute('data-ct');
      if (e2.__orig === undefined) e2.__orig = e2.textContent;
      var cv2 = contact(e2.getAttribute('data-ti'), ck2);
      e2.textContent = cv2 ? (ck2 === 'telegram' ? '@' + cv2 : cv2) : e2.__orig;
    }
    var logos = document.querySelectorAll('img[data-cms-logo]');
    for (var j = 0; j < logos.length; j++) {
      var img = logos[j];
      if (img.__orig === undefined) img.__orig = img.getAttribute('src');
      var u = S['site.logo'];
      var ok = typeof u === 'string' && u.indexOf('/cms-api/uploads/') === 0;
      var want = ok ? u : img.__orig;
      if (img.getAttribute('src') !== want) img.setAttribute('src', want);
    }
  }

  try {
    var cached = localStorage.getItem(KEY);
    if (cached) apply(JSON.parse(cached));
  } catch (e) {}

  if (!window.fetch) return;
  fetch('/cms-api/data/content.json', { cache: 'no-cache', credentials: 'omit' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (!d || !d.content) return;
      apply(d);
      try { localStorage.setItem(KEY, JSON.stringify(d)); } catch (e) {}
    })
    .catch(function () {});
})();
