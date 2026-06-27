<?php
/**
 * Local-only minimal simulation of shard-query/ui/awsconfig/configure2.php.
 *
 * The original script is a directly reachable setup/configuration endpoint that
 * reads $_REQUEST, performs MySQL grants/drop/create, writes bootstrap/config
 * files, and does not enforce authentication, an install secret, request method,
 * or CSRF validation.
 *
 * This simulation records intended SQL/file operations instead of touching MySQL
 * or real Shard-Query files.
 */

class LocalSwanhartNode
{
    public array $sql = [];
    public array $fileWrites = [];
    public int $authChecks = 0;
    public int $authFailures = 0;
    public int $csrfChecks = 0;
    public int $csrfFailures = 0;
    public bool $hasExistingData = true;

    public function myQuery(string $sql): void
    {
        $this->sql[] = $sql;
    }

    public function writeFile(string $path, string $content): void
    {
        $this->fileWrites[] = [
            'path' => $path,
            'content' => $content,
        ];
    }
}

function sq_escape(string $value): string
{
    return str_replace(['\\', "'", "\0", "\n", "\r", '"'], ['\\\\', "\\'", '\\0', '\\n', '\\r', '\\"'], $value);
}

function local_reset_swanhart_state(): LocalSwanhartNode
{
    return new LocalSwanhartNode();
}

function local_run_configure2(LocalSwanhartNode $node, string $method, array $params, bool $patched = false): array
{
    $_GET = strtoupper($method) === 'GET' ? $params : [];
    $_POST = strtoupper($method) === 'POST' ? $params : [];
    $_REQUEST = $params;
    $_SERVER['REQUEST_METHOD'] = strtoupper($method);

    if ($patched) {
        // Patch model: setup/configuration is a critical function and must require
        // a dedicated setup secret and POST. A real patch could also disable the
        // script after first install.
        $node->authChecks++;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $node->authFailures++;
            return local_swanhart_response(405, ['ok' => false, 'error' => 'configure2 requires POST'], $node);
        }
        $node->csrfChecks++;
        if (($_POST['setup_token'] ?? null) !== 'server-side-setup-token') {
            $node->csrfFailures++;
            return local_swanhart_response(403, ['ok' => false, 'error' => 'missing or invalid setup token'], $node);
        }
    }

    // Mirrors configure2.php: if empty($_REQUEST) exit;
    if (empty($_REQUEST)) {
        return local_swanhart_response(204, ['ok' => true, 'message' => 'empty request: no setup performed'], $node);
    }

    // Mirrors destructive force link: existing data does not block if force=1 is supplied.
    if ($node->hasExistingData && empty($_REQUEST['force'])) {
        return local_swanhart_response(409, [
            'ok' => false,
            'error' => 'node appears to be set up already',
            'force_url' => 'configure2.php?' . http_build_query($_REQUEST) . '&force=1',
        ], $node);
    }

    foreach ([3306, 5029] as $port) {
        $node->myQuery("CONNECT 127.0.0.1:$port AS root WITH EMPTY PASSWORD");
        $node->myQuery("grant all on *.* to '" . sq_escape((string)$_REQUEST['username']) . "'@'%' identified by '" . sq_escape((string)$_REQUEST['password']) . "' with grant option;");
        $node->myQuery('drop database if exists `' . sq_escape((string)$_REQUEST['schema_name']) . '`;');
        $node->myQuery('create database `' . sq_escape((string)$_REQUEST['schema_name']) . '`;');
        if (($_REQUEST['type'] ?? '') === 'r') {
            $node->myQuery('drop database if exists sq;');
        }
        $node->myQuery('create database if not exists sq;');
    }

    if (($_REQUEST['type'] ?? '') === 'r') {
        $bootstrap = implode("\n", [
            '[default]',
            'user="' . ($_REQUEST['username'] ?? '') . '"',
            'password="' . ($_REQUEST['password'] ?? '') . '"',
            '[config]',
            'db="sq"',
            'schema_name="' . ($_REQUEST['schema_name'] ?? '') . '"',
            'host="' . ($_REQUEST['host'] ?? '') . '"',
            'mapper="directory"',
            'column="' . ($_REQUEST['column'] ?? '') . '"',
            'column_datatype="' . ($_REQUEST['column_datatype'] ?? '') . '"',
        ]);
        $node->writeFile('shard-query/ui/awsconfig/bootstrap.ini', $bootstrap);
    } else {
        // Mirrors storage-node branch: write ../../include/config.inc with attacker-supplied settings.
        $config = [
            'rdbms-type' => 'pdo-mysql',
            'db' => 'sq',
            'host' => $_REQUEST['host'] ?? '',
            'port' => 3306,
            'user' => $_REQUEST['username'] ?? '',
            'password' => $_REQUEST['password'] ?? '',
            'mapper_type' => 'directory',
            'default_virtual_schema' => $_REQUEST['schema_name'] ?? '',
        ];
        $node->writeFile('shard-query/include/config.inc', serialize($config));
    }

    return local_swanhart_response(200, ['ok' => true, 'message' => 'node setup completed'], $node);
}

function local_swanhart_response(int $status, array $body, LocalSwanhartNode $node): array
{
    $stateChangingSql = array_values(array_filter($node->sql, function (string $sql): bool {
        return preg_match('/^(grant|drop|create)/i', $sql) === 1;
    }));

    return [
        'http_status' => $status,
        'body' => $body,
        'auth_checks' => $node->authChecks,
        'auth_failures' => $node->authFailures,
        'csrf_checks' => $node->csrfChecks,
        'csrf_failures' => $node->csrfFailures,
        'all_sql_operations' => $node->sql,
        'state_changing_sql_operations' => $stateChangingSql,
        'file_writes' => $node->fileWrites,
    ];
}
