<?php
// Projects and services edited in the panel.
// The site stays plain static files: every time items change, the server rebuilds the affected
// pages (item pages, lists, home page cards, sitemap) from the page shell that the site build
// produced (cms-tpl/item) and the built-in items listed in cms-items.json.
if (!defined('CMS_API')) { http_response_code(403); exit; }

/* ---------- storage ---------- */

function cms_items_schema() {
    return 'CREATE TABLE IF NOT EXISTS cms_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(10) NOT NULL,
        lang VARCHAR(2) NOT NULL,
        slug VARCHAR(80) NOT NULL,
        data MEDIUMTEXT NOT NULL,
        body MEDIUMTEXT NOT NULL,
        deleted TINYINT(1) NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_item (type, lang, slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
}

function cms_baked() {
    static $b = null;
    if ($b !== null) return $b;
    $raw = @file_get_contents(dirname(__DIR__) . '/cms-items.json');
    $b = $raw ? json_decode($raw, true) : null;
    if (!is_array($b) || empty($b['strings'])) $b = ['site' => '', 'categories' => [], 'projects' => [], 'services' => [], 'strings' => []];
    return $b;
}

function cms_item_rows() {
    $rows = cms_db()->query('SELECT type, lang, slug, data, body, deleted FROM cms_items')->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['type']][$r['lang']][$r['slug']] = $r;
    return $out;
}

function cms_words_mins($body) {
    $n = count(preg_split('/\s+/u', $body));
    return max(1, (int) round($n / 200));
}

// All items of a type in a language as the site should show them (built-in ones with the owner's edits applied).
function cms_effective($type, $lang, $rows = null) {
    $b = cms_baked();
    $rows = $rows === null ? cms_item_rows() : $rows;
    $items = [];
    foreach ($b[$type . 's'] as $e) {
        if ($e['lang'] !== $lang) continue;
        $items[$e['key']] = ['type' => $type, 'lang' => $lang, 'key' => $e['key'], 'data' => $e['data'], 'body' => $e['body'], 'src' => 'baked',
            'mins' => isset($e['mins']) ? $e['mins'] : cms_words_mins($e['body'])];
    }
    if (isset($rows[$type][$lang])) {
        foreach ($rows[$type][$lang] as $slug => $r) {
            if ($r['deleted']) { unset($items[$slug]); continue; }
            $d = json_decode($r['data'], true);
            if (!is_array($d)) continue;
            $src = isset($items[$slug]) ? 'edited' : 'new';
            $items[$slug] = ['type' => $type, 'lang' => $lang, 'key' => $slug, 'data' => $d, 'body' => $r['body'], 'src' => $src, 'mins' => cms_words_mins($r['body'])];
        }
    }
    $items = array_values($items);
    if ($type === 'project') usort($items, function ($a, $b2) { return strcmp($b2['data']['date'], $a['data']['date']); });
    else usort($items, function ($a, $b2) { return (int) $a['data']['order'] - (int) $b2['data']['order']; });
    return $items;
}

/* ---------- validation ---------- */

function cms_img_url_ok($u) {
    if ($u === '') return true;
    if (strlen($u) > 300) return false;
    return preg_match('~^(/images/[A-Za-z0-9_./\-]+|/cms-api/uploads/img-[a-f0-9]{16}\.(png|jpg|webp)|https?://[^\s"<>]+)$~', $u) === 1;
}

// Returns [cleanData, error]
function cms_item_clean($type, $in) {
    $b = cms_baked();
    $g = function ($k, $max) use ($in) {
        $v = isset($in[$k]) && is_string($in[$k]) ? trim($in[$k]) : '';
        return cms_len($v) > $max ? null : $v;
    };
    $title = $g('title', 200); $excerpt = $g('excerpt', 600);
    if ($title === null || $title === '') return [null, 'bad_title'];
    if ($excerpt === null || $excerpt === '') return [null, 'bad_excerpt'];
    if ($type === 'project') {
        $date = $g('date', 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) return [null, 'bad_date'];
        $cat = $g('category', 40);
        if (!in_array($cat, $b['categories'], true)) return [null, 'bad_category'];
        $cover = $g('cover', 300); $alt = $g('coverAlt', 200); $video = $g('video', 300);
        if ($cover === null || !cms_img_url_ok($cover)) return [null, 'bad_image'];
        if ($alt === null) return [null, 'bad_alt'];
        if ($video === null || ($video !== '' && !preg_match('~^https?://[^\s"<>]+$~', $video))) return [null, 'bad_video'];
        return [['title' => $title, 'date' => $date, 'category' => $cat, 'excerpt' => $excerpt, 'cover' => $cover, 'coverAlt' => $alt, 'video' => $video,
            'featured' => !empty($in['featured'])], null];
    }
    $img = $g('image', 300); $alt = $g('imageAlt', 200);
    if ($img === null || $img === '' || !cms_img_url_ok($img)) return [null, 'bad_image'];
    if ($alt === null) return [null, 'bad_alt'];
    $order = isset($in['order']) ? (int) $in['order'] : 100;
    if ($order < 0 || $order > 9999) return [null, 'bad_order'];
    return [['title' => $title, 'order' => $order, 'excerpt' => $excerpt, 'image' => $img, 'imageAlt' => $alt], null];
}

