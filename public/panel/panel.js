(function () {
  'use strict';

  var API = '/cms-api/';
  var csrf = '';
  var schema = null;                       // { groups, defaults:{fa,en} } built with the site
  var saved = { fa: {}, en: {}, all: {} }; // what the server has
  var pending = {};                        // "lang|key" -> string (new text) or null (back to default)
  var lang = 'fa';
  var section = 'hero';

  var $ = function (id) { return document.getElementById(id); };

  var ERR = {
    bad_login: 'نام کاربری یا رمز عبور درست نیست.',
    too_many: 'تلاش‌های ناموفق زیاد بود. چند دقیقه بعد دوباره امتحان کنید.',
    auth: 'نشست شما تمام شده است. دوباره وارد شوید.',
    csrf: 'نشست شما منقضی شده است. صفحه را تازه کنید و دوباره وارد شوید.',
    not_installed: 'پنل هنوز نصب نشده است. ابتدا install.php را اجرا کنید.',
    db_connect: 'اتصال به دیتابیس برقرار نشد.',
    too_big: 'حجم تصویر بیشتر از ۲ مگابایت است.',
    bad_type: 'فقط تصویر PNG، JPG یا WebP قبول است.',
    no_file: 'فایلی انتخاب نشده است.',
    weak_password: 'رمز جدید باید حداقل ۸ نویسه باشد.',
    bad_old_password: 'رمز فعلی درست نیست.',
    bad_backup: 'این فایل، فایل پشتیبان این سایت نیست.',
    network: 'ارتباط با سرور برقرار نشد. اینترنت را بررسی کنید.'
  };
  function errText(code) { return ERR[code] || 'خطایی رخ داد. دوباره امتحان کنید.'; }

  function h(tag, attrs, kids) {
    var e = document.createElement(tag);
    if (attrs) for (var k in attrs) {
      if (k === 'text') e.textContent = attrs[k];
      else if (k.slice(0, 2) === 'on') e.addEventListener(k.slice(2), attrs[k]);
      else if (attrs[k] !== false && attrs[k] != null) e.setAttribute(k, attrs[k] === true ? '' : attrs[k]);
    }
    (kids || []).forEach(function (c) { if (c) e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return e;
  }

  function toast(msg, bad) {
    var t = $('toast');
    t.textContent = msg; t.className = 'toast' + (bad ? ' err' : ''); t.hidden = false;
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { t.hidden = true; }, bad ? 6000 : 3000);
  }

  function req(action, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: {} };
    if (csrf) init.headers['X-CSRF-Token'] = csrf;
    if (opts.json) { init.method = 'POST'; init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.json); }
    if (opts.form) { init.method = 'POST'; init.body = opts.form; }
    return fetch(API + '?a=' + action, init).then(function (r) {
      return r.json().catch(function () { return { error: 'server' }; }).then(function (d) {
        if (d && d.error === 'auth') { showLogin(); }
        return d || {};
      });
    }, function () { return { error: 'network' }; });
  }

  /* ---------- login ---------- */
  function showLogin() { $('app').hidden = true; $('login').hidden = false; $('lu').focus(); }
  $('login-form').addEventListener('submit', function (e) {
    e.preventDefault();
    $('login-msg').textContent = '';
    req('login', { json: { username: $('lu').value, password: $('lp').value } }).then(function (d) {
      if (d.auth) { csrf = d.csrf; $('lp').value = ''; boot(); }
      else $('login-msg').textContent = errText(d.error);
    });
  });
  $('logout').addEventListener('click', function () {
    if (count() && !confirm('تغییرات ذخیره‌نشده از بین می‌رود. خارج شوید؟')) return;
    req('logout', { json: {} }).then(function () { pending = {}; csrf = ''; showLogin(); });
  });

  /* ---------- data helpers ---------- */
  function def(l, k) { var v = schema.defaults[l][k]; return v === undefined ? '' : v; }
  function effective(l, k) {
    var id = l + '|' + k;
    if (Object.prototype.hasOwnProperty.call(pending, id)) return pending[id] === null ? def(l, k) : pending[id];
    return saved[l][k] !== undefined ? saved[l][k] : def(l, k);
  }
  function isOverridden(l, k) {
    var id = l + '|' + k;
    if (Object.prototype.hasOwnProperty.call(pending, id)) return pending[id] !== null;
    return saved[l][k] !== undefined;
  }
  function setValue(l, k, val) {
    var id = l + '|' + k;
    var target = (val.trim() === '' || val === def(l, k)) ? null : val;
    var current = saved[l][k] === undefined ? null : saved[l][k];
    if (target === current) delete pending[id]; else pending[id] = target;
  }
  function count() { return Object.keys(pending).length; }

  function updateBar() {
    var n = count();
    $('savebar').hidden = n === 0;
    $('savecount').textContent = n + ' تغییر ذخیره‌نشده';
  }
  window.addEventListener('beforeunload', function (e) { if (count()) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------- rendering ---------- */
  function renderNav() {
    var nav = $('nav'); nav.textContent = '';
    schema.groups.forEach(function (g) {
      nav.appendChild(h('button', { type: 'button', text: g.title, 'aria-current': section === g.id ? 'true' : 'false',
        onclick: function () { section = g.id; render(); window.scrollTo(0, 0); } }));
    });
    nav.appendChild(h('hr'));
    nav.appendChild(h('button', { type: 'button', text: 'لوگو، پشتیبان و رمز', 'aria-current': section === 'tools' ? 'true' : 'false',
      onclick: function () { section = 'tools'; render(); window.scrollTo(0, 0); } }));
  }

  function renderFields(g) {
    var box = $('content'); box.textContent = '';
    box.appendChild(h('h2', { text: g.title }));
    if (g.hint) box.appendChild(h('p', { class: 'muted', text: g.hint }));
    g.fields.forEach(function (f) {
      if (schema.defaults[lang][f.key] === undefined) return; // this text does not exist in this language
      var id = 'f-' + f.key.replace(/\./g, '-');
      var input = f.type === 'textarea'
        ? h('textarea', { id: id, rows: '3', dir: lang === 'fa' ? 'rtl' : 'ltr' })
        : h('input', { id: id, type: 'text', dir: lang === 'fa' ? 'rtl' : 'ltr' });
      input.value = effective(lang, f.key);
      var wrap = h('div', { class: 'field' });
      var tag = h('span', { class: 'tag', text: 'ویرایش‌شده' });
      var reset = h('button', { type: 'button', class: 'reset', text: 'بازگرداندن به متن اصلی' });
      var orig = h('p', { class: 'defaults' });
      function refresh() {
        var over = isOverridden(lang, f.key);
        wrap.className = 'field' + (Object.prototype.hasOwnProperty.call(pending, lang + '|' + f.key) ? ' changed' : '');
        tag.hidden = !over; reset.disabled = !over; orig.hidden = !over;
        orig.textContent = over ? 'متن اصلی: ' + def(lang, f.key) : '';
        updateBar();
      }
      input.addEventListener('input', function () { setValue(lang, f.key, input.value); refresh(); });
      reset.addEventListener('click', function () { setValue(lang, f.key, def(lang, f.key)); input.value = def(lang, f.key); refresh(); });
      wrap.appendChild(h('div', { class: 'row' }, [h('label', { for: id, text: f.label }), tag, reset]));
      wrap.appendChild(input); wrap.appendChild(orig);
      box.appendChild(wrap);
      refresh();
    });
  }

  function renderTools() {
    var box = $('content'); box.textContent = '';
    box.appendChild(h('h2', { text: 'لوگو، پشتیبان و رمز' }));

    // logo
    var logoUrl = saved.all['site.logo'] || '/images/logo.png';
    var prev = h('img', { src: logoUrl, alt: 'لوگوی فعلی' });
    var file = h('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp' });
    var logo = h('section', { class: 'card tool' }, [
      h('h3', { text: 'لوگوی سایت' }),
      h('p', { class: 'muted', text: 'تصویر مربعی با فرمت PNG، JPG یا WebP و حجم کمتر از ۲ مگابایت. لوگو در همه‌ی صفحه‌ها عوض می‌شود.' }),
      h('div', { class: 'logo-prev' }, [prev]),
      file,
      h('div', { class: 'actions' }, [
        h('button', { type: 'button', class: 'btn', text: 'آپلود لوگوی جدید', onclick: function () {
          if (!file.files.length) { toast('اول یک تصویر انتخاب کنید.', true); return; }
          var fd = new FormData(); fd.append('file', file.files[0]);
          req('logo', { form: fd }).then(function (d) {
            if (d.url) { saved.all['site.logo'] = d.url; toast('لوگو عوض شد.'); renderTools(); } else toast(errText(d.error), true);
          });
        } }),
        h('button', { type: 'button', class: 'ghost', text: 'بازگشت به لوگوی اصلی', onclick: function () {
          req('logo_reset', { json: {} }).then(function (d) {
            if (d.ok) { delete saved.all['site.logo']; toast('لوگوی اصلی برگشت.'); renderTools(); } else toast(errText(d.error), true);
          });
        } })
      ])
    ]);

    // backup
    var rfile = h('input', { type: 'file', accept: 'application/json,.json' });
    var backup = h('section', { class: 'card tool' }, [
      h('h3', { text: 'فایل پشتیبان' }),
      h('p', { class: 'muted', text: 'همه‌ی متن‌های ویرایش‌شده و لوگو در یک فایل ذخیره می‌شود. این فایل را جای امن نگه دارید.' }),
      h('div', { class: 'actions' }, [h('a', { class: 'btn', href: API + '?a=backup', download: '', style: 'text-decoration:none;display:inline-block', text: 'دانلود فایل پشتیبان' })]),
      h('p', { class: 'muted', style: 'margin-top:1.2rem', text: 'بازیابی از فایل پشتیبان (متن‌های فعلی جایگزین می‌شود):' }),
      rfile,
      h('div', { class: 'actions' }, [h('button', { type: 'button', class: 'ghost', text: 'بازیابی', onclick: function () {
        if (!rfile.files.length) { toast('اول فایل پشتیبان را انتخاب کنید.', true); return; }
        if (!confirm('متن‌های فعلی با محتوای فایل جایگزین می‌شود. ادامه می‌دهید؟')) return;
        rfile.files[0].text().then(function (txt) {
          var data; try { data = JSON.parse(txt); } catch (e) { toast(errText('bad_backup'), true); return; }
          req('restore', { json: data }).then(function (d) {
            if (d.ok) { toast('بازیابی انجام شد.'); pending = {}; loadContent().then(render); } else toast(errText(d.error), true);
          });
        });
      } })])
    ]);

    // password
    var o = h('input', { type: 'password', autocomplete: 'current-password', dir: 'ltr' });
    var n = h('input', { type: 'password', autocomplete: 'new-password', dir: 'ltr' });
    var pw = h('section', { class: 'card tool' }, [
      h('h3', { text: 'تغییر رمز عبور' }),
      h('label', { text: 'رمز فعلی' }), o,
      h('label', { text: 'رمز جدید (حداقل ۸ نویسه)' }), n,
      h('div', { class: 'actions' }, [h('button', { type: 'button', class: 'btn', text: 'تغییر رمز', onclick: function () {
        req('password', { json: { old: o.value, 'new': n.value } }).then(function (d) {
          if (d.ok) { o.value = ''; n.value = ''; toast('رمز عبور عوض شد.'); } else toast(errText(d.error), true);
        });
      } })])
    ]);
    [logo, backup, pw].forEach(function (s) { box.appendChild(s); });
  }

  function render() {
    renderNav();
    var bar = $('langbar');
    bar.hidden = section === 'tools';
    $('lang-note').hidden = section === 'tools';
    $('lang-note').textContent = lang === 'fa'
      ? 'متن‌های نسخه‌ی فارسی سایت. اگر کادر را خالی کنید، متن اصلی برمی‌گردد.'
      : 'متن‌های نسخه‌ی انگلیسی سایت. اگر کادر را خالی کنید، متن اصلی برمی‌گردد.';
    Array.prototype.forEach.call(bar.querySelectorAll('button'), function (b) { b.setAttribute('aria-selected', b.dataset.lang === lang ? 'true' : 'false'); });
    if (section === 'tools') renderTools();
    else renderFields(schema.groups.filter(function (g) { return g.id === section; })[0] || schema.groups[0]);
    updateBar();
  }

  Array.prototype.forEach.call($('langbar').querySelectorAll('button'), function (b) {
    b.addEventListener('click', function () { lang = b.dataset.lang; render(); });
  });

  $('discard').addEventListener('click', function () { pending = {}; render(); });
  $('save').addEventListener('click', function () {
    var changes = Object.keys(pending).map(function (id) {
      var p = id.split('|'); return { lang: p[0], k: p[1], v: pending[id] };
    });
    if (!changes.length) return;
    var btn = $('save'); btn.disabled = true;
    req('save', { json: { changes: changes } }).then(function (d) {
      btn.disabled = false;
      if (!d.ok) { toast(errText(d.error), true); return; }
      changes.forEach(function (c) { if (c.v === null) delete saved[c.lang][c.k]; else saved[c.lang][c.k] = c.v; });
      pending = {};
      render();
      if (d.snapshot === false) toast('ذخیره شد، اما فایل نمایش عمومی ساخته نشد. دسترسی نوشتن پوشه cms-api/data را بررسی کنید.', true);
      else toast('ذخیره شد. تغییرات در سایت دیده می‌شود.');
    });
  });

  /* ---------- start ---------- */
  function loadContent() {
    return req('content').then(function (d) {
      if (d.content) saved = { fa: d.content.fa || {}, en: d.content.en || {}, all: d.content.all || {} };
    });
  }
  function boot() {
    fetch('/cms-defaults.json', { cache: 'no-cache' }).then(function (r) { return r.json(); }).then(function (s) {
      schema = s;
      return loadContent();
    }).then(function () {
      $('login').hidden = true; $('app').hidden = false;
      render();
    }).catch(function () { toast(errText('network'), true); });
  }

  req('me').then(function (d) {
    if (d.auth) { csrf = d.csrf; boot(); }
    else if (d.error === 'not_installed') { showLogin(); $('login-msg').textContent = errText('not_installed'); }
    else showLogin();
  });
})();
