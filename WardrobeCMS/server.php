<?php
/*
 Minimal vulnerable harness for WardrobeCMS installer CSRF.
 Affected source pattern from app/routes.php:
   Route::group(array('prefix' => 'install'), function() {
       Route::post('config', array('uses' => 'InstallController@updateConfig'));
   });
 The project defines Route::filter('csrf', ...) in app/filters.php but the
 installer route group does not attach before => 'csrf'. updateConfig() writes
 app/config/packages/wardrobe/core/wardrobe.php via setWardrobeConfig().
*/
$stateDir = __DIR__ . '/state';
$configFile = $stateDir . '/wardrobe.php';
if (!is_dir($stateDir)) {
    mkdir($stateDir, 0777, true);
}
if (!file_exists($configFile)) {
    file_put_contents($configFile, "<?php\nreturn array('title'=>'Initial Site','theme'=>'Default','per_page'=>5,'installed'=>false);\n");
}
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/install/config' && $method === 'POST') {
    // Vulnerability: no _token / CSRF validation before state change.
    $title = $_POST['title'] ?? 'Site Name';
    $theme = $_POST['theme'] ?? 'Default';
    $perPage = (int)($_POST['per_page'] ?? 5);
    $content = "<?php\nreturn array('title'=>'" . addslashes($title) . "','theme'=>'" . addslashes($theme) . "','per_page'=>" . $perPage . ",'installed'=>true);\n";
    file_put_contents($configFile, $content);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'message' => 'installer config updated without CSRF token',
        'received__token' => array_key_exists('_token', $_POST),
        'config' => $configFile,
    ]);
    return;
}

if ($path === '/state' && $method === 'GET') {
    header('Content-Type: text/plain');
    readfile($configFile);
    return;
}

http_response_code(404);
echo "Not found\n";