/* ---------- small helpers ---------- */

function cms_e($s) { return htmlspecialchars((string) $s, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'); }

function cms_lower($s) { return mb_strtolower($s, 'UTF-8'); }

function cms_href($lang, $p) { return $lang === 'en' ? ($p === '/' ? '/en/' : '/en' . $p) : $p; }

function cms_fa_digits($s) { return strtr((string) $s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']); }

function cms_g2j($gy, $gm, $gd) {
    $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = $gm > 2 ? $gy + 1 : $gy;
    $days = 355666 + (365 * $gy) + (int) (($gy2 + 3) / 4) - (int) (($gy2 + 99) / 100) + (int) (($gy2 + 399) / 400) + $gd + $gdm[$gm - 1];
    $jy = -1595 + (33 * (int) ($days / 12053));
    $days %= 12053;
    $jy += 4 * (int) ($days / 1461);
    $days %= 1461;
    if ($days > 365) { $jy += (int) (($days - 1) / 365); $days = ($days - 1) % 365; }
    if ($days < 186) { $jm = 1 + (int) ($days / 31); $jd = 1 + ($days % 31); }
    else { $jm = 7 + (int) (($days - 186) / 30); $jd = 1 + (($days - 186) % 30); }
    return [$jy, $jm, $jd];
}

function cms_fmt_date($ymd, $lang, $long) {
    $y = (int) substr($ymd, 0, 4); $m = (int) substr($ymd, 5, 2); $d = (int) substr($ymd, 8, 2);
    if ($lang === 'fa') {
        list($jy, $jm, $jd) = cms_g2j($y, $m, $d);
        $names = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        return cms_fa_digits($jd) . ' ' . $names[$jm - 1] . ' ' . cms_fa_digits($jy);
    }
    $long_n = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $n = $long_n[$m - 1];
    return ($long ? $n : substr($n, 0, 3)) . ' ' . $d . ', ' . $y;
}

function cms_ts_ms($ymd) { return strtotime($ymd . ' 00:00:00 UTC') * 1000; }

/* ---------- markdown (small and safe: all HTML in the text is escaped) ---------- */

function cms_slugify($text) {
    $s = cms_lower(trim(preg_replace('~<[^>]*>~', '', $text)));
    $s = preg_replace('/[^\p{L}\p{N}\p{M}_\- ]/u', '', $s);
    return str_replace(' ', '-', $s);
}

function cms_md_url_ok($u, $img) {
    if ($img) return preg_match('~^(/[A-Za-z0-9_./\-%]+|https?://[^\s"<>]+)$~', $u) === 1;
    return preg_match('~^(/[^\s"<>]*|#[^\s"<>]*|https?://[^\s"<>]+|mailto:[^\s"<>]+|tel:[^\s"<>]+)$~', $u) === 1;
}

// Curly quotes, dashes and ellipsis in text (not inside tags), like the site's own markdown does.
function cms_smart($html) {
    $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $p) {
        if ($p === '' || $p[0] === '<') continue;
        $p = str_replace(['---', '--', '...'], ['—', '–', '…'], $p);
        $p = preg_replace('/(^|[\s(\[\x{2014}\x{2013}])&quot;/u', '$1“', $p);
        $p = str_replace('&quot;', '”', $p);
        $p = preg_replace('/(^|[\s(\[“\x{2014}\x{2013}])\'(?=\S)/u', '$1‘', $p);
        $p = str_replace("'", '’', $p);
        $parts[$i] = $p;
    }
    return implode('', $parts);
}

function cms_md_inline($text) {
    $codes = [];
    $text = preg_replace_callback('/`([^`\n]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . cms_e($m[1]) . '</code>';
        return "\x01" . (count($codes) - 1) . "\x02";
    }, $text);
    $t = cms_e($text);
    // htmlspecialchars turned & into &amp; inside URLs, which is what an attribute needs
    $t = preg_replace_callback('/!\[([^\]\n]*)\]\(([^)\s]+)\)/u', function ($m) {
        $u = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        return cms_md_url_ok($u, true) ? '<img src="' . $m[2] . '" alt="' . $m[1] . '" loading="lazy">' : $m[0];
    }, $t);
    $t = preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/u', function ($m) {
        $u = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (!cms_md_url_ok($u, false)) return $m[0];
        $ext = preg_match('~^https?://~', $u) ? ' target="_blank" rel="noopener"' : '';
        return '<a href="' . $m[2] . '"' . $ext . '>' . $m[1] . '</a>';
    }, $t);
    $t = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/us', '<strong>$1</strong>', $t);
    $t = preg_replace('/(?<![*\w])\*(?=\S)([^*\n]+?)(?<=\S)\*(?![*\w])/u', '<em>$1</em>', $t);
    $t = cms_smart($t);
    $t = preg_replace_callback('/\x01(\d+)\x02/', function ($m) use ($codes) { return $codes[(int) $m[1]]; }, $t);
    return $t;
}

