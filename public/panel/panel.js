(function () {
  'use strict';

  var API = '/cms-api/';
  var csrf = '', me = '';
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
    bad_old_password: 'رمز فعلی شما درست نیست.',
    bad_username: 'نام کاربری فقط حروف انگلیسی، عدد، نقطه، خط تیره و _ باشد (۳ تا ۵۰ نویسه).',
    username_taken: 'این نام کاربری قبلا گرفته شده است.',
    cannot_delete_self: 'حساب خودتان را نمی‌شود حذف کرد.',
    bad_backup: 'این فایل، فایل پشتیبان این سایت نیست.',
    network: 'ارتباط با سرور برقرار نشد. اینترنت را بررسی کنید.',
    bad_title: 'عنوان خالی است یا از ۲۰۰ نویسه بیشتر است.',
    bad_excerpt: 'توضیح کوتاه خالی است یا از ۶۰۰ نویسه بیشتر است.',
    bad_date: 'تاریخ درست نیست.',
    bad_category: 'دسته را انتخاب کنید.',
    bad_image: 'تصویر انتخاب نشده یا آدرسش درست نیست.',
    bad_alt: 'متن جایگزین تصویر خیلی بلند است.',
    bad_video: 'آدرس ویدیو باید با https شروع شود.',
    bad_order: 'ترتیب باید عددی بین ۰ تا ۹۹۹۹ باشد.',
    bad_body: 'متن کامل خیلی طولانی است.',
    bad_slug: 'آدرس صفحه فقط می‌تواند حروف کوچک انگلیسی، عدد و خط تیره باشد و حداقل ۲ نویسه.',
    slug_taken: 'این آدرس صفحه قبلا استفاده شده است. آدرس دیگری بنویسید.',
    not_found: 'این مورد پیدا نشد. صفحه را تازه کنید.',
    write_failed: 'ذخیره‌ی فایل روی هاست ممکن نشد.'
  };
  var CATS = { 'Writing': 'نگارش', 'Articles': 'مقالات علمی', 'Research': 'پژوهش', 'Editing & Quality': 'ویرایش و کنترل کیفیت', 'Data & Technical': 'داده و فنی' };
  var itemsData = null;
  var editing = null;
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
      if (d.auth) { csrf = d.csrf; me = d.user; $('lp').value = ''; boot(); }
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
    nav.insertBefore(h('hr'), nav.firstChild);
    [['items-service', 'خدمات (افزودن و ویرایش)'], ['items-project', 'پروژه‌ها (افزودن و ویرایش)']].forEach(function (x) {
      nav.insertBefore(h('button', { type: 'button', text: x[1], 'aria-current': section === x[0] ? 'true' : 'false',
        onclick: function () { section = x[0]; editing = null; render(); window.scrollTo(0, 0); } }), nav.firstChild);
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
      var L = f.shared ? 'all' : lang;
      if (schema.defaults[L][f.key] === undefined) return; // this text does not exist in this language
      var id = 'f-' + f.key.replace(/\./g, '-');
      var dir = (f.ltr || lang === 'en') ? 'ltr' : 'rtl';
      var input = f.type === 'textarea'
        ? h('textarea', { id: id, rows: '3', dir: dir })
        : h('input', { id: id, type: 'text', dir: dir });
      input.value = effective(L, f.key);
      var wrap = h('div', { class: 'field' });
      var tag = h('span', { class: 'tag', text: 'ویرایش‌شده' });
      var reset = h('button', { type: 'button', class: 'reset', text: 'بازگرداندن به متن اصلی' });
      var orig = h('p', { class: 'defaults' });
      function refresh() {
        var over = isOverridden(L, f.key);
        wrap.className = 'field' + (Object.prototype.hasOwnProperty.call(pending, L + '|' + f.key) ? ' changed' : '');
        tag.hidden = !over; reset.disabled = !over; orig.hidden = !over;
        orig.textContent = over ? 'مقدار اصلی: ' + def(L, f.key) : '';
        updateBar();
      }
      input.addEventListener('input', function () { setValue(L, f.key, input.value); refresh(); });
      reset.addEventListener('click', function () { setValue(L, f.key, def(L, f.key)); input.value = def(L, f.key); refresh(); });
      wrap.appendChild(h('div', { class: 'row' }, [h('label', { for: id, text: f.label }), tag, reset]));
      wrap.appendChild(input); wrap.appendChild(orig);
      box.appendChild(wrap);
      refresh();
    });
    if (g.list === 'faqlist') renderFaqList(box);
  }

  // Questions and answers as a list: add, delete, move up/down.
  function renderFaqList(box) {
    var arr;
    try { arr = JSON.parse(effective(lang, 'faqlist')); } catch (e) { arr = []; }
    if (!Array.isArray(arr)) arr = [];
    var wrap = h('div', { class: 'faqed' });
    box.appendChild(wrap);
    function commit() {
      setValue(lang, 'faqlist', JSON.stringify(arr));
      updateBar();
      note.textContent = isOverridden(lang, 'faqlist') ? 'فهرست سؤال‌ها تغییر کرده است.' : '';
    }
    var note = h('p', { class: 'muted' });
    function draw() {
      wrap.textContent = '';
      wrap.appendChild(h('h3', { text: 'فهرست سؤال‌ها (' + arr.length + ')' }));
      arr.forEach(function (row, i) {
        var q = h('input', { type: 'text', dir: lang === 'fa' ? 'rtl' : 'ltr', placeholder: 'سؤال', 'aria-label': 'سؤال ' + (i + 1) });
        var a = h('textarea', { rows: '3', dir: lang === 'fa' ? 'rtl' : 'ltr', placeholder: 'پاسخ', 'aria-label': 'پاسخ ' + (i + 1) });
        q.value = row[0]; a.value = row[1];
        q.addEventListener('input', function () { row[0] = q.value; commit(); });
        a.addEventListener('input', function () { row[1] = a.value; commit(); });
        function move(d) { var j = i + d; if (j < 0 || j >= arr.length) return; var t = arr[i]; arr[i] = arr[j]; arr[j] = t; commit(); draw(); }
        wrap.appendChild(h('div', { class: 'field' }, [
          h('div', { class: 'row' }, [
            h('strong', { text: 'سؤال ' + (i + 1) }),
            h('button', { type: 'button', class: 'reset', text: 'بالا', disabled: i === 0, onclick: function () { move(-1); } }),
            h('button', { type: 'button', class: 'reset', text: 'پایین', disabled: i === arr.length - 1, onclick: function () { move(1); } }),
            h('button', { type: 'button', class: 'reset', text: 'حذف', onclick: function () {
              if (!confirm('این سؤال حذف شود؟')) return; arr.splice(i, 1); commit(); draw(); } })
          ]),
          q, a
        ]));
      });
      wrap.appendChild(h('div', { class: 'actions' }, [
        h('button', { type: 'button', class: 'btn', text: 'افزودن سؤال', onclick: function () { arr.push(['', '']); commit(); draw(); } }),
        h('button', { type: 'button', class: 'ghost', text: 'بازگرداندن همه‌ی سؤال‌ها به حالت اولیه', onclick: function () {
          if (!confirm('همه‌ی تغییرات این فهرست برای این زبان از بین می‌رود. ادامه می‌دهید؟')) return;
          setValue(lang, 'faqlist', def(lang, 'faqlist')); try { arr = JSON.parse(def(lang, 'faqlist')); } catch (e) { arr = []; } updateBar(); draw(); } })
      ]));
      wrap.appendChild(note);
      note.textContent = isOverridden(lang, 'faqlist') ? 'فهرست سؤال‌ها تغییر کرده است.' : '';
    }
    draw();
  }

  /* ---------- projects and services ---------- */
  function loadItems() {
    return req('items').then(function (d) { if (d.project) itemsData = d; return d; });
  }

  function renderItems(type) {
    var box = $('content'); box.textContent = '';
    if (!itemsData) { box.appendChild(h('p', { class: 'muted', text: 'در حال بارگذاری…' })); loadItems().then(function (d) { if (!d.project) toast(errText(d.error), true); else render(); }); return; }
    if (editing) { renderItemEditor(type, editing); return; }
    var label = type === 'project' ? 'پروژه' : 'خدمت';
    box.appendChild(h('h2', { text: type === 'project' ? 'پروژه‌ها' : 'خدمات' }));
    box.appendChild(h('p', { class: 'muted', text: 'نسخه‌ی ' + (lang === 'fa' ? 'فارسی' : 'انگلیسی') + ' — زبان را از بالا عوض کنید. اگر یک مورد را در هر دو زبان با «آدرس صفحه»ی یکسان بسازید، دکمه‌ی تغییر زبان سایت به همدیگر وصل می‌شود.' }));
    box.appendChild(h('div', { class: 'actions' }, [h('button', { type: 'button', class: 'btn', text: 'افزودن ' + label + ' جدید', onclick: function () { editing = { isNew: true, type: type, lang: lang }; render(); window.scrollTo(0, 0); } })]));
    var list = itemsData[type][lang];
    if (!list.length) box.appendChild(h('p', { class: 'muted', text: 'هنوز موردی برای این زبان وجود ندارد.' }));
    var SRC = { baked: 'اصلی', edited: 'ویرایش‌شده', 'new': 'جدید' };
    list.forEach(function (it) {
      var sub = type === 'project' ? (it.data.date + ' · ' + (CATS[it.data.category] || it.data.category)) : ('ترتیب ' + it.data.order);
      box.appendChild(h('div', { class: 'field itemrow' }, [
        h('div', { class: 'row' }, [
          h('strong', { text: it.data.title, style: 'flex:1;min-width:12rem' }),
          h('span', { class: 'tag', text: SRC[it.src] || '' }),
          h('button', { type: 'button', class: 'reset', text: 'ویرایش', onclick: function () { editing = { isNew: false, type: type, lang: lang, item: it }; render(); window.scrollTo(0, 0); } }),
          h('button', { type: 'button', class: 'reset', text: 'حذف', onclick: function () {
            if (!confirm('«' + it.data.title + '» حذف شود؟')) return;
            req('item_delete', { json: { type: type, lang: lang, key: it.key } }).then(function (d) {
              if (!d.ok) { toast(errText(d.error), true); return; }
              toast('حذف شد.'); loadItems().then(render);
            });
          } })
        ]),
        h('p', { class: 'muted', style: 'margin:.2rem 0 0', text: sub + ' · /' + (type === 'project' ? 'projects' : 'services') + '/' + it.key })
      ]));
    });
    var gone = (itemsData.removed || []).filter(function (x) { return x.type === type && x.lang === lang; });
    if (gone.length) {
      box.appendChild(h('h3', { text: 'حذف‌شده‌ها', style: 'margin-top:1.6rem' }));
      gone.forEach(function (x) {
        box.appendChild(h('div', { class: 'field' }, [h('div', { class: 'row' }, [
          h('span', { text: x.title, style: 'flex:1' }),
          h('button', { type: 'button', class: 'reset', text: 'بازگرداندن', onclick: function () {
            req('item_reset', { json: { type: type, lang: lang, key: x.key } }).then(function (d) {
              if (!d.ok) { toast(errText(d.error), true); return; }
              toast('برگشت.'); loadItems().then(render);
            });
          } })
        ])]));
      });
    }
  }

  function imageField(label, getUrl, setUrl, altInput) {
    var url = h('input', { type: 'text', dir: 'ltr', placeholder: '/images/… یا https://…' });
    url.value = getUrl();
    var prev = h('img', { alt: '', style: 'max-width:160px;max-height:100px;border:1px solid var(--line);border-radius:8px;background:#fff;display:none;margin:.4rem 0' });
    function show() { var u = url.value.trim(); prev.style.display = u ? 'block' : 'none'; if (u) prev.src = u; }
    url.addEventListener('input', function () { setUrl(url.value.trim()); show(); });
    var file = h('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp' });
    file.addEventListener('change', function () {
      if (!file.files.length) return;
      var fd = new FormData(); fd.append('file', file.files[0]);
      req('image', { form: fd }).then(function (d) {
        if (d.url) { url.value = d.url; setUrl(d.url); show(); toast('تصویر آپلود شد.'); } else toast(d.error === 'too_big' ? 'حجم تصویر بیشتر از ۳ مگابایت است.' : errText(d.error), true);
        file.value = '';
      });
    });
    show();
    var kids = [h('label', { text: label }), prev, file, h('p', { class: 'muted', style: 'margin:.3rem 0', text: 'یا آدرس تصویر را بنویسید (PNG، JPG یا WebP، تا ۳ مگابایت):' }), url];
    if (altInput) kids.push(h('label', { text: 'توضیح تصویر (برای نابینایان و گوگل)' }), altInput);
    return h('div', null, kids);
  }

  function renderItemEditor(type, ed) {
    var box = $('content'); box.textContent = '';
    var isP = type === 'project';
    var it = ed.item || { key: '', data: isP ? { title: '', date: new Date().toISOString().slice(0, 10), category: 'Writing', excerpt: '', cover: '', coverAlt: '', video: '', featured: false } : { title: '', order: 100, excerpt: '', image: '', imageAlt: '' }, body: '' };
    var d = JSON.parse(JSON.stringify(it.data));
    var rtl = ed.lang === 'fa' ? 'rtl' : 'ltr';
    box.appendChild(h('h2', { text: (ed.isNew ? 'افزودن ' : 'ویرایش ') + (isP ? 'پروژه' : 'خدمت') + ' (' + (ed.lang === 'fa' ? 'فارسی' : 'انگلیسی') + ')' }));
    function txt(label, val, onInput, area, dirx) {
      var el = area ? h('textarea', { rows: String(area), dir: dirx || rtl }) : h('input', { type: 'text', dir: dirx || rtl });
      el.value = val; el.addEventListener('input', function () { onInput(el.value); });
      box.appendChild(h('label', { text: label })); box.appendChild(el); return el;
    }
    var slug = it.key || (isP ? 'project-' : 'service-') + Date.now().toString(36);
    if (ed.isNew) {
      txt('آدرس صفحه (حروف کوچک انگلیسی، عدد و -)', slug, function (v) { slug = v.trim(); }, 0, 'ltr');
      box.appendChild(h('p', { class: 'muted', text: 'آدرس صفحه بعد از ساخت قابل تغییر نیست. اگر همین مورد نسخه‌ی زبان دیگر دارد، همان آدرس را برایش بنویسید.' }));
    } else box.appendChild(h('p', { class: 'muted', text: 'آدرس صفحه: /' + (isP ? 'projects' : 'services') + '/' + slug }));
    txt('عنوان', d.title, function (v) { d.title = v; });
    txt('توضیح کوتاه (زیر عنوان و برای گوگل)', d.excerpt, function (v) { d.excerpt = v; }, 3);
    if (isP) {
      var dt = h('input', { type: 'date' }); dt.value = d.date; dt.addEventListener('input', function () { d.date = dt.value; });
      box.appendChild(h('label', { text: 'تاریخ (میلادی)' })); box.appendChild(dt);
      var cat = h('select', null, itemsData.categories.map(function (c) { var o = h('option', { value: c, text: CATS[c] || c }); if (c === d.category) o.selected = true; return o; }));
      cat.addEventListener('input', function () { d.category = cat.value; });
      box.appendChild(h('label', { text: 'دسته' })); box.appendChild(cat);
      var alt = h('input', { type: 'text', dir: rtl }); alt.value = d.coverAlt; alt.addEventListener('input', function () { d.coverAlt = alt.value; });
      box.appendChild(imageField('تصویر شاخص (اختیاری)', function () { return d.cover; }, function (u) { d.cover = u; }, alt));
      txt('آدرس ویدیو (یوتیوب، آپارات یا ویمیو؛ اختیاری)', d.video, function (v) { d.video = v.trim(); }, 0, 'ltr');
      var fe = h('input', { type: 'checkbox', style: 'width:auto' }); fe.checked = !!d.featured; fe.addEventListener('change', function () { d.featured = fe.checked; });
      box.appendChild(h('label', { style: 'display:flex;gap:.5rem;align-items:center' }, [fe, h('span', { text: 'نمایش در «پروژه‌های منتخب» صفحه اول' })]));
    } else {
      var ord = h('input', { type: 'number', min: '0', max: '9999', dir: 'ltr' }); ord.value = d.order; ord.addEventListener('input', function () { d.order = parseInt(ord.value, 10) || 0; });
      box.appendChild(h('label', { text: 'ترتیب نمایش (عدد کوچک‌تر بالاتر)' })); box.appendChild(ord);
      var alt2 = h('input', { type: 'text', dir: rtl }); alt2.value = d.imageAlt; alt2.addEventListener('input', function () { d.imageAlt = alt2.value; });
      box.appendChild(imageField('تصویر خدمت', function () { return d.image; }, function (u) { d.image = u; }, alt2));
    }
    var body = it.body || '';
    var ta = txt('متن کامل صفحه', body, function (v) { body = v; }, 16);
    box.appendChild(h('p', { class: 'muted', text: 'راهنما: «## عنوان بخش»، «- مورد لیست»، «**پررنگ**»، «[متن لینک](https://…)»، «![توضیح](آدرس عکس)». برای هر پاراگراف یک خط خالی بگذارید.' }));
    var pv = h('div', { class: 'prose card', hidden: true });
    var imgFile = h('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp' });
    imgFile.addEventListener('change', function () {
      if (!imgFile.files.length) return;
      var fd = new FormData(); fd.append('file', imgFile.files[0]);
      req('image', { form: fd }).then(function (r2) {
        imgFile.value = '';
        if (!r2.url) { toast(r2.error === 'too_big' ? 'حجم تصویر بیشتر از ۳ مگابایت است.' : errText(r2.error), true); return; }
        var pos = ta.selectionStart || ta.value.length; var ins = '\n\n![توضیح تصویر](' + r2.url + ')\n\n';
        ta.value = ta.value.slice(0, pos) + ins + ta.value.slice(pos); body = ta.value; toast('تصویر در متن گذاشته شد.');
      });
    });
    box.appendChild(h('div', { class: 'actions' }, [
      h('button', { type: 'button', class: 'ghost', text: 'پیش‌نمایش متن', onclick: function () {
        if (!pv.hidden) { pv.hidden = true; return; }
        req('preview', { json: { body: body } }).then(function (r3) { pv.innerHTML = r3.html || ''; pv.hidden = false; pv.setAttribute('dir', rtl); });
      } })
    ]));
    box.appendChild(h('label', { text: 'گذاشتن عکس در متن' })); box.appendChild(imgFile);
    box.appendChild(pv);
    var acts = [
      h('button', { type: 'button', class: 'btn', text: 'ذخیره', onclick: function (ev) {
        var b = ev.currentTarget; b.disabled = true;
        req('item_save', { json: { type: type, lang: ed.lang, key: slug, isNew: ed.isNew, data: d, body: body } }).then(function (r4) {
          b.disabled = false;
          if (!r4.ok) { toast(errText(r4.error), true); return; }
          toast('ذخیره شد و در سایت اعمال شد.');
          editing = null; loadItems().then(render); window.scrollTo(0, 0);
        });
      } }),
      h('button', { type: 'button', class: 'ghost', text: 'انصراف', onclick: function () { editing = null; render(); } })
    ];
    if (ed.item && ed.item.src === 'edited') acts.push(h('button', { type: 'button', class: 'ghost', text: 'بازگرداندن به نسخه‌ی اصلی', onclick: function () {
      if (!confirm('تغییرات این مورد از بین می‌رود و نسخه‌ی اصلی برمی‌گردد. ادامه می‌دهید؟')) return;
      req('item_reset', { json: { type: type, lang: ed.lang, key: slug } }).then(function (r5) { if (!r5.ok) { toast(errText(r5.error), true); return; } toast('برگشت.'); editing = null; loadItems().then(render); });
    } }));
    box.appendChild(h('div', { class: 'actions', style: 'margin-top:1.4rem' }, acts));
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
    // change own username
    var un = h('input', { type: 'text', dir: 'ltr', autocomplete: 'username' });
    var unp = h('input', { type: 'password', autocomplete: 'current-password', dir: 'ltr' });
    var unCard = h('section', { class: 'card tool' }, [
      h('h3', { text: 'تغییر نام کاربری' }),
      h('p', { class: 'muted', text: 'نام کاربری فعلی: ' + (me || '') }),
      h('label', { text: 'نام کاربری جدید' }), un,
      h('label', { text: 'رمز فعلی خودتان' }), unp,
      h('div', { class: 'actions' }, [h('button', { type: 'button', class: 'btn', text: 'تغییر نام کاربری', onclick: function () {
        req('username', { json: { username: un.value, password: unp.value } }).then(function (d) {
          if (d.ok) { me = d.user; unp.value = ''; un.value = ''; toast('نام کاربری عوض شد.'); renderTools(); } else toast(errText(d.error), true);
        });
      } })])
    ]);
    // accounts
    var usersBox = h('section', { class: 'card tool' }, [h('h3', { text: 'حساب‌های کاربری' }), h('p', { class: 'muted', text: 'در حال بارگذاری…' })]);
    function drawUsers(list) {
      usersBox.textContent = '';
      usersBox.appendChild(h('h3', { text: 'حساب‌های کاربری' }));
      usersBox.appendChild(h('p', { class: 'muted', text: 'هر نفر با حساب جدا وارد می‌شود و همه می‌توانند سایت را ویرایش کنند. برای هر کار روی حساب‌ها، رمز خودتان لازم است.' }));
      var gp = h('input', { type: 'password', autocomplete: 'current-password', dir: 'ltr' });
      usersBox.appendChild(h('label', { text: 'رمز فعلی خودتان (برای تایید کارهای پایین)' }));
      usersBox.appendChild(gp);
      list.forEach(function (x) {
        var np = h('input', { type: 'password', autocomplete: 'new-password', dir: 'ltr', placeholder: 'رمز جدید (حداقل ۸ نویسه)', style: 'flex:1;min-width:10rem' });
        var kids = [h('strong', { text: x.username + (x.self ? ' (شما)' : ''), style: 'min-width:8rem' }), np,
          h('button', { type: 'button', class: 'reset', text: 'تغییر رمز', onclick: function () {
            req('user_setpass', { json: { id: x.id, password: gp.value, 'new': np.value } }).then(function (d) {
              if (d.ok) { np.value = ''; toast('رمز ' + x.username + ' عوض شد.'); } else toast(errText(d.error), true);
            });
          } })];
        if (!x.self) kids.push(h('button', { type: 'button', class: 'reset', text: 'حذف حساب', onclick: function () {
          if (!confirm('حساب «' + x.username + '» حذف شود؟')) return;
          req('user_delete', { json: { id: x.id, password: gp.value } }).then(function (d) {
            if (d.ok) { toast('حساب حذف شد.'); loadUsers(); } else toast(errText(d.error), true);
          });
        } }));
        usersBox.appendChild(h('div', { class: 'field' }, [h('div', { class: 'row' }, kids)]));
      });
      usersBox.appendChild(h('h3', { text: 'افزودن حساب جدید', style: 'margin-top:1.2rem' }));
      var an = h('input', { type: 'text', dir: 'ltr', autocomplete: 'off' });
      var ap = h('input', { type: 'password', dir: 'ltr', autocomplete: 'new-password' });
      usersBox.appendChild(h('label', { text: 'نام کاربری' })); usersBox.appendChild(an);
      usersBox.appendChild(h('label', { text: 'رمز عبور (حداقل ۸ نویسه)' })); usersBox.appendChild(ap);
      usersBox.appendChild(h('div', { class: 'actions' }, [h('button', { type: 'button', class: 'btn', text: 'افزودن حساب', onclick: function () {
        req('user_add', { json: { username: an.value, 'new': ap.value, password: gp.value } }).then(function (d) {
          if (d.ok) { toast('حساب ساخته شد.'); loadUsers(); } else toast(errText(d.error), true);
        });
      } })]));
    }
    function loadUsers() { req('users').then(function (d) { if (d.users) drawUsers(d.users); else toast(errText(d.error), true); }); }
    [logo, backup, pw, unCard, usersBox].forEach(function (s) { box.appendChild(s); });
    loadUsers();
  }

  function render() {
    renderNav();
    var bar = $('langbar');
    var isItems = section.indexOf('items-') === 0;
    bar.hidden = section === 'tools' || (isItems && !!editing);
    $('lang-note').hidden = section === 'tools' || isItems;
    $('lang-note').textContent = section === 'team' ? 'نام‌ها برای هر زبان جداست. شماره‌ها و آی‌دی‌ها برای هر دو زبان یکی است.' : lang === 'fa'
      ? 'متن‌های نسخه‌ی فارسی سایت. اگر کادر را خالی کنید، متن اصلی برمی‌گردد.'
      : 'متن‌های نسخه‌ی انگلیسی سایت. اگر کادر را خالی کنید، متن اصلی برمی‌گردد.';
    Array.prototype.forEach.call(bar.querySelectorAll('button'), function (b) { b.setAttribute('aria-selected', b.dataset.lang === lang ? 'true' : 'false'); });
    if (section === 'tools') renderTools();
    else if (isItems) renderItems(section.slice(6));
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
    for (var q = 0; q < changes.length; q++) {
      if (changes[q].k === 'faqlist' && changes[q].v !== null) {
        var rows = []; try { rows = JSON.parse(changes[q].v); } catch (e) {}
        for (var w = 0; w < rows.length; w++) {
          if (!String(rows[w][0]).trim() || !String(rows[w][1]).trim()) { toast('سؤال ' + (w + 1) + ' خالی است. سؤال و پاسخ را کامل کنید یا آن را حذف کنید.', true); return; }
        }
      }
    }
    var RE = { phone: /^\+?[0-9]{6,15}$/, telegram: /^[A-Za-z0-9_]{3,64}$/, whatsapp: /^[0-9]{6,15}$/, bale: /^\+?[A-Za-z0-9_]{3,64}$/ };
    var NAMES = { phone: 'شماره تلفن', telegram: 'آی‌دی تلگرام', whatsapp: 'شماره واتساپ', bale: 'آی‌دی بله' };
    for (var i = 0; i < changes.length; i++) {
      var m = /^team\.\d+\.(phone|telegram|whatsapp|bale)$/.exec(changes[i].k);
      if (m && changes[i].v !== null && !RE[m[1]].test(changes[i].v)) { toast(NAMES[m[1]] + ' درست نیست. فاصله و علامت اضافه نگذارید.', true); return; }
    }
    var btn = $('save'); btn.disabled = true;
    req('save', { json: { changes: changes } }).then(function (d) {
      btn.disabled = false;
      if (!d.ok) { toast(errText(d.error), true); return; }
      changes.forEach(function (c) { if (c.v === null) delete saved[c.lang][c.k]; else saved[c.lang][c.k] = c.v; });
      pending = {};
      render();
      if (d.snapshot === false) toast('ذخیره شد، اما فایل نمایش عمومی ساخته نشد. دسترسی نوشتن پوشه cms-api/data را بررسی کنید.', true);
      else if (d.patch && d.patch.failed) toast('ذخیره شد، اما نوشتن بعضی صفحه‌ها ممکن نشد. دسترسی نوشتن فایل‌های سایت را بررسی کنید.', true);
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
      req('reapply', { json: {} }); // makes sure the saved titles and FAQ are in the page files, even after a fresh upload of the site
    }).catch(function () { toast(errText('network'), true); });
  }

  req('me').then(function (d) {
    if (d.auth) { csrf = d.csrf; me = d.user; boot(); }
    else if (d.error === 'not_installed') { showLogin(); $('login-msg').textContent = errText('not_installed'); }
    else showLogin();
  });
})();
