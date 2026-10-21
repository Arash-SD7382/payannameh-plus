<?php
// Shared helpers for the Payannameh Plus CMS API. Works on PHP 7.4 and newer.

define('CMS_DIR', __DIR__);
define('CMS_UPLOADS', __DIR__ . '/uploads');
define('CMS_DATA', __DIR__ . '/data');
define('CMS_LANGS', ['fa', 'en', 'all']);
define('CMS_API', true);

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
    try { $pdo->exec(cms_items_schema()); $pdo->exec(cms_hits_schema()); } catch (Exception $e) { /* older installs get the new tables on first use */ }
    return $pdo;
}

function cms_hits_schema() {
    return 'CREATE TABLE IF NOT EXISTS cms_hits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        at DATETIME NOT NULL,
        KEY ip_at (ip, at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
}

// Request-rate limit per IP for the whole API: at most $max requests in $secs seconds.
// Stops scripts that hammer the login, the cron address or any other action.
function cms_rate_limit($max = 40, $secs = 10) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '0';
    try {
        $db = cms_db();
        if (random_int(1, 50) === 1) $db->exec('DELETE FROM cms_hits WHERE at < (NOW() - INTERVAL 1 HOUR)');
        $st = $db->prepare('SELECT COUNT(*) FROM cms_hits WHERE ip = ? AND at > (NOW() - INTERVAL ' . (int) $secs . ' SECOND)');
        $st->execute([$ip]);
        if ((int) $st->fetchColumn() >= $max) {
            header('Retry-After: ' . (int) $secs);
            json_out(['error' => 'rate_limited'], 429);
        }
        $db->prepare('INSERT INTO cms_hits (ip, at) VALUES (?, NOW())')->execute([$ip]);
    } catch (PDOException $e) { /* never block the panel because of the counter itself */ }
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
        cms_items_schema(),
        cms_hits_schema(),
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

// Can this (key, language, value) be stored by the panel? Contact details are shared by both languages
// (language "all") and are checked strictly, because they end up inside links on the public site.
// $v === null means "go back to the default" and is always fine for an allowed key.
function cms_valid_entry($k, $lang, $v) {
    if ($lang === 'fa' || $lang === 'en') {
        if (!cms_user_editable_key($k)) return false;
        if ($k === 'faqlist' && $v !== null && trim((string) $v) !== '') return is_string($v) && cms_faq_valid($v);
        return true;
    }
    if ($lang !== 'all' || !is_string($k) || !preg_match('/^team\.[0-9]{1,2}\.(phone|telegram|whatsapp|bale)$/', $k, $m)) return false;
    if ($v === null || trim((string) $v) === '') return true;
    $re = [
        'phone' => '/^\+?[0-9]{6,15}$/',
        'telegram' => '/^[A-Za-z0-9_]{3,64}$/',
        'whatsapp' => '/^[0-9]{6,15}$/',
        'bale' => '/^\+?[A-Za-z0-9_]{3,64}$/',
    ];
    return is_string($v) && preg_match($re[$m[1]], $v) === 1;
}

// Longest value the panel may store for a key (the FAQ list is one JSON value and needs more room).
function cms_max_len($k) {
    return $k === 'faqlist' ? 60000 : 5000;
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

require __DIR__ . '/patch.php';
require __DIR__ . '/items.php';

/* confirm the logged-in user's own password before sensitive account actions */
function cms_check_pass($uid, $pass) {
    $st = cms_db()->prepare('SELECT pass_hash FROM cms_users WHERE id = ?');
    $st->execute([$uid]);
    $row = $st->fetch();
    if (!$row || !password_verify((string) $pass, $row['pass_hash'])) json_out(['error' => 'bad_old_password'], 400);
}

/* secret token for the scheduled "reapply" call (stored in a PHP file that prints nothing if opened) */
function cms_cron_token($create) {
    $f = CMS_DATA . '/cron-token.php';
    if (is_file($f)) {
        $t = trim(substr((string) file_get_contents($f), strlen('<?php exit; ?>')));
        if (preg_match('/^[a-f0-9]{40}$/', $t)) return $t;
    }
    if (!$create) return '';
    if (!is_dir(CMS_DATA)) @mkdir(CMS_DATA, 0755, true);
    $t = bin2hex(random_bytes(20));
    file_put_contents($f, '<?php exit; ?>' . $t, LOCK_EX);
    return $t;
}