// Returns [html, headings[[depth, slug, text]]]
function cms_md($md) {
    $md = str_replace(["\r\n", "\r"], "\n", (string) $md);
    $lines = explode("\n", $md);
    $html = ''; $heads = []; $used = [];
    $para = []; $list = null; $quote = [];
    $flush = function () use (&$para, &$html) {
        if ($para) { $html .= '<p>' . cms_md_inline(implode("\n", $para)) . '</p>'; $para = []; }
    };
    $flushList = function () use (&$list, &$html) {
        if ($list) {
            $tag = $list['t'];
            $html .= '<' . $tag . '>';
            foreach ($list['i'] as $it) $html .= '<li>' . cms_md_inline($it) . '</li>';
            $html .= '</' . $tag . '>';
            $list = null;
        }
    };
    $flushQuote = function () use (&$quote, &$html) {
        if ($quote) { $html .= '<blockquote><p>' . cms_md_inline(implode("\n", $quote)) . '</p></blockquote>'; $quote = []; }
    };
    $inCode = false; $code = [];
    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) {
            if ($inCode) { $html .= '<pre><code>' . cms_e(implode("\n", $code)) . '</code></pre>'; $code = []; $inCode = false; }
            else { $flush(); $flushList(); $flushQuote(); $inCode = true; }
            continue;
        }
        if ($inCode) { $code[] = $line; continue; }
        if (trim($line) === '') { $flush(); $flushList(); $flushQuote(); continue; }
        if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/u', $line, $m)) {
            $flush(); $flushList(); $flushQuote();
            $depth = strlen($m[1]);
            $plain = preg_replace('/[*_`]/', '', $m[2]);
            $slug = cms_slugify($plain); $base = $slug; $n = 1;
            while (isset($used[$slug])) { $slug = $base . '-' . $n++; }
            $used[$slug] = true;
            $heads[] = [$depth, $slug, $plain];
            $html .= '<h' . $depth . ' id="' . cms_e($slug) . '">' . cms_md_inline($m[2]) . '</h' . $depth . '>';
            continue;
        }
        if (preg_match('/^\s*(-{3,}|\*{3,})\s*$/', $line)) { $flush(); $flushList(); $flushQuote(); $html .= '<hr>'; continue; }
        if (preg_match('/^>\s?(.*)$/', $line, $m)) { $flush(); $flushList(); $quote[] = $m[1]; continue; }
        if (preg_match('/^\s*[-*+]\s+(.*)$/', $line, $m)) {
            $flush(); $flushQuote();
            if ($list && $list['t'] !== 'ul') $flushList();
            if (!$list) $list = ['t' => 'ul', 'i' => []];
            $list['i'][] = $m[1];
            continue;
        }
        if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $line, $m)) {
            $flush(); $flushQuote();
            if ($list && $list['t'] !== 'ol') $flushList();
            if (!$list) $list = ['t' => 'ol', 'i' => []];
            $list['i'][] = $m[1];
            continue;
        }
        $flushList(); $flushQuote();
        $para[] = trim($line);
    }
    if ($inCode) $html .= '<pre><code>' . cms_e(implode("\n", $code)) . '</code></pre>';
    $flush(); $flushList(); $flushQuote();
    return [$html, $heads];
}

/* ---------- page parts (the same markup as the site's own templates) ---------- */

