<?php
// Payannameh Plus CMS API. One entry point: index.php?a=<action>
require __DIR__ . '/lib.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$a = isset($_GET['a']) ? $_GET['a'] : '';
$method = $_SERVER['REQUEST_METHOD'];

function need_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method'], 405);
}

switch ($a) {

    case 'me': {
        $u = cms_user();
        if (!$u) json_out(['auth' => false]);
        json_out(['auth' => true, 'user' => $u['username'], 'csrf' => cms_csrf()]);
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
        json_out(['auth' => true, 'user' => $username, 'csrf' => $_SESSION['csrf']]);
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
                if (!cms_user_editable_key($k) || !in_array($lang, ['fa', 'en'], true)) continue;
                $v = array_key_exists('v', $c) ? $c['v'] : null;
                if ($v === null || trim((string) $v) === '') {
                    $del->execute([$k, $lang]);
                } else {
                    $v = (string) $v;
                    if (cms_len($v) > 5000) continue;
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
        json_out(['ok' => true, 'saved' => $n, 'snapshot' => $snap]);
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

    case 'backup': {
        cms_require_auth(false);
        $data = [
            'app' => 'payannameh-plus-cms',
            'version' => 1,
            'exported_at' => gmdate('c'),
            'content' => cms_all_content(),
        ];
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
            $db->exec("DELETE FROM cms_content WHERE lang IN ('fa','en')");
            foreach (['fa', 'en'] as $l) {
                if (empty($in['content'][$l]) || !is_array($in['content'][$l])) continue;
                foreach ($in['content'][$l] as $k => $v) {
                    if (!cms_user_editable_key($k) || !is_string($v) || trim($v) === '' || cms_len($v) > 5000) continue;
                    $up->execute([$k, $l, $v]);
                    $n++;
                }
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            json_out(['error' => 'restore_failed'], 500);
        }
        if (!empty($in['logo']['data'])) {
            $bytes = base64_decode($in['logo']['data'], true);
            if ($bytes !== false) cms_store_logo($bytes);
        }
        cms_snapshot();
        json_out(['ok' => true, 'restored' => $n]);
    }

    default:
        json_out(['error' => 'unknown_action'], 404);
}
