<?php
// Payannameh Plus CMS API. One entry point: index.php?a=<action>
require __DIR__ . '/lib.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

cms_rate_limit();

$a = isset($_GET['a']) ? $_GET['a'] : '';
$method = $_SERVER['REQUEST_METHOD'];

function need_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method'], 405);
}

switch ($a) {

    case 'me': {
        $u = cms_user();
        if (!$u) json_out(['auth' => false]);
        json_out(['auth' => true, 'user' => $u['username'], 'title' => (string) $u['title'], 'csrf' => cms_csrf()]);
    }

    case 'login': {
        need_post();
        $in = cms_input();
        $username = isset($in['username']) ? trim((string) $in['username']) : '';
        $password = isset($in['password']) ? (string) $in['password'] : '';
        $db = cms_db();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '0';
        $db->exec("DELETE FROM cms_attempts WHERE at < (NOW() - INTERVAL 1 DAY)");
        $st = $db->prepare('SELECT COUNT(*) FROM cms_attempts WHERE ip = ? AND at > (NOW() - INTERVAL 10 MINUTE)');
        $st->execute([$ip]);
        if ((int) $st->fetchColumn() >= 8) json_out(['error' => 'too_many'], 429);

        $st = $db->prepare('SELECT id, pass_hash FROM cms_users WHERE username = ?');
        $st->execute([$username]);
        $row = $st->fetch();
        // run a hash check even for unknown users so the response time is the same
        $hash = $row ? $row['pass_hash'] : '$2y$10$0ORTRUJib7nNetEirERU4.W3b.xzX5i8WjTOHtRmMQRL.JfYafUh2';
        $ok = password_verify($password, $hash) && $row;
        if (!$ok) {
            $db->prepare('INSERT INTO cms_attempts (ip, at) VALUES (?, NOW())')->execute([$ip]);
            json_out(['error' => 'bad_login'], 401);
        }
        $db->prepare('DELETE FROM cms_attempts WHERE ip = ?')->execute([$ip]);
        cms_session();
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $row['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        $me2 = cms_user();
        json_out(['auth' => true, 'user' => $username, 'title' => $me2 ? (string) $me2['title'] : '', 'csrf' => $_SESSION['csrf']]);
    }

    case 'logout': {
        need_post();
        cms_session();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        json_out(['ok' => true]);
    }

    case 'content': {
        cms_require_auth(false);
        json_out(['content' => cms_all_content()]);
    }

    case 'save': {
        need_post();
        cms_require_auth(true);
        $in = cms_input();
        $changes = isset($in['changes']) && is_array($in['changes']) ? $in['changes'] : [];
        if (count($changes) > 600) json_out(['error' => 'too_many_changes'], 400);
        $db = cms_db();
        $up = $db->prepare('INSERT INTO cms_content (k, lang, v) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
        $del = $db->prepare('DELETE FROM cms_content WHERE k = ? AND lang = ?');
        $n = 0;
        $db->beginTransaction();
        try {
            foreach ($changes as $c) {
                $k = isset($c['k']) ? $c['k'] : null;
                $lang = isset($c['lang']) ? $c['lang'] : null;
                $v = array_key_exists('v', $c) ? $c['v'] : null;
                if (!cms_valid_entry($k, $lang, $v)) continue;
                if ($v === null || trim((string) $v) === '') {
                    $del->execute([$k, $lang]);
                } else {
                    $v = (string) $v;
                    if (cms_len($v) > cms_max_len($k)) continue;
                    $up->execute([$k, $lang, $v]);
                }
                $n++;
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            json_out(['error' => 'save_failed'], 500);
        }
        $snap = cms_snapshot();
        $patch = cms_patch_site();
        json_out(['ok' => true, 'saved' => $n, 'snapshot' => $snap, 'patch' => $patch]);
    }

    case 'logo': {
        need_post();
        cms_require_auth(true);
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) json_out(['error' => 'no_file'], 400);
        $bytes = file_get_contents($_FILES['file']['tmp_name']);
        $r = cms_store_logo($bytes);
        if (isset($r['error'])) json_out(['error' => $r['error']], 400);
        cms_snapshot();
        json_out($r);
    }

    case 'logo_reset': {
        need_post();
        cms_require_auth(true);
        cms_db()->prepare("DELETE FROM cms_content WHERE k = 'site.logo' AND lang = 'all'")->execute();
        cms_snapshot();
        json_out(['ok' => true]);
    }

    case 'password': {
        need_post();
        $u = cms_require_auth(true);
        $in = cms_input();
        $old = isset($in['old']) ? (string) $in['old'] : '';
        $new = isset($in['new']) ? (string) $in['new'] : '';
        if (cms_len($new) < 8) json_out(['error' => 'weak_password'], 400);
        $st = cms_db()->prepare('SELECT pass_hash FROM cms_users WHERE id = ?');
        $st->execute([$u['id']]);
        $row = $st->fetch();
        if (!$row || !password_verify($old, $row['pass_hash'])) json_out(['error' => 'bad_old_password'], 400);
        cms_db()->prepare('UPDATE cms_users SET pass_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        json_out(['ok' => true]);
    }

    case 'users': {
        $u = cms_require_auth(false);
        $rows = cms_db()->query('SELECT id, username, title, created_at FROM cms_users ORDER BY id')->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[] = ['id' => (int) $r['id'], 'username' => $r['username'], 'title' => (string) $r['title'], 'created' => $r['created_at'], 'self' => (int) $r['id'] === (int) $u['id']];
        json_out(['users' => $out]);
    }

    case 'user_title': {
        // the label shown under a person's name (for example "site designer"); cosmetic, so no password needed
        need_post();
        cms_require_auth(true);
        $in = cms_input();
        $id = isset($in['id']) ? (int) $in['id'] : 0;
        $title = isset($in['title']) && is_string($in['title']) ? trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $in['title'])) : '';
        if (cms_len($title) > 40) json_out(['error' => 'bad_title_user'], 400);
        $st = cms_db()->prepare('SELECT id FROM cms_users WHERE id = ?');
        $st->execute([$id]);
        if (!$st->fetch()) json_out(['error' => 'not_found'], 404);
        cms_db()->prepare('UPDATE cms_users SET title = ? WHERE id = ?')->execute([$title, $id]);
        json_out(['ok' => true, 'title' => $title]);
    }

    case 'username': {
        need_post();
        $u = cms_require_auth(true);
        $in = cms_input();
        $pass = isset($in['password']) ? (string) $in['password'] : '';
        $name = isset($in['username']) ? trim((string) $in['username']) : '';
        if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $name)) json_out(['error' => 'bad_username'], 400);
        cms_check_pass($u['id'], $pass);
        $st = cms_db()->prepare('SELECT id FROM cms_users WHERE username = ? AND id <> ?');
        $st->execute([$name, $u['id']]);
        if ($st->fetch()) json_out(['error' => 'username_taken'], 409);
        cms_db()->prepare('UPDATE cms_users SET username = ? WHERE id = ?')->execute([$name, $u['id']]);
        json_out(['ok' => true, 'user' => $name]);
    }

    case 'user_add': {
        need_post();
        $u = cms_require_auth(true);
        $in = cms_input();
        $pass = isset($in['password']) ? (string) $in['password'] : '';
        $name = isset($in['username']) ? trim((string) $in['username']) : '';
        $new = isset($in['new']) ? (string) $in['new'] : '';
        if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $name)) json_out(['error' => 'bad_username'], 400);
        if (cms_len($new) < 8) json_out(['error' => 'weak_password'], 400);
        cms_check_pass($u['id'], $pass);
        $st = cms_db()->prepare('SELECT id FROM cms_users WHERE username = ?');
        $st->execute([$name]);
        if ($st->fetch()) json_out(['error' => 'username_taken'], 409);
        cms_db()->prepare('INSERT INTO cms_users (username, pass_hash) VALUES (?, ?)')->execute([$name, password_hash($new, PASSWORD_DEFAULT)]);
        json_out(['ok' => true]);
    }

    case 'user_setpass': {
        need_post();
        $u = cms_require_auth(true);
        $in = cms_input();
        $pass = isset($in['password']) ? (string) $in['password'] : '';
        $new = isset($in['new']) ? (string) $in['new'] : '';
        $id = isset($in['id']) ? (int) $in['id'] : 0;
        if (cms_len($new) < 8) json_out(['error' => 'weak_password'], 400);
        cms_check_pass($u['id'], $pass);
        $st = cms_db()->prepare('UPDATE cms_users SET pass_hash = ? WHERE id = ?');
        $st->execute([password_hash($new, PASSWORD_DEFAULT), $id]);
        $chk = cms_db()->prepare('SELECT id FROM cms_users WHERE id = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) json_out(['error' => 'not_found'], 404);
        json_out(['ok' => true]);
    }

    case 'user_delete': {
        need_post();
        $u = cms_require_auth(true);
        $in = cms_input();
        $pass = isset($in['password']) ? (string) $in['password'] : '';
        $id = isset($in['id']) ? (int) $in['id'] : 0;
        if ($id === (int) $u['id']) json_out(['error' => 'cannot_delete_self'], 400);
        cms_check_pass($u['id'], $pass);
        $st = cms_db()->prepare('DELETE FROM cms_users WHERE id = ?');
        $st->execute([$id]);
        if (!$st->rowCount()) json_out(['error' => 'not_found'], 404);
        json_out(['ok' => true]);
    }

    case 'backup': {
        cms_require_auth(false);
        cms_mark_set('backup');
        $data = [
            'app' => 'payannameh-plus-cms',
            'version' => 2,
            'exported_at' => gmdate('c'),
            'content' => cms_all_content(),
            'items' => cms_db()->query('SELECT type, lang, slug, data, body, deleted FROM cms_items')->fetchAll(),
            'images' => new stdClass(),
        ];
        $imgs = [];
        foreach ((array) glob(CMS_UPLOADS . '/img-*') as $f) {
            if (is_file($f) && filesize($f) <= 3 * 1024 * 1024) $imgs[basename($f)] = base64_encode(file_get_contents($f));
        }
        if ($imgs) $data['images'] = $imgs;
        $st = cms_db()->query("SELECT v FROM cms_content WHERE k = 'site.logo' AND lang = 'all'");
        $logo = $st->fetchColumn();
        if ($logo && strpos($logo, '/cms-api/uploads/') === 0) {
            $f = CMS_UPLOADS . '/' . basename($logo);
            if (is_file($f)) $data['logo'] = ['name' => basename($logo), 'data' => base64_encode(file_get_contents($f))];
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="payannameh-backup-' . gmdate('Y-m-d') . '.json"');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    case 'restore': {
        need_post();
        cms_require_auth(true);
        $in = cms_input();
        if (!isset($in['app']) || $in['app'] !== 'payannameh-plus-cms' || !isset($in['content']) || !is_array($in['content'])) {
            json_out(['error' => 'bad_backup'], 400);
        }
        $db = cms_db();
        $up = $db->prepare('INSERT INTO cms_content (k, lang, v) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
        $n = 0;
        $db->beginTransaction();
        try {
            $db->exec("DELETE FROM cms_content WHERE lang IN ('fa','en') OR (lang = 'all' AND k LIKE 'team.%')");
            if (isset($in['items']) && is_array($in['items'])) {
                $db->exec('DELETE FROM cms_items');
                $ins = $db->prepare('INSERT INTO cms_items (type, lang, slug, data, body, deleted) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($in['items'] as $it) {
                    if (!is_array($it) || !isset($it['type'], $it['lang'], $it['slug'], $it['data'], $it['body'])) continue;
                    if (!in_array($it['type'], ['project', 'service'], true) || !in_array($it['lang'], ['fa', 'en'], true)) continue;
                    if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,78}$/', $it['slug']) || !is_string($it['data']) || !is_string($it['body'])) continue;
                    $ins->execute([$it['type'], $it['lang'], $it['slug'], $it['data'], $it['body'], empty($it['deleted']) ? 0 : 1]);
                }
            }
            foreach (['fa', 'en', 'all'] as $l) {
                if (empty($in['content'][$l]) || !is_array($in['content'][$l])) continue;
                foreach ($in['content'][$l] as $k => $v) {
                    if (!is_string($v) || trim($v) === '' || cms_len($v) > cms_max_len($k) || !cms_valid_entry($k, $l, $v)) continue;
                    $up->execute([$k, $l, $v]);
                    $n++;
                }
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            json_out(['error' => 'restore_failed'], 500);
        }
        if (!empty($in['images']) && is_array($in['images'])) {
            foreach ($in['images'] as $name => $b64) {
                if (!is_string($name) || !preg_match('/^img-[a-f0-9]{16}\.(png|jpg|webp)$/', $name) || !is_string($b64)) continue;
                $bytes = base64_decode($b64, true);
                if ($bytes === false || strlen($bytes) > 3 * 1024 * 1024 || !cms_image_ext($bytes)) continue;
                if (!is_dir(CMS_UPLOADS)) @mkdir(CMS_UPLOADS, 0755, true);
                @file_put_contents(CMS_UPLOADS . '/' . $name, $bytes);
            }
        }
        if (!empty($in['logo']['data'])) {
            $bytes = base64_decode($in['logo']['data'], true);
            if ($bytes !== false) cms_store_logo($bytes);
        }
        cms_snapshot();
        $patch = cms_patch_site();
        $gen = cms_generate_items();
        json_out(['ok' => true, 'restored' => $n, 'patch' => $patch, 'items' => $gen]);
    }

    case 'reapply': {
        need_post();
        cms_require_auth(true);
        json_out(['ok' => true, 'patch' => cms_patch_site(), 'items' => cms_generate_items()]);
    }

    case 'cron': {
        // called by a scheduled job on the host; no login, protected by a secret token
        $t = cms_cron_token(false);
        $sent = isset($_GET['t']) ? (string) $_GET['t'] : '';
        if ($t === '' || !hash_equals($t, $sent)) json_out(['error' => 'forbidden'], 403);
        cms_mark_set('cron');
        json_out(['ok' => true, 'patch' => cms_patch_site(), 'items' => cms_generate_items()]);
    }

    case 'dashboard': {
        cms_require_auth(false);
        $db = cms_db();
        $out = ['projects' => [], 'services' => [], 'categories' => [], 'months' => [], 'texts' => [], 'recent' => [], 'recent_items' => []];
        $rows = cms_item_rows();
        foreach (['fa', 'en'] as $l) {
            $out['projects'][$l] = count(cms_effective('project', $l, $rows));
            $out['services'][$l] = count(cms_effective('service', $l, $rows));
        }
        $plist = cms_effective('project', $out['projects']['fa'] ? 'fa' : 'en', $rows);
        foreach ($plist as $it) {
            $c = isset($it['data']['category']) ? $it['data']['category'] : '';
            $out['categories'][$c] = isset($out['categories'][$c]) ? $out['categories'][$c] + 1 : 1;
        }
        $m = [];
        for ($i = 11; $i >= 0; $i--) $m[gmdate('Y-m', strtotime('first day of -' . $i . ' month'))] = 0;
        foreach ($plist as $it) {
            $k = substr($it['data']['date'], 0, 7);
            if (isset($m[$k])) $m[$k]++;
        }
        foreach ($m as $k => $n) $out['months'][] = ['m' => $k, 'n' => $n];
        $st = $db->query("SELECT lang, COUNT(*) c FROM cms_content WHERE k <> 'site.logo' GROUP BY lang");
        foreach ($st->fetchAll() as $r) $out['texts'][$r['lang']] = (int) $r['c'];
        $out['users'] = (int) $db->query('SELECT COUNT(*) FROM cms_users')->fetchColumn();
        $st = $db->query('SELECT k, lang, TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS ago FROM cms_content ORDER BY updated_at DESC LIMIT 8');
        foreach ($st->fetchAll() as $r) $out['recent'][] = ['k' => $r['k'], 'lang' => $r['lang'], 'ago' => max(0, (int) $r['ago'])];
        $st = $db->query('SELECT type, lang, slug, data, deleted, TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS ago FROM cms_items ORDER BY updated_at DESC LIMIT 6');
        foreach ($st->fetchAll() as $r) {
            $d = json_decode($r['data'], true);
            $out['recent_items'][] = ['type' => $r['type'], 'lang' => $r['lang'], 'slug' => $r['slug'], 'title' => is_array($d) && isset($d['title']) ? $d['title'] : $r['slug'], 'deleted' => (int) $r['deleted'], 'ago' => max(0, (int) $r['ago'])];
        }
        $n = 0; $bytes = 0;
        foreach ((array) glob(CMS_UPLOADS . '/img-*') as $f) if (is_file($f)) { $n++; $bytes += filesize($f); }
        $out['uploads'] = ['count' => $n, 'bytes' => $bytes];
        $out['cron_ago'] = cms_mark_ago('cron');
        $out['backup_ago'] = cms_mark_ago('backup');
        $out['env'] = [
            'php' => PHP_VERSION,
            'gd' => extension_loaded('gd'), 'mbstring' => extension_loaded('mbstring'), 'fileinfo' => extension_loaded('fileinfo'),
            'data_writable' => is_dir(CMS_DATA) && is_writable(CMS_DATA),
            'uploads_writable' => is_dir(CMS_UPLOADS) && is_writable(CMS_UPLOADS),
            'snapshot' => is_file(CMS_DATA . '/content.json'),
            'upload_max' => (string) ini_get('upload_max_filesize'),
        ];
        json_out($out);
    }

    case 'cron_info': {
        cms_require_auth(false);
        $t = cms_cron_token(true);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        json_out(['url' => $scheme . '://' . $_SERVER['HTTP_HOST'] . '/cms-api/?a=cron&t=' . $t]);
    }

    case 'items': {
        cms_require_auth(false);
        $rows = cms_item_rows();
        $b = cms_baked();
        $out = ['categories' => $b['categories'], 'project' => ['fa' => cms_effective('project', 'fa', $rows), 'en' => cms_effective('project', 'en', $rows)],
            'service' => ['fa' => cms_effective('service', 'fa', $rows), 'en' => cms_effective('service', 'en', $rows)], 'removed' => []];
        foreach ($b['projects'] as $e) if (!empty($rows['project'][$e['lang']][$e['key']]['deleted'])) $out['removed'][] = ['type' => 'project', 'lang' => $e['lang'], 'key' => $e['key'], 'title' => $e['data']['title']];
        foreach ($b['services'] as $e) if (!empty($rows['service'][$e['lang']][$e['key']]['deleted'])) $out['removed'][] = ['type' => 'service', 'lang' => $e['lang'], 'key' => $e['key'], 'title' => $e['data']['title']];
        json_out($out);
    }

    case 'item_save': {
        need_post();
        cms_require_auth(true);
        $in = cms_input();
        $type = isset($in['type']) ? $in['type'] : '';
        $lang = isset($in['lang']) ? $in['lang'] : '';
        $key = isset($in['key']) && is_string($in['key']) ? $in['key'] : '';
        if (!in_array($type, ['project', 'service'], true) || !in_array($lang, ['fa', 'en'], true)) json_out(['error' => 'bad_item'], 400);
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,78}$/', $key)) json_out(['error' => 'bad_slug'], 400);
        list($data, $err) = cms_item_clean($type, isset($in['data']) && is_array($in['data']) ? $in['data'] : []);
        if ($err) json_out(['error' => $err], 400);
        $body = isset($in['body']) && is_string($in['body']) ? $in['body'] : '';
        if (cms_len($body) > 100000) json_out(['error' => 'bad_body'], 400);
        $exists = cms_effective($type, $lang);
        $isNew = !empty($in['isNew']);
        $found = false;
        foreach ($exists as $x) if ($x['key'] === $key) $found = true;
        if ($isNew && $found) json_out(['error' => 'slug_taken'], 409);
        if (!$isNew && !$found) json_out(['error' => 'not_found'], 404);
        $st = cms_db()->prepare('INSERT INTO cms_items (type, lang, slug, data, body, deleted) VALUES (?, ?, ?, ?, ?, 0) ON DUPLICATE KEY UPDATE data = VALUES(data), body = VALUES(body), deleted = 0');
        $st->execute([$type, $lang, $key, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $body]);
        $gen = cms_generate_items();
        json_out(['ok' => true, 'items' => $gen]);
    }

    case 'item_delete': {
        need_post();
        cms_require_auth(true);
        $in = cms_input();
        $type = isset($in['type']) ? $in['type'] : ''; $lang = isset($in['lang']) ? $in['lang'] : ''; $key = isset($in['key']) && is_string($in['key']) ? $in['key'] : '';
        if (!in_array($type, ['project', 'service'], true) || !in_array($lang, ['fa', 'en'], true) || !preg_match('/^[a-z0-9][a-z0-9\-]{1,78}$/', $key)) json_out(['error' => 'bad_item'], 400);
        $baked = false;
        foreach (cms_baked()[$type . 's'] as $e) if ($e['lang'] === $lang && $e['key'] === $key) $baked = true;
        if ($baked) {
            $st = cms_db()->prepare("INSERT INTO cms_items (type, lang, slug, data, body, deleted) VALUES (?, ?, ?, '{}', '', 1) ON DUPLICATE KEY UPDATE deleted = 1");
            $st->execute([$type, $lang, $key]);
        } else {
            $st = cms_db()->prepare('DELETE FROM cms_items WHERE type = ? AND lang = ? AND slug = ?');
            $st->execute([$type, $lang, $key]);
        }
        json_out(['ok' => true, 'items' => cms_generate_items()]);
    }

    case 'item_reset': {
        need_post();
        cms_require_auth(true);
        $in = cms_input();
        $type = isset($in['type']) ? $in['type'] : ''; $lang = isset($in['lang']) ? $in['lang'] : ''; $key = isset($in['key']) && is_string($in['key']) ? $in['key'] : '';
        if (!in_array($type, ['project', 'service'], true) || !in_array($lang, ['fa', 'en'], true) || !preg_match('/^[a-z0-9][a-z0-9\-]{1,78}$/', $key)) json_out(['error' => 'bad_item'], 400);
        $st = cms_db()->prepare('DELETE FROM cms_items WHERE type = ? AND lang = ? AND slug = ?');
        $st->execute([$type, $lang, $key]);
        json_out(['ok' => true, 'items' => cms_generate_items()]);
    }

    case 'image': {
        need_post();
        cms_require_auth(true);
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) json_out(['error' => 'no_file'], 400);
        $r = cms_store_image(file_get_contents($_FILES['file']['tmp_name']));
        if (isset($r['error'])) json_out($r, 400);
        json_out($r);
    }

    case 'preview': {
        need_post();
        cms_require_auth(true);
        $in = cms_input();
        $md = isset($in['body']) && is_string($in['body']) ? $in['body'] : '';
        list($html) = cms_md(cms_len($md) > 100000 ? '' : $md);
        json_out(['html' => $html]);
    }

    default:
        json_out(['error' => 'unknown_action'], 404);
}