function cms_card_project($p, $pageLang, $S, $foreign) {
    $L = $p['lang']; $ti = $S[$L]; $d = $p['data'];
    $cover = $d['cover'] !== ''
        ? '<img class="cover" src="' . cms_e($d['cover']) . '" alt="' . cms_e($d['coverAlt']) . '" loading="lazy" width="640" height="360">'
        : '<div class="cover" role="presentation"></div>';
    $cat = isset($ti['cat'][$d['category']]) ? $ti['cat'][$d['category']] : $d['category'];
    return '<a class="card" lang="' . $L . '" dir="' . $ti['dir'] . '" href="' . cms_e(cms_href($L, '/projects/' . $p['key'])) . '"'
        . ' data-title="' . cms_e(cms_lower($d['title'])) . '" data-cat="' . cms_e($d['category']) . '"'
        . ' data-text="' . cms_e(cms_lower($d['excerpt'] . ' ' . $cat)) . '" data-date="' . cms_ts_ms($d['date']) . '">'
        . $cover
        . '<div class="body"><div style="display:flex;gap:.4rem;flex-wrap:wrap"><span class="badge">' . cms_e($cat) . '</span>'
        . ($foreign ? '<span class="badge gold">' . cms_e($S[$pageLang]['foreignBadge']) . '</span>' : '') . '</div>'
        . '<h3>' . cms_e($d['title']) . '</h3><p class="muted sm" style="margin:0">' . cms_e($d['excerpt']) . '</p>'
        . '<p class="muted sm" style="margin:0">' . cms_e(cms_fmt_date($d['date'], $L, false)) . ' · ' . $p['mins'] . ' ' . cms_e($ti['minRead']) . '</p></div></a>';
}

function cms_card_service($s, $lang, $S, $more, $btn) {
    $d = $s['data'];
    return '<a class="card scard" href="' . cms_e(cms_href($lang, '/services/' . $s['key'])) . '">'
        . '<img class="cover" src="' . cms_e($d['image']) . '" alt="' . cms_e($d['imageAlt'] !== '' ? $d['imageAlt'] : $d['title']) . '" loading="lazy" width="640" height="360">'
        . '<div class="body"><h3>' . cms_e($d['title']) . '</h3>'
        . ($more ? '<p class="muted sm" style="margin:0">' . cms_e($d['excerpt']) . '</p>' : '')
        . (($more || $btn) ? '<span class="btn sm" style="align-self:flex-start;margin-top:auto">' . cms_e($S[$lang]['readMoreBtn']) . '</span>' : '')
        . '</div></a>';
}

function cms_video_embed($v) {
    if (preg_match('~(?:v=|youtu\.be/|embed/|shorts/)([\w-]{11})~', $v, $m)) return ['https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1'];
    if (preg_match('~vimeo\.com/(\d+)~', $v, $m)) return ['https://player.vimeo.com/video/' . $m[1] . '?autoplay=1'];
    if (preg_match('~aparat\.com/(?:v/|video/video/embed/videohash/)([\w]+)~', $v, $m)) return ['https://www.aparat.com/video/video/embed/videohash/' . $m[1] . '/vt/frame'];
    return null;
}

const CMS_VIDEO_SCRIPT = "<script>\ndocument.querySelectorAll('.vframe').forEach(function(f){f.querySelector('.vplay').addEventListener('click',function(){var i=document.createElement('iframe');i.src=f.dataset.src;i.title=f.dataset.title;i.allow='autoplay; fullscreen; picture-in-picture';i.allowFullscreen=true;i.className='video';f.replaceWith(i)})})\n</script>";

