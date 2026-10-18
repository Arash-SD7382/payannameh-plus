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
