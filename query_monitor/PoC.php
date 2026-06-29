<?php
/*
 * Standalone PoC for Query Monitor QM_Dispatcher_Html::ajax_on().
 * It loads the original Dispatcher.php and Html.php from the target plugin and stubs only WordPress APIs
 * needed to exercise the vulnerable setcookie() call.
 */
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('COOKIE_DOMAIN', '');
define('COOKIEPATH', '/');
define('COOKIEHASH', 'demo_hash');
define('QM_VERSION', '4.0.7');

class QM_Plugin {}
function add_action($hook, $callback, $priority = null) {}
function add_filter($hook, $callback, $priority = null, $accepted_args = null) {}
function current_user_can($capability) { return $capability === 'view_query_monitor'; }
function check_ajax_referer($action, $query_arg = false, $die = true) { return $action === 'qm-auth-on'; }
function is_ssl() { return false; }
function home_url() { return 'http://example.test'; }
function get_current_user_id() { return 123; }
function wp_generate_auth_cookie($user_id, $expiration, $scheme) {
    return "user{$user_id}|{$expiration}|session-token|valid-hmac-for-{$scheme}";
}
function wp_send_json_success($value = null) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $value]);
    exit;
}
function wp_send_json_error($value = null) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['success' => false, 'data' => $value]);
    exit;
}

require __DIR__ . '/classes/Dispatcher.php';
require __DIR__ . '/dispatchers/Html.php';

$dispatcher = new QM_Dispatcher_Html(new QM_Plugin());
$dispatcher->ajax_on();