function cms_article_project($p, $own, $S) {
    $lang = $p['lang']; $t = $S[$lang]; $d = $p['data'];
    list($body, $heads) = cms_md($p['body']);
    $idx = 0;
    foreach ($own as $i => $x) if ($x['key'] === $p['key']) $idx = $i;
    $newer = isset($own[$idx - 1]) ? $own[$idx - 1] : null;
    $older = isset($own[$idx + 1]) ? $own[$idx + 1] : null;
    $cat = isset($t['cat'][$d['category']]) ? $t['cat'][$d['category']] : $d['category'];
    $embed = $d['video'] !== '' ? cms_video_embed($d['video']) : null;
    if ($embed) {
        $media = '<div class="vframe" data-src="' . cms_e($embed[0]) . '" data-title="' . cms_e($d['title']) . '">'
            . ($d['cover'] !== '' ? '<img src="' . cms_e($d['cover']) . '" alt="" loading="lazy">' : '')
            . '<button class="vplay" type="button" aria-label="' . cms_e($t['playVideo']) . '">▶</button></div>'
            . '<p class="muted sm" style="margin:.5rem 0 0"><a href="' . cms_e($d['video']) . '" target="_blank" rel="noopener">' . cms_e($t['videoFallback']) . '</a></p>';
    } elseif ($d['cover'] !== '') {
        $media = '<img class="cover" style="border-radius:var(--r)" src="' . cms_e($d['cover']) . '" alt="' . cms_e($d['coverAlt']) . '">';
    } else $media = '';
    $toc = array_values(array_filter($heads, function ($h) { return $h[0] === 2; }));
    $tocHtml = '';
    if (count($toc) > 1) {
        $tocHtml = '<aside class="toc" aria-label="' . cms_e($t['onThisPage']) . '"><strong>' . cms_e($t['onThisPage']) . '</strong><ol>';
        foreach ($toc as $h) $tocHtml .= '<li><a href="#' . cms_e($h[1]) . '">' . cms_e($h[2]) . '</a></li>';
        $tocHtml .= '</ol></aside>';
    }
    $pn = '<div class="pn">'
        . ($older ? '<a href="' . cms_e(cms_href($lang, '/projects/' . $older['key'])) . '">' . cms_e($t['prev']) . '<br><strong>' . cms_e($older['data']['title']) . '</strong></a>' : '<span></span>')
        . ($newer ? '<a href="' . cms_e(cms_href($lang, '/projects/' . $newer['key'])) . '" style="text-align:end">' . cms_e($t['next']) . '<br><strong>' . cms_e($newer['data']['title']) . '</strong></a>' : '<span></span>')
        . '</div>';
    $related = array_slice(array_values(array_filter($own, function ($x) use ($p) { return $x['key'] !== $p['key'] && $x['data']['category'] === $p['data']['category']; })), 0, 3);
    $relHtml = '';
    if ($related) {
        $relHtml = '<section class="soft"><div class="wrap"><h2>' . cms_e($t['related']) . '</h2><div class="grid">';
        foreach ($related as $r) $relHtml .= cms_card_project($r, $lang, $S, false);
        $relHtml .= '</div></div></section>';
    }
    return '<article><section style="padding-bottom:var(--s3)"><div class="wrap article-head">'
        . '<nav class="crumbs" aria-label="Breadcrumb"><a href="' . cms_href($lang, '/') . '">' . cms_e($t['home']) . '</a> / <a href="' . cms_e(cms_href($lang, '/projects')) . '">' . cms_e($t['nav']['projects']) . '</a> / <a href="' . cms_e(cms_href($lang, '/projects?cat=' . rawurlencode($d['category']))) . '">' . cms_e($cat) . '</a></nav>'
        . '<h1>' . cms_e($d['title']) . '</h1><p class="muted" style="font-size:1.15rem">' . cms_e($d['excerpt']) . '</p>'
        . '<div class="meta"><span>' . cms_e($t['by']) . ': ' . cms_e($t['brand']) . '</span><span>' . cms_e(cms_fmt_date($d['date'], $lang, true)) . '</span><span>' . $p['mins'] . ' ' . cms_e($t['minRead']) . '</span></div>'
        . $media . '</div></section>'
        . '<section style="padding-top:var(--s3)"><div class="wrap layout"><div><div class="prose">' . $body . '</div>' . $pn . '</div>' . $tocHtml . '</div></section></article>'
        . $relHtml . CMS_VIDEO_SCRIPT;
}

function cms_article_service($s, $own, $S, $teamHtml) {
    $lang = $s['lang']; $t = $S[$lang]; $d = $s['data'];
    list($body) = cms_md($s['body']);
    $related = array_slice(array_values(array_filter($own, function ($x) use ($s) { return $x['key'] !== $s['key']; })), 0, 3);
    $relHtml = '';
    if ($related) {
        $relHtml = '<section class="soft"><div class="wrap"><h2>' . cms_e($t['relatedServices']) . '</h2><div class="grid g2">';
        foreach ($related as $r) $relHtml .= cms_card_service($r, $lang, $S, false, false);
        $relHtml .= '</div></div></section>';
    }
    return '<article><section style="padding-bottom:var(--s3)"><div class="wrap article-head">'
        . '<nav class="crumbs" aria-label="Breadcrumb"><a href="' . cms_href($lang, '/') . '">' . cms_e($t['home']) . '</a> / <a href="' . cms_e(cms_href($lang, '/services')) . '">' . cms_e($t['nav']['services']) . '</a> / ' . cms_e($d['title']) . '</nav>'
        . '<h1>' . cms_e($d['title']) . '</h1><p class="muted" style="font-size:1.15rem">' . cms_e($d['excerpt']) . '</p>'
        . '<img class="cover" style="border-radius:var(--r);margin-top:1rem" src="' . cms_e($d['image']) . '" alt="' . cms_e($d['imageAlt'] !== '' ? $d['imageAlt'] : $d['title']) . '" width="1200" height="675">'
        . '</div></section>'
        . '<section style="padding-top:var(--s3)"><div class="wrap article-head"><div class="prose">' . $body . '</div>'
        . '<div class="cta"><h2>' . cms_e($t['ctaTitle']) . '</h2><p>' . cms_e($t['ctaText']) . '</p>' . $teamHtml . '</div></div></section></article>'
        . $relHtml;
}

/* ---------- writing files ---------- */

