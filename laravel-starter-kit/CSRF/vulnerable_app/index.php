<?php
/**
 * Minimal standalone reproducer for brunogaspar/laravel-starter-kit CAND-657ddceac24d.
 *
 * This reproduces the Laravel 4 controller-constructor root cause:
 *   BaseController::__construct() registers beforeFilter('csrf', ['on' => 'post']).
 *   AuthorizedController::__construct() calls parent::__construct().
 *   AdminController::__construct() overrides the constructor but does not call parent::__construct().
 *   GroupsController::postEdit() is therefore reachable with admin-auth but without csrf.
 *
 * It is intentionally small and local-only. It models the relevant route and filter semantics,
 * not the full Laravel framework or Sentry package.
 */

const DB_FILE = '/tmp/laravel_starter_kit_groups.json';
const ADMIN_COOKIE = 'admin-session';
const ADMIN_CSRF_TOKEN = 'server-side-real-csrf-token';

final class HttpError extends Exception
{
    public int $status;
    public function __construct(int $status, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function init_db(): void
{
    file_put_contents(DB_FILE, json_encode([
        '1' => [
            'id' => 1,
            'name' => 'Original Admins',
            'permissions' => ['dashboard.view' => 1],
        ],
    ], JSON_PRETTY_PRINT));
}

function load_groups(): array
{
    if (!file_exists(DB_FILE)) {
        init_db();
    }
    $decoded = json_decode(file_get_contents(DB_FILE), true);
    return is_array($decoded) ? $decoded : [];
}

function save_groups(array $groups): void
{
    file_put_contents(DB_FILE, json_encode($groups, JSON_PRETTY_PRINT));
}

function current_session(): ?array
{
    $session = $_COOKIE['laravel_session'] ?? '';
    if ($session === ADMIN_COOKIE) {
        return [
            'user_id' => 1,
            'is_admin' => true,
            'csrf_token' => ADMIN_CSRF_TOKEN,
        ];
    }
    return null;
}

class Controller
{
    /** @var array<int, array{name:string,options:array}> */
    protected array $beforeFilters = [];

    public function beforeFilter(string $name, array $options = []): void
    {
        $this->beforeFilters[] = ['name' => $name, 'options' => $options];
    }

    public function registeredFilters(): array
    {
        return array_map(static fn(array $filter): string => $filter['name'], $this->beforeFilters);
    }

    public function runBeforeFilters(string $httpMethod): void
    {
        foreach ($this->beforeFilters as $filter) {
            $name = $filter['name'];
            $options = $filter['options'];
            if (isset($options['on']) && strtolower((string) $options['on']) !== strtolower($httpMethod)) {
                continue;
            }
            if ($name === 'auth') {
                if (!current_session()) {
                    throw new HttpError(401, 'auth filter rejected request');
                }
            }
            if ($name === 'admin-auth') {
                $session = current_session();
                if (!$session || !$session['is_admin']) {
                    throw new HttpError(403, 'admin-auth filter rejected request');
                }
            }
            if ($name === 'csrf') {
                $session = current_session();
                $submitted = $_POST['_token'] ?? null;
                if (!$session || $submitted !== $session['csrf_token']) {
                    throw new HttpError(419, 'csrf filter rejected request: missing or invalid _token');
                }
            }
        }
    }
}

class BaseController extends Controller
{
    public function __construct()
    {
        // Mirrors app/controllers/BaseController.php:17-23.
        $this->beforeFilter('csrf', ['on' => 'post']);
    }
}

class AuthorizedController extends BaseController
{
    protected array $whitelist = [];

    public function __construct()
    {
        // Mirrors app/controllers/AuthorizedController.php:17-24.
        $this->beforeFilter('auth', ['except' => $this->whitelist]);
        parent::__construct();
    }
}

class AdminController extends AuthorizedController
{
    public function __construct()
    {
        // VULNERABLE: mirrors app/controllers/AdminController.php:10-14.
        // The missing call is: parent::__construct();
        $this->beforeFilter('admin-auth');
    }

    protected function decodePermissions(array &$permissions): void
    {
        $decoded = [];
        foreach ($permissions as $encoded => $access) {
            $decoded[base64_decode((string) $encoded, true) ?: (string) $encoded] = $access;
        }
        $permissions = $decoded;
    }
}

class PatchedAdminController extends AuthorizedController
{
    public function __construct()
    {
        // Control endpoint: the minimal fix is to call parent first, then add admin-auth.
        parent::__construct();
        $this->beforeFilter('admin-auth');
    }

    protected function decodePermissions(array &$permissions): void
    {
        $decoded = [];
        foreach ($permissions as $encoded => $access) {
            $decoded[base64_decode((string) $encoded, true) ?: (string) $encoded] = $access;
        }
        $permissions = $decoded;
    }
}

class GroupsController extends AdminController
{
    public function postEdit(int $id): array
    {
        // Mirrors app/controllers/admin/GroupsController.php:143-184.
        $permissions = $_POST['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }
        $this->decodePermissions($permissions);

        $groups = load_groups();
        if (!isset($groups[(string) $id])) {
            throw new HttpError(404, 'group not found');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            throw new HttpError(422, 'name is required');
        }

        $groups[(string) $id]['name'] = $name;
        $groups[(string) $id]['permissions'] = $permissions;
        save_groups($groups);

        return [
            'updated' => true,
            'controller' => 'GroupsController',
            'registered_filters' => $this->registeredFilters(),
            'csrf_filter_registered' => in_array('csrf', $this->registeredFilters(), true),
            'group' => $groups[(string) $id],
        ];
    }
}

class GroupsControllerPatched extends PatchedAdminController
{
    public function postEdit(int $id): array
    {
        $permissions = $_POST['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }
        $this->decodePermissions($permissions);

        $groups = load_groups();
        if (!isset($groups[(string) $id])) {
            throw new HttpError(404, 'group not found');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            throw new HttpError(422, 'name is required');
        }

        $groups[(string) $id]['name'] = $name;
        $groups[(string) $id]['permissions'] = $permissions;
        save_groups($groups);

        return [
            'updated' => true,
            'controller' => 'GroupsControllerPatched',
            'registered_filters' => $this->registeredFilters(),
            'csrf_filter_registered' => in_array('csrf', $this->registeredFilters(), true),
            'group' => $groups[(string) $id],
        ];
    }
}

function json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function dispatch_group_edit(bool $patched, int $id): void
{
    $controller = $patched ? new GroupsControllerPatched() : new GroupsController();
    $controller->runBeforeFilters('post');
    json_response(200, $controller->postEdit($id));
}

try {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    if ($path === '/health') {
        header('Content-Type: text/plain');
        echo 'ok';
        return;
    }
    if ($path === '/reset') {
        init_db();
        json_response(200, ['reset' => true, 'groups' => load_groups()]);
        return;
    }
    if ($path === '/state') {
        json_response(200, ['groups' => load_groups(), 'expected_csrf_token' => ADMIN_CSRF_TOKEN]);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('#^/admin/groups/(\d+)/edit$#', $path, $m)) {
        dispatch_group_edit(false, (int) $m[1]);
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('#^/patched/admin/groups/(\d+)/edit$#', $path, $m)) {
        dispatch_group_edit(true, (int) $m[1]);
        return;
    }

    json_response(404, ['error' => 'not found', 'path' => $path]);
} catch (HttpError $e) {
    json_response($e->status, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    json_response(500, ['error' => $e->getMessage(), 'type' => get_class($e)]);
}
