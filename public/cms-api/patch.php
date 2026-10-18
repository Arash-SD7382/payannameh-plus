<?php
// Writes the owner's saved titles/descriptions and FAQ list into the already-built HTML pages.
// The site stays plain static files (fast, good for search engines); this only rewrites a few spots in them.
// It is safe to run again and again: it always writes "saved value, or the original from the build".
if (!defined('CMS_API')) { http_response_code(403); exit; }

function cms_faq_valid($json) {
    $a = json_decode($json, true);
    if (!is_array($a) || count($a) > 60) return false;
    foreach ($a as $row) {
        if (!is_array($row) || count($row) !== 2 || !is_string($row[0]) || !is_string($row[1])) return false;
        if (trim($row[0]) === '' || trim($row[1]) === '' || cms_len($row[0]) > 400 || cms_len($row[1]) > 3000) return false;
    }
    return true;
}

function cms_h($s) { return htmlspecialchars($s, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'); }

function cms_patch_html($html, $title, $desc, $faq) {
    $e = cms_h($title); $d = cms_h($desc);
    $html = preg_replace_callback('~<title>.*?</title>~su', function () use ($e) { return '<title>' . $e . '</title>'; }, $html, 1);
    $html = preg_replace_callback('~(<meta name="description" content=")[^"]*(")~u', function ($m) use ($d) { return $m[1] . $d . $m[2]; }, $html, 1);
    $html = preg_replace_callback('~(<meta property="og:title" content=")[^"]*(")~u', function ($m) use ($e) { return $m[1] . $e . $m[2]; }, $html, 1);
    $html = preg_replace_callback('~(<meta property="og:description" content=")[^"]*(")~u', function ($m) use ($d) { return $m[1] . $d . $m[2]; }, $html, 1);
    if ($faq !== null) {
        $items = '';
        $ld = [];
        foreach ($faq as $row) {
            $items .= '<details name="faq"><summary>' . cms_h($row[0]) . '</summary><div class="faq-a"><p>' . cms_h($row[1]) . '</p></div></details>';
            $ld[] = ['@type' => 'Question', 'name' => $row[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $row[1]]];
        }
        $block = '<div class="faq" id="faq-list">' . $items . '</div><span hidden id="faq-end"></span>';
        $html = preg_replace_callback('~<div class="faq" id="faq-list">.*?<span hidden id="faq-end"></span>~su', function () use ($block) { return $block; }, $html, 1);
        $json = json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $html = preg_replace_callback('~<script type="application/ld\+json">\{"@context":"https:\\\\?/\\\\?/schema\.org","@type":"FAQPage".*?</script>~su', function () use ($json) { return '<script type="application/ld+json">' . $json . '</script>'; }, $html, 1);
    }
    return $html;
}

function cms_patch_site() {
    $root = dirname(__DIR__);
    $raw = @file_get_contents($root . '/cms-pages.json');
    $m = $raw ? json_decode($raw, true) : null;
    if (!is_array($m) || empty($m['pages'])) return ['pages' => 0, 'written' => 0, 'failed' => 0];
    $c = cms_all_content();
    $fa = (array) $c['fa']; $en = (array) $c['en'];
    $over = ['fa' => $fa, 'en' => $en];
    $pages = 0; $written = 0; $failed = 0;
    foreach ($m['pages'] as $pg) {
        $lang = $pg['lang']; $key = $pg['key'];
        if (!isset($over[$lang]) || !preg_match('~^[a-z0-9/\-]*index\.html$~', $pg['file'])) continue;
        $o = $over[$lang];
        $title = isset($o["meta.$key.title"]) && trim($o["meta.$key.title"]) !== '' ? $o["meta.$key.title"] : $pg['title'];
        $desc = isset($o["meta.$key.desc"]) && trim($o["meta.$key.desc"]) !== '' ? $o["meta.$key.desc"] : $pg['desc'];
        $faq = null;
        if (!empty($pg['faq'])) {
            $faq = isset($m['faq'][$lang]) ? $m['faq'][$lang] : [];
            if (isset($o['faqlist']) && cms_faq_valid($o['faqlist'])) $faq = json_decode($o['faqlist'], true);
        }
        $path = $root . '/' . $pg['file'];
        $html = @file_get_contents($path);
        if ($html === false) continue;
        $pages++;
        $new = cms_patch_html($html, $title, $desc, $faq);
        if ($new === $html || $new === null) continue;
        $tmp = $path . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $new) !== false && @rename($tmp, $path)) $written++;
        else { @unlink($tmp); $failed++; }
    }
    return ['pages' => $pages, 'written' => $written, 'failed' => $failed];
}