function cms_write_file($path, $content) {
    $old = @file_get_contents($path);
    if ($old === $content) return 'same';
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return 'fail';
    $tmp = $path . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $content) === false) return 'fail';
    if (!@rename($tmp, $path)) { @unlink($tmp); return 'fail'; }
    return 'written';
}

function cms_replace_region($html, $name, $inner) {
    $re = '~<!--cms:' . preg_quote($name, '~') . '-->.*?<!--/cms:' . preg_quote($name, '~') . '-->~su';
    $new = '<!--cms:' . $name . '-->' . $inner . '<!--/cms:' . $name . '-->';
    $out = preg_replace_callback($re, function () use ($new) { return $new; }, $html, 1, $n);
    return $n ? $out : $html;
}

function cms_abs($site, $u) { return preg_match('~^https?://~', $u) ? $u : $site . $u; }

// Builds one item page from the page shell.
function cms_render_item_page($tpl, $teamHtml, $item, $articleHtml, $hasOther, $site, $S) {
    $lang = $item['lang']; $key = $item['key']; $d = $item['data'];
    $isProject = $item['type'] === 'project';
    $other = $lang === 'fa' ? 'en' : 'fa';
    $html = str_replace('@@ARTICLE@@', $articleHtml, $tpl);
    $img = $isProject ? ($d['cover'] !== '' ? $d['cover'] : '/images/logo.png') : $d['image'];
    $path = cms_href($lang, ($isProject ? '/projects/' : '/services/') . $key);
    $abs = $site . $path . '/';
    $title = $d['title'] === $S[$lang]['brand'] ? $d['title'] : $d['title'] . ' | ' . $S[$lang]['brand'];
    $html = cms_patch_html($html, $title, $d['excerpt'], null);
    $html = preg_replace('~<meta name="robots" content="noindex">~', '', $html, 1);
    $html = preg_replace_callback('~(<link rel="canonical" href=")[^"]*(")~', function ($m) use ($abs) { return $m[1] . $abs . $m[2]; }, $html, 1);
    $html = preg_replace_callback('~(<meta property="og:url" content=")[^"]*(")~', function ($m) use ($abs) { return $m[1] . $abs . $m[2]; }, $html, 1);
    $html = preg_replace_callback('~(<meta property="og:image" content=")[^"]*(")~', function ($m) use ($site, $img) { return $m[1] . cms_e(cms_abs($site, $img)) . $m[2]; }, $html, 1);
    $html = preg_replace_callback('~(<link rel="alternate" hreflang="' . $lang . '" href=")[^"]*(")~', function ($m) use ($abs) { return $m[1] . $abs . $m[2]; }, $html, 1);
    if ($isProject) $html = preg_replace('~(<meta property="og:type" content=")website(")~', '$1article$2', $html, 1);
    // language switch + hreflang
    if ($isProject) $alt = $hasOther ? cms_href($other, '/projects/' . $key) : cms_href($other, '/');
    else $alt = $hasOther ? cms_href($other, '/services/' . $key) : cms_href($other, '/services');
    if ($isProject && !$hasOther) $html = preg_replace('~<link rel="alternate" hreflang="[a-z]+" href="[^"]*"/?>~', '', $html);
    else $html = preg_replace_callback('~https?://[^"/]+/@@ALT@@~', function () use ($site, $alt) { return $site . $alt; }, $html);
    $html = str_replace('@@ALT@@', $alt, $html);
    // JSON-LD
    if ($isProject) {
        $ld = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $d['title'], 'description' => $d['excerpt'], 'inLanguage' => $lang,
            'datePublished' => $d['date'] . 'T00:00:00.000Z', 'author' => ['@type' => 'Organization', 'name' => $S[$lang]['brand']],
            'publisher' => ['@type' => 'Organization', 'name' => $S[$lang]['brand']], 'mainEntityOfPage' => $site . $path];
        if ($d['cover'] !== '') $ld['image'] = $d['cover'];
    } else {
        $ld = ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => $d['title'], 'description' => $d['excerpt'], 'inLanguage' => $lang,
            'url' => $site . $path, 'image' => cms_abs($site, $d['image']), 'provider' => ['@type' => 'Organization', 'name' => $S[$lang]['brand']]];
    }
    $json = json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    $html = preg_replace_callback('~<script type="application/ld\+json">.*?</script>~su', function () use ($json) { return '<script type="application/ld+json">' . $json . '</script>'; }, $html, 1);
    // the current section is marked in the main menu
    $section = $isProject ? '/projects' : '/services';
    $href = cms_href($lang, $section);
    $html = preg_replace_callback('~<a href="' . preg_quote($href, '~') . '"( data-cms="nav\.[a-z]+")~', function ($m) use ($href) { return '<a href="' . $href . '" aria-current="page"' . $m[1]; }, $html, 1);
    return $html;
}

