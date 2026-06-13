<?php
/**
 * Source verifier for Croogo FileManager arbitrary file write/path traversal.
 *
 * This does not exploit a remote target. It verifies that the checked-out
 * Croogo commit contains the vulnerable source patterns described in the
 * advisory, before the local logic-level reproduction is executed.
 *
 * The checks intentionally combine stable substring checks with lightweight
 * regexes. Croogo source formatting differs between tags/commits, so overly
 * strict function-scoped regexes can produce false negatives.
 */

$root = '/opt/croogo';
$files = [
    'bootstrap' => $root . '/FileManager/config/bootstrap.php',
    'utility' => $root . '/FileManager/src/Utility/FileManager.php',
    'controller' => $root . '/FileManager/src/Controller/Admin/FileManagerController.php',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "[!] Missing expected source file for {$label}: {$path}\n");
        exit(1);
    }
}

$bootstrap = file_get_contents($files['bootstrap']);
$utility = file_get_contents($files['utility']);
$controller = file_get_contents($files['controller']);

function ok(string $message, bool $condition): void
{
    if (!$condition) {
        fwrite(STDERR, "[!] Source check failed: {$message}\n");
        exit(1);
    }
    echo "[+] {$message}\n";
}

ok(
    'editablePaths is configured to WWW_ROOT/assets',
    preg_match("/['\"]editablePaths['\"]\s*=>\s*\[\s*WWW_ROOT\s*\.\s*['\"]assets['\"]/s", $bootstrap) === 1
        || (str_contains($bootstrap, "'editablePaths'") && str_contains($bootstrap, "WWW_ROOT . 'assets'"))
);

ok(
    'isEditable() incorrectly uses Configure::check() instead of Configure::read()',
    str_contains($utility, 'function isEditable')
        && str_contains($utility, "Configure::check('FileManager.editablePaths')")
);

ok(
    '_isWithinPath() uses realpath($referencePath) for prefix validation',
    str_contains($utility, 'function _isWithinPath')
        && str_contains($utility, 'realpath($referencePath)')
        && str_contains($utility, 'preg_match($regex, $path)')
);

ok(
    'createFile() calls isEditable($path)',
    str_contains($controller, 'function createFile')
        && str_contains($controller, '$this->FileManager->isEditable($path)')
);

ok(
    'createFile() writes $path + submitted name with submitted content',
    str_contains($controller, 'file_put_contents($path .')
        && str_contains($controller, "['name']")
        && str_contains($controller, "['content']")
);

echo "[+] Source-level vulnerable pattern confirmed.\n";
