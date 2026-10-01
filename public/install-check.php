<?php
/**
 * SPIMS installation check — works even when Laravel itself cannot start.
 * Open:  https://your-subdomain/install-check.php?token=<SPIMS_SETUP_TOKEN from .env>
 * Disabled automatically when SPIMS_SETUP_TOKEN is empty (remove the token after installation).
 */
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
$root = dirname(__DIR__);
$env = [];
if (is_readable("$root/.env")) {
    foreach (file("$root/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
            $env[$m[1]] = trim(trim($m[2]), "\"'");
        }
    }
}
$token = $env['SPIMS_SETUP_TOKEN'] ?? '';
if ($token === '' || ! hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit('Not found');
}
$h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$rows = [];
$add = function (string $label, bool $ok, string $detail = '') use (&$rows) { $rows[] = [$label, $ok, $detail]; };

$add('PHP version ≥ 8.2', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION.' (hPanel → Advanced → PHP Configuration)');
foreach (['pdo_mysql', 'mbstring', 'openssl', 'gd', 'fileinfo', 'zip', 'xml', 'ctype', 'tokenizer'] as $ext) {
    $add("PHP extension $ext", extension_loaded($ext));
}
$add('vendor/ folder present', is_file("$root/vendor/autoload.php"), 'Use the release ZIP that includes vendor/');
$add('.env present', $env !== []);
$add('APP_KEY set', str_starts_with($env['APP_KEY'] ?? '', 'base64:'), 'Must look like base64:xxxx…');
$add('APP_DEBUG=false', strtolower($env['APP_DEBUG'] ?? '') === 'false');
$add('APP_URL', ! empty($env['APP_URL']), $env['APP_URL'] ?? '');
$https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$secure = strtolower($env['SESSION_SECURE_COOKIE'] ?? '') === 'true';
$add('Secure cookie matches HTTPS', ! $secure || $https, $secure && ! $https ? 'SESSION_SECURE_COOKIE=true but site opened over http — enable SSL or set it to false, otherwise login will not work' : '');
$add('Root .htaccess present', is_file("$root/.htaccess"));
$add('public/.htaccess present', is_file(__DIR__.'/.htaccess'));
foreach (['storage', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache', 'storage/logs', 'storage/app/private', 'bootstrap/cache'] as $d) {
    $add("Writable: $d", is_dir("$root/$d") && is_writable("$root/$d"), is_dir("$root/$d") ? '' : 'folder missing — create it');
}
try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? 'localhost', $env['DB_PORT'] ?? '3306', $env['DB_DATABASE'] ?? ''),
        $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    $add('Database connection', true, $pdo->query('select version()')->fetchColumn());
    $tables = (int) $pdo->query('select count(*) from information_schema.tables where table_schema = database()')->fetchColumn();
    $add('Tables imported', $tables >= 30, "$tables tables (import database/sql/spims_install.sql if 0)");
    if ($tables) {
        $users = (int) $pdo->query('select count(*) from users')->fetchColumn();
        $add('Users', true, $users ? "$users user(s)" : 'none yet — open /setup to create the first Super Admin');
    }
} catch (Throwable $e) {
    $add('Database connection', false, $e->getMessage());
}
$logs = glob("$root/storage/logs/*.log") ?: [];
rsort($logs);
$tail = '';
if ($logs) {
    $lines = file($logs[0]);
    $tail = implode('', array_slice($lines, -40));
}
?><!doctype html><html><head><meta charset="utf-8"><title>SPIMS install check</title>
<style>body{font-family:system-ui,sans-serif;max-width:900px;margin:30px auto;color:#1a2744}td{padding:6px 10px;border-bottom:1px solid #e3e8f0}.ok{color:#15803d}.bad{color:#b91c1c;font-weight:700}pre{background:#0f1f3d;color:#e2e8f0;padding:12px;overflow:auto;font-size:12px;white-space:pre-wrap}</style></head><body>
<h2>SPIMS installation check</h2><table>
<?php foreach ($rows as [$l, $ok, $d]): ?><tr><td class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✔' : '✘' ?></td><td><?= $h($l) ?></td><td><?= $h($d) ?></td></tr><?php endforeach; ?>
</table>
<?php if ($tail): ?><h3>Latest log (<?= $h(basename($logs[0])) ?>)</h3><pre><?= $h($tail) ?></pre><?php endif; ?>
<p><strong>Remove SPIMS_SETUP_TOKEN from .env after installation</strong> — this page then stops working.</p>
</body></html>
