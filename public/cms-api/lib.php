<?php
// Shared helpers for the Payannameh Plus CMS API. Works on PHP 7.4 and newer.

define('CMS_DIR', __DIR__);
define('CMS_UPLOADS', __DIR__ . '/uploads');
define('CMS_DATA', __DIR__ . '/data');
define('CMS_LANGS', ['fa', 'en', 'all']);

function cms_config() {
    static $c = false;
    if ($c === false) {
        $f = CMS_DIR . '/config.local.php';
        $c = is_file($f) ? require $f : null;
    }
    return $c;
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cms_db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cms_config();
    if (!$c) json_out(['error' => 'not_installed'], 503);
    try {
        $pdo = new PDO(
            'mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4',
            $c['db_user'],
            $c['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Exception $e) {
        json_out(['error' => 'db_connect'], 500);
    }
    return $pdo;
}

function cms_schema_sql() {
    return [
        'CREATE TABLE IF NOT EXISTS cms_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            pass_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS cms_content (
            k VARCHAR(120) NOT NULL,
            lang VARCHAR(6) NOT NULL,
            v MEDIUMTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (k, lang)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS cms_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            at DATETIME NOT NULL,
            KEY ip_at (ip, at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    ];
}

function cms_is_https() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    return false;
}

function cms_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('cms_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => cms_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function cms_user() {
    cms_session();
    if (empty($_SESSION['uid'])) return null;
    $st = cms_db()->prepare('SELECT id, username FROM cms_users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    return $u ? $u : null;
}

function cms_csrf() {
    cms_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

// Every write needs a logged-in owner and a matching CSRF token.
function cms_require_auth($write = false) {
    $u = cms_user();
    if (!$u) json_out(['error' => 'auth'], 401);
    if ($write) {
        $sent = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
        if (!hash_equals(cms_csrf(), $sent)) json_out(['error' => 'csrf'], 403);
    }
    return $u;
}

function cms_input() {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function cms_valid_key($k) {
    return is_string($k) && preg_match('/^[A-Za-z0-9_.\-]{1,120}$/', $k);
}

// Keys that start with "site." are written only by the server itself (for example the logo).
function cms_user_editable_key($k) {
    return cms_valid_key($k) && strpos($k, 'site.') !== 0;
}

function cms_len($s) {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

// Rebuild the public snapshot (data/content.json). The site reads this file, so it keeps
// working from the last saved copy even if the database or PHP is unavailable.
function cms_snapshot() {
    $out = ['fa' => new stdClass(), 'en' => new stdClass(), 'all' => new stdClass()];
    $tmp = ['fa' => [], 'en' => [], 'all' => []];
    $rows = cms_db()->query('SELECT k, lang, v FROM cms_content')->fetchAll();
    foreach ($rows as $r) {
        if (isset($tmp[$r['lang']])) $tmp[$r['lang']][$r['k']] = $r['v'];
    }
    foreach ($tmp as $l => $vals) $out[$l] = (object) $vals;
    $json = json_encode(['v' => time(), 'content' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_dir(CMS_DATA)) @mkdir(CMS_DATA, 0755, true);
    $file = CMS_DATA . '/content.json';
    $t = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($t, $json) === false) return false;
    @chmod($t, 0644);
    if (!@rename($t, $file)) {
        @unlink($t);
        return false;
    }
    return true;
}

function cms_all_content() {
    $out = ['fa' => [], 'en' => [], 'all' => []];
    $rows = cms_db()->query('SELECT k, lang, v FROM cms_content')->fetchAll();
    foreach ($rows as $r) {
        if (isset($out[$r['lang']])) $out[$r['lang']][$r['k']] = $r['v'];
    }
    return [
        'fa' => (object) $out['fa'],
        'en' => (object) $out['en'],
        'all' => (object) $out['all'],
    ];
}

function cms_image_ext($bytes) {
    if (!function_exists('finfo_open')) return null;
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_buffer($fi, $bytes);
    finfo_close($fi);
    $map = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) return null;
    if (@getimagesizefromstring($bytes) === false) return null;
    return $map[$mime];
}

// Save a validated logo image and remember its address. Old logo files are cleaned up.
function cms_store_logo($bytes) {
    if (strlen($bytes) > 2 * 1024 * 1024) return ['error' => 'too_big'];
    $ext = cms_image_ext($bytes);
    if (!$ext) return ['error' => 'bad_type'];
    if (!is_dir(CMS_UPLOADS)) @mkdir(CMS_UPLOADS, 0755, true);
    $name = 'logo-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (@file_put_contents(CMS_UPLOADS . '/' . $name, $bytes) === false) return ['error' => 'write_failed'];
    @chmod(CMS_UPLOADS . '/' . $name, 0644);
    $st = cms_db()->prepare('INSERT INTO cms_content (k, lang, v) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $st->execute(['site.logo', 'all', '/cms-api/uploads/' . $name]);
    // keep the newest 3 logo files so a mistake can be undone by restoring a backup
    $files = glob(CMS_UPLOADS . '/logo-*');
    if ($files && count($files) > 3) {
        sort($files);
        foreach (array_slice($files, 0, count($files) - 3) as $old) @unlink($old);
    }
    return ['ok' => true, 'url' => '/cms-api/uploads/' . $name];
}
