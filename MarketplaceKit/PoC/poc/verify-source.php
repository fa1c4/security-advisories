<?php
$root = '/opt/marketplacekit';
$checks = [
    [
        'file' => 'routes/web.php',
        'pattern' => "Route::get('payments/{id}/unlink', 'BankAccountController@unlink')->name('payments.unlink')",
        'label' => 'state-changing unlink route is exposed as GET',
    ],
    [
        'file' => 'app/Models/PaymentProvider.php',
        'pattern' => "hasOne('App\\Models\\PaymentGateway', 'name', 'key')",
        'label' => 'PaymentProvider::identifier relation is not owner-scoped',
    ],
    [
        'file' => 'app/Http/Controllers/Account/BankAccountController.php',
        'pattern' => "PaymentProvider::find(\$provider)",
        'label' => 'unlink resolves provider by attacker-controlled id',
    ],
    [
        'file' => 'app/Http/Controllers/Account/BankAccountController.php',
        'pattern' => "PaymentGateway::find(\$provider->identifier->id)",
        'label' => 'unlink dereferences provider identifier without ownership check',
    ],
    [
        'file' => 'app/Http/Controllers/Account/BankAccountController.php',
        'pattern' => "\$gateway->delete()",
        'label' => 'unlink deletes the resolved gateway',
    ],
    [
        'file' => 'app/Models/PaymentGateway.php',
        'pattern' => "'user_id'",
        'label' => 'PaymentGateway has user_id ownership field',
    ],
];

foreach ($checks as $check) {
    $path = $root . '/' . $check['file'];
    if (!is_file($path)) {
        fwrite(STDERR, "[!] Missing source file: {$check['file']}\n");
        exit(1);
    }
    $source = file_get_contents($path);
    if (strpos($source, $check['pattern']) === false) {
        fwrite(STDERR, "[!] Source check failed: {$check['label']}\n");
        fwrite(STDERR, "    File: {$check['file']}\n");
        fwrite(STDERR, "    Pattern: {$check['pattern']}\n");
        exit(1);
    }
    echo "[+] Source check passed: {$check['label']}\n";
}

echo "[+] Source-level vulnerable pattern confirmed.\n";