function cms_sitemap($site, $list) {
    // $list: array of [path, [other lang path|null]] per language page
    $x = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';
    foreach ($list as $u) {
        $x .= '<url><loc>' . cms_e($site . $u['path']) . '</loc>';
        if ($u['alts']) foreach ($u['alts'] as $hl => $p) $x .= '<xhtml:link rel="alternate" hreflang="' . $hl . '" href="' . cms_e($site . $p) . '"/>';
        $x .= '</url>';
    }
    return $x . '</urlset>';
}

// Rebuilds everything that depends on projects and services. Safe to run any number of times.
function cms_generate_items() {
    $root = dirname(__DIR__);
    $b = cms_baked();
    $res = ['written' => 0, 'failed' => 0, 'removed' => 0];
    if (empty($b['strings'])) return $res;
    $S = $b['strings']; $site = rtrim($b['site'], '/');
    $rows = cms_item_rows();
    $proj = ['fa' => cms_effective('project', 'fa', $rows), 'en' => cms_effective('project', 'en', $rows)];
    $svc = ['fa' => cms_effective('service', 'fa', $rows), 'en' => cms_effective('service', 'en', $rows)];
    $tplRaw = [];
    foreach (['fa', 'en'] as $l) $tplRaw[$l] = @file_get_contents($root . '/' . ($l === 'en' ? 'en/' : '') . 'cms-tpl/item/index.html');
    $keep = [];
    $count = function ($r) use (&$res) { if ($r === 'written') $res['written']++; elseif ($r === 'fail') $res['failed']++; };

    foreach (['fa', 'en'] as $lang) {
        $other = $lang === 'fa' ? 'en' : 'fa';
        if ($tplRaw[$lang]) {
            // the contact block that service pages show comes from the shell itself
            $team = '';
            $tpl = preg_replace_callback('~@@TEAM_START@@(.*?)@@TEAM_END@@~su', function ($m) use (&$team) { $team = $m[1]; return ''; }, $tplRaw[$lang], 1);
            $otherKeysP = array_map(function ($x) { return $x['key']; }, $proj[$other]);
            $otherKeysS = array_map(function ($x) { return $x['key']; }, $svc[$other]);
            foreach ($proj[$lang] as $p) {
                $html = cms_render_item_page($tpl, $team, $p, cms_article_project($p, $proj[$lang], $S), in_array($p['key'], $otherKeysP, true), $site, $S);
                $rel = ($lang === 'en' ? 'en/' : '') . 'projects/' . $p['key'] . '/index.html';
                $keep[$rel] = true; $count(cms_write_file($root . '/' . $rel, $html));
            }
            foreach ($svc[$lang] as $s) {
                $html = cms_render_item_page($tpl, $team, $s, cms_article_service($s, $svc[$lang], $S, $team), in_array($s['key'], $otherKeysS, true), $site, $S);
                $rel = ($lang === 'en' ? 'en/' : '') . 'services/' . $s['key'] . '/index.html';
                $keep[$rel] = true; $count(cms_write_file($root . '/' . $rel, $html));
            }
        }
        // lists and home page
        $pre = $lang === 'en' ? 'en/' : '';
        $own = $proj[$lang];
        $have = array_map(function ($x) { return $x['key']; }, $own);
        $all = $own;
        foreach ($proj[$other] as $x) if (!in_array($x['key'], $have, true)) { $x['foreign'] = true; $all[] = $x; }
        usort($all, function ($a, $c) { return strcmp($c['data']['date'], $a['data']['date']); });
        $cards = ''; foreach ($all as $p) $cards .= cms_card_project($p, $lang, $S, !empty($p['foreign']));
        $f = $root . '/' . $pre . 'projects/index.html';
        if (is_file($f)) $count(cms_write_file($f, cms_replace_region(file_get_contents($f), 'projects-grid', $cards)));
        $sc = ''; foreach ($svc[$lang] as $s) $sc .= cms_card_service($s, $lang, $S, true, false);
        $f = $root . '/' . $pre . 'services/index.html';
        if (is_file($f)) $count(cms_write_file($f, cms_replace_region(file_get_contents($f), 'services-grid', $sc)));
        $f = $root . '/' . $pre . 'index.html';
        if (is_file($f)) {
            $h = file_get_contents($f);
            $sc2 = ''; foreach ($svc[$lang] as $s) $sc2 .= cms_card_service($s, $lang, $S, false, true);
            $h = cms_replace_region($h, 'home-services', $sc2);
            $feat = array_slice(array_values(array_filter($all, function ($p) { return !empty($p['data']['featured']); })), 0, 3);
            $shown = $feat ? $feat : array_slice($all, 0, 6);
            $inner = '<h2 data-cms="' . ($feat ? 'featured' : 'latest') . '">' . cms_e($feat ? $S[$lang]['featured'] : $S[$lang]['latest']) . '</h2>';
            if ($shown) { $inner .= '<div class="grid">'; foreach ($shown as $p) $inner .= cms_card_project($p, $lang, $S, !empty($p['foreign'])); $inner .= '</div>'; }
            else $inner .= '<div class="empty" data-cms="noProjects">' . cms_e($S[$lang]['noProjects']) . '</div>';
            $h = cms_replace_region($h, 'home-projects', $inner);
            $count(cms_write_file($f, $h));
        }
    }

    // sitemap
    $list = [];
    foreach (['/', '/about', '/contact', '/projects', '/services'] as $p) {
        $fa = $p === '/' ? '/' : $p . '/'; $en = $p === '/' ? '/en/' : '/en' . $p . '/';
        $alts = ['fa-IR' => $fa, 'en-US' => $en];
        $list[] = ['path' => $fa, 'alts' => $alts]; $list[] = ['path' => $en, 'alts' => ['en-US' => $en, 'fa-IR' => $fa]];
    }
    foreach (['project' => [$proj, 'projects'], 'service' => [$svc, 'services']] as $type => $pair) {
        foreach (['fa', 'en'] as $lang) {
            $other = $lang === 'fa' ? 'en' : 'fa';
            $keys = array_map(function ($x) { return $x['key']; }, $pair[0][$other]);
            foreach ($pair[0][$lang] as $it) {
                $path = cms_href($lang, '/' . $pair[1] . '/' . $it['key']) . '/';
                $alts = null;
                if (in_array($it['key'], $keys, true)) {
                    $a = cms_href('fa', '/' . $pair[1] . '/' . $it['key']) . '/'; $e = cms_href('en', '/' . $pair[1] . '/' . $it['key']) . '/';
                    $alts = $lang === 'fa' ? ['fa-IR' => $a, 'en-US' => $e] : ['en-US' => $e, 'fa-IR' => $a];
                }
                $list[] = ['path' => $path, 'alts' => $alts];
            }
        }
    }
    if ($site !== '') $count(cms_write_file($root . '/sitemap-0.xml', cms_sitemap($site, $list)));

    // files of items that were removed
    $genFile = CMS_DATA . '/generated.json';
    $old = is_file($genFile) ? json_decode((string) file_get_contents($genFile), true) : [];
    $gone = is_array($old) ? $old : [];
    foreach ($b['projects'] as $e) $gone[] = ($e['lang'] === 'en' ? 'en/' : '') . 'projects/' . $e['key'] . '/index.html';
    foreach ($b['services'] as $e) $gone[] = ($e['lang'] === 'en' ? 'en/' : '') . 'services/' . $e['key'] . '/index.html';
    foreach (array_unique($gone) as $rel) {
        if (isset($keep[$rel]) || !preg_match('~^(en/)?(projects|services)/[a-z0-9][a-z0-9\-]*/index\.html$~', $rel)) continue;
        $f = $root . '/' . $rel;
        if (is_file($f) && @unlink($f)) { @rmdir(dirname($f)); $res['removed']++; }
    }
    if (!is_dir(CMS_DATA)) @mkdir(CMS_DATA, 0755, true);
    @file_put_contents($genFile, json_encode(array_keys($keep)));
    cms_gc_uploads();
    return $res;
}

