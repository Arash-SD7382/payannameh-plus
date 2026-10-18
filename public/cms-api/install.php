<?php
// One-time installer. Open /cms-api/install.php once, fill the form, then delete this file.
// It refuses to run again once config.local.php exists.
require __DIR__ . '/lib.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$done = false;
$error = '';
$locked = is_file(CMS_DIR . '/config.local.php');
$v = ['db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'username' => 'admin'];

if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['db_host', 'db_name', 'db_user', 'username'] as $f) $v[$f] = trim(isset($_POST[$f]) ? $_POST[$f] : '');
    $dbPass = isset($_POST['db_pass']) ? (string) $_POST['db_pass'] : '';
    $pass1 = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $pass2 = isset($_POST['password2']) ? (string) $_POST['password2'] : '';

    if ($v['db_host'] === '' || $v['db_name'] === '' || $v['db_user'] === '') {
        $error = 'اطلاعات دیتابیس را کامل وارد کنید.';
    } elseif (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $v['username'])) {
        $error = 'نام کاربری فقط حروف انگلیسی، عدد و علامت‌های _ . - باشد (حداقل ۳ حرف).';
    } elseif (strlen($pass1) < 8) {
        $error = 'رمز عبور باید حداقل ۸ نویسه باشد.';
    } elseif ($pass1 !== $pass2) {
        $error = 'رمز عبور و تکرار آن یکسان نیست.';
    } else {
        try {
            // try to create the database first (works on a local computer; on most hosts it already exists)
            try {
                $root = new PDO('mysql:host=' . $v['db_host'] . ';charset=utf8mb4', $v['db_user'], $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $root->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $v['db_name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (Exception $e) { /* ignore */ }

            $pdo = new PDO('mysql:host=' . $v['db_host'] . ';dbname=' . $v['db_name'] . ';charset=utf8mb4', $v['db_user'], $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            foreach (cms_schema_sql() as $sql) $pdo->exec($sql);

            $st = $pdo->prepare('SELECT COUNT(*) FROM cms_users');
            $st->execute();
            if ((int) $st->fetchColumn() > 0) {
                $error = 'این دیتابیس قبلاً نصب شده است. اگر می‌خواهید دوباره نصب کنید، جدول‌های cms_ را حذف کنید.';
            } else {
                $pdo->prepare('INSERT INTO cms_users (username, pass_hash) VALUES (?, ?)')->execute([$v['username'], password_hash($pass1, PASSWORD_DEFAULT)]);
                $cfg = "<?php\nreturn " . var_export(['db_host' => $v['db_host'], 'db_name' => $v['db_name'], 'db_user' => $v['db_user'], 'db_pass' => $dbPass], true) . ";\n";
                if (@file_put_contents(CMS_DIR . '/config.local.php', $cfg) === false) {
                    $error = 'نوشتن فایل config.local.php ممکن نشد. دسترسی نوشتن پوشه cms-api را بررسی کنید.';
                } else {
                    @chmod(CMS_DIR . '/config.local.php', 0640);
                    if (!is_dir(CMS_DATA)) @mkdir(CMS_DATA, 0755, true);
                    cms_snapshot();
                    $done = true;
                    $locked = true;
                }
            }
        } catch (Exception $e) {
            $error = 'اتصال به دیتابیس برقرار نشد. نام دیتابیس، نام کاربری و رمز را دوباره بررسی کنید.';
        }
    }
}
?><!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>نصب پنل</title>
<style>
body{font-family:Tahoma,system-ui,sans-serif;background:#f6f1e7;color:#2b2620;margin:0;padding:24px}
.box{max-width:520px;margin:0 auto;background:#fffdf8;border:1px solid #e3dac6;border-radius:14px;padding:24px}
h1{font-size:1.3rem;margin:0 0 .4rem}p{line-height:1.9;margin:.4rem 0}
label{display:block;margin:.9rem 0 .25rem;font-weight:600;font-size:.95rem}
input{width:100%;box-sizing:border-box;padding:.65rem .8rem;border:1px solid #d3c8b0;border-radius:8px;font:inherit;background:#fff;direction:ltr;text-align:left}
button{margin-top:1.2rem;padding:.75rem 1.2rem;border:0;border-radius:8px;background:#2b4a7a;color:#fff;font:inherit;cursor:pointer}
.err{background:#fde8e6;color:#8a1c12;padding:.7rem .9rem;border-radius:8px;margin:.8rem 0}
.ok{background:#e4f4e6;color:#14532d;padding:.7rem .9rem;border-radius:8px;margin:.8rem 0}
.warn{background:#fff4d6;color:#6a4a00;padding:.7rem .9rem;border-radius:8px;margin:.8rem 0}
a{color:#2b4a7a}
</style></head><body><div class="box">
<h1>نصب پنل مدیریت</h1>
<?php if ($done): ?>
  <div class="ok">نصب انجام شد.</div>
  <div class="warn"><strong>مهم:</strong> همین حالا فایل <bdi>cms-api/install.php</bdi> را از هاست حذف کنید.</div>
  <p><a href="/panel/">ورود به پنل مدیریت</a></p>
<?php elseif ($locked): ?>
  <div class="warn">پنل قبلاً نصب شده است. برای امنیت، فایل <bdi>install.php</bdi> را از هاست حذف کنید.</div>
<?php else: ?>
  <p>اطلاعات دیتابیس را که در هاست ساخته‌اید و نام کاربری و رمز مدیر سایت را وارد کنید.</p>
  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label for="db_host">میزبان دیتابیس (معمولاً localhost)</label>
    <input id="db_host" name="db_host" value="<?= h($v['db_host']) ?>" required>
    <label for="db_name">نام دیتابیس</label>
    <input id="db_name" name="db_name" value="<?= h($v['db_name']) ?>" required>
    <label for="db_user">نام کاربری دیتابیس</label>
    <input id="db_user" name="db_user" value="<?= h($v['db_user']) ?>" required>
    <label for="db_pass">رمز دیتابیس</label>
    <input id="db_pass" name="db_pass" type="password">
    <hr style="border:0;border-top:1px solid #e3dac6;margin:1.4rem 0 .2rem">
    <label for="username">نام کاربری مدیر سایت</label>
    <input id="username" name="username" value="<?= h($v['username']) ?>" required>
    <label for="password">رمز عبور مدیر (حداقل ۸ نویسه)</label>
    <input id="password" name="password" type="password" required minlength="8">
    <label for="password2">تکرار رمز عبور</label>
    <input id="password2" name="password2" type="password" required minlength="8">
    <button type="submit">نصب</button>
  </form>
<?php endif; ?>
</div></body></html>