// Deletes uploaded pictures that no page, item or logo uses any more (only files older than a day).
function cms_gc_uploads() {
    $files = glob(CMS_UPLOADS . '/img-*');
    if (!$files) return;
    $used = [];
    $db = cms_db();
    foreach ($db->query('SELECT data, body FROM cms_items')->fetchAll() as $r) {
        if (preg_match_all('~/cms-api/uploads/(img-[a-f0-9]{16}\.[a-z]+)~', $r['data'] . ' ' . $r['body'], $m)) foreach ($m[1] as $n) $used[$n] = true;
    }
    foreach ($files as $f) {
        $n = basename($f);
        if (!isset($used[$n]) && filemtime($f) < time() - 86400) @unlink($f);
    }
}

function cms_store_image($bytes) {
    if (strlen($bytes) > 3 * 1024 * 1024) return ['error' => 'too_big'];
    $ext = cms_image_ext($bytes);
    if (!$ext) return ['error' => 'bad_type'];
    if (!is_dir(CMS_UPLOADS)) @mkdir(CMS_UPLOADS, 0755, true);
    $name = 'img-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (@file_put_contents(CMS_UPLOADS . '/' . $name, $bytes) === false) return ['error' => 'write_failed'];
    @chmod(CMS_UPLOADS . '/' . $name, 0644);
    return ['ok' => true, 'url' => '/cms-api/uploads/' . $name];
}
