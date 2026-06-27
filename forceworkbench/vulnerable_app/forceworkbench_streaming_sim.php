<?php
declare(strict_types=1);

/**
 * Standalone, local-only simulation of the Force.com Workbench StreamingController
 * CSRF bug found in forceworkbench/forceworkbench.
 *
 * The important source pattern being modeled is:
 *
 *   workbench/session.php:
 *       if ($_SERVER['REQUEST_METHOD'] != 'GET') {
 *           validateCsrfToken();
 *       }
 *
 *   workbench/controllers/StreamingController.php:
 *       if (isset($_REQUEST['PUSH_TOPIC_DML'])) {
 *           if ($_REQUEST['PUSH_TOPIC_DML'] == "SAVE") { $this->save(); }
 *           else if ($_REQUEST['PUSH_TOPIC_DML'] == "DELETE") { $this->delete(); }
 *       }
 *
 * This harness never contacts Salesforce.  Instead, FakeRestApi records the REST
 * methods and URLs that the vulnerable controller would send to Salesforce.
 */

final class HttpAbort extends RuntimeException
{
    public int $statusCode;

    public function __construct(int $statusCode, string $message)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }
}

function fw_reset_runtime(): void
{
    $GLOBALS['FW_REST_LOG'] = [];
    $GLOBALS['FW_CSRF_CHECKS'] = 0;
    $GLOBALS['FW_CSRF_FAILURES'] = 0;
}

function fw_csrf_token(): string
{
    return 'server-side-real-csrf-token';
}

function fw_validate_csrf_token(bool $doError = true): bool
{
    $GLOBALS['FW_CSRF_CHECKS']++;

    if (!isset($_REQUEST['CSRF_TOKEN']) || $_REQUEST['CSRF_TOKEN'] !== fw_csrf_token()) {
        $GLOBALS['FW_CSRF_FAILURES']++;
        if ($doError) {
            throw new HttpAbort(403, 'Invalid or missing required CSRF token');
        }
        return false;
    }

    return true;
}

function fw_vulnerable_session_gate(): void
{
    // Mirrors workbench/session.php: CSRF is skipped for every GET request.
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        fw_validate_csrf_token();
    }
}

function fw_patched_session_gate(): void
{
    // Expected fixed behavior for DML endpoints: only POST is allowed, and CSRF
    // must be validated before any state-changing operation is dispatched.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new HttpAbort(405, 'PUSH_TOPIC_DML requires POST');
    }
    fw_validate_csrf_token();
}

final class FakeRestResponse
{
    public string $header;
    public string $body;

    public function __construct(string $header, string $body)
    {
        $this->header = $header;
        $this->body = $body;
    }
}

final class FakeRestApi
{
    public function send(string $method, string $url, ?array $headers = null, ?string $data = null, bool $unused = false): FakeRestResponse
    {
        $GLOBALS['FW_REST_LOG'][] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'data' => $data,
        ];

        if ($method === 'GET') {
            return new FakeRestResponse('HTTP/1.1 200 OK', json_encode(['records' => []], JSON_THROW_ON_ERROR));
        }

        if ($method === 'POST') {
            return new FakeRestResponse('HTTP/1.1 201 Created', '[]');
        }

        if ($method === 'PATCH' || $method === 'DELETE') {
            return new FakeRestResponse('HTTP/1.1 204 No Content', '[]');
        }

        return new FakeRestResponse('HTTP/1.1 400 Bad Request', '[{"message":"unsupported method"}]');
    }
}

final class WorkbenchContext
{
    public static function get(): self
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    public function getRestDataConnection(): FakeRestApi
    {
        return new FakeRestApi();
    }

    public function getApiVersion(): string
    {
        return '65.0';
    }
}

final class PushTopic
{
    public ?string $Id;
    public ?string $Name;
    public ?string $ApiVersion;
    public ?string $Query;

    public function __construct(?string $id, ?string $name, ?string $apiVersion, ?string $query)
    {
        $this->Id = $id;
        $this->Name = $name;
        $this->ApiVersion = $apiVersion;
        $this->Query = $query;
    }

    public function toJson(bool $includeId = true): string
    {
        $payload = [
            'Name' => $this->Name,
            'ApiVersion' => $this->ApiVersion,
            'Query' => $this->Query,
        ];
        if ($includeId) {
            $payload['Id'] = $this->Id;
        }
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}

final class VulnerableStreamingController
{
    private string $restBaseUrl;
    private FakeRestApi $restApi;
    private PushTopic $selectedTopic;

    public function __construct()
    {
        $this->restApi = WorkbenchContext::get()->getRestDataConnection();
        $this->restBaseUrl = '/services/data/v' . WorkbenchContext::get()->getApiVersion();

        // Mirrors the vulnerable source: $_REQUEST accepts both GET query
        // parameters and POST body parameters.
        $this->selectedTopic = new PushTopic(
            $_REQUEST['pushTopicDmlForm_Id'] ?? null,
            $_REQUEST['pushTopicDmlForm_Name'] ?? null,
            $_REQUEST['pushTopicDmlForm_ApiVersion'] ?? null,
            $_REQUEST['pushTopicDmlForm_Query'] ?? null
        );

        if (isset($_REQUEST['PUSH_TOPIC_DML'])) {
            if ($_REQUEST['PUSH_TOPIC_DML'] === 'SAVE') {
                $this->save();
            } elseif ($_REQUEST['PUSH_TOPIC_DML'] === 'DELETE') {
                $this->delete();
            }
        }

        $this->refresh();
    }

    private function refresh(): void
    {
        $pushTopicSoql = 'SELECT Id, Name, Query, ApiVersion FROM PushTopic';
        $url = $this->restBaseUrl . '/query?' . http_build_query(['q' => $pushTopicSoql]);
        $this->restApi->send('GET', $url, null, null, false);
    }

    private function save(): void
    {
        if ($this->selectedTopic->Id !== null && $this->selectedTopic->Id !== 'undefined') {
            $this->dml('PATCH', '/' . $this->selectedTopic->Id, $this->selectedTopic->toJson(false));
        } else {
            $this->dml('POST', '', $this->selectedTopic->toJson(false));
        }
    }

    private function delete(): void
    {
        $this->dml('DELETE', '/' . $this->selectedTopic->Id, null);
    }

    private function dml(string $method, string $urlTail, ?string $data): void
    {
        $headers = ['Content-Type: application/json'];
        $url = $this->restBaseUrl . '/sobjects/PushTopic' . $urlTail;
        $this->restApi->send($method, $url, $headers, $data, false);
    }
}

final class PatchedStreamingController
{
    private string $restBaseUrl;
    private FakeRestApi $restApi;
    private PushTopic $selectedTopic;

    public function __construct()
    {
        $this->restApi = WorkbenchContext::get()->getRestDataConnection();
        $this->restBaseUrl = '/services/data/v' . WorkbenchContext::get()->getApiVersion();

        // Expected fixed behavior: DML is dispatched only from POST body after
        // the patched session gate has validated CSRF.
        $this->selectedTopic = new PushTopic(
            $_POST['pushTopicDmlForm_Id'] ?? null,
            $_POST['pushTopicDmlForm_Name'] ?? null,
            $_POST['pushTopicDmlForm_ApiVersion'] ?? null,
            $_POST['pushTopicDmlForm_Query'] ?? null
        );

        if (isset($_POST['PUSH_TOPIC_DML'])) {
            if ($_POST['PUSH_TOPIC_DML'] === 'SAVE') {
                $this->save();
            } elseif ($_POST['PUSH_TOPIC_DML'] === 'DELETE') {
                $this->delete();
            }
        }

        $this->refresh();
    }

    private function refresh(): void
    {
        $pushTopicSoql = 'SELECT Id, Name, Query, ApiVersion FROM PushTopic';
        $url = $this->restBaseUrl . '/query?' . http_build_query(['q' => $pushTopicSoql]);
        $this->restApi->send('GET', $url, null, null, false);
    }

    private function save(): void
    {
        if ($this->selectedTopic->Id !== null && $this->selectedTopic->Id !== 'undefined') {
            $this->dml('PATCH', '/' . $this->selectedTopic->Id, $this->selectedTopic->toJson(false));
        } else {
            $this->dml('POST', '', $this->selectedTopic->toJson(false));
        }
    }

    private function delete(): void
    {
        $this->dml('DELETE', '/' . $this->selectedTopic->Id, null);
    }

    private function dml(string $method, string $urlTail, ?string $data): void
    {
        $headers = ['Content-Type: application/json'];
        $url = $this->restBaseUrl . '/sobjects/PushTopic' . $urlTail;
        $this->restApi->send($method, $url, $headers, $data, false);
    }
}

function fw_state_changing_calls(array $restLog): array
{
    return array_values(array_filter($restLog, static function (array $call): bool {
        return in_array($call['method'], ['POST', 'PATCH', 'DELETE'], true);
    }));
}

function fw_prepare_superglobals(string $method, array $params): void
{
    fw_reset_runtime();

    $_SERVER['REQUEST_METHOD'] = strtoupper($method);
    $_SERVER['SCRIPT_NAME'] = '/workbench/streaming.php';
    $_SERVER['PHP_SELF'] = '/workbench/streaming.php';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $_GET = $params;
        $_POST = [];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_GET = [];
        $_POST = $params;
    } else {
        $_GET = [];
        $_POST = [];
    }

    // PHP populates $_REQUEST from GET/POST/COOKIE depending on variables_order.
    // The vulnerability exists because Workbench consumes $_REQUEST for DML.
    $_REQUEST = $params;
}

function fw_run_vulnerable_streaming_request(string $method, array $params): array
{
    fw_prepare_superglobals($method, $params);

    $status = 200;
    $body = ['ok' => true];

    try {
        fw_vulnerable_session_gate();
        new VulnerableStreamingController();
    } catch (HttpAbort $e) {
        $status = $e->statusCode;
        $body = ['ok' => false, 'error' => $e->getMessage()];
    }

    return [
        'http_status' => $status,
        'body' => $body,
        'csrf_checks' => $GLOBALS['FW_CSRF_CHECKS'],
        'csrf_failures' => $GLOBALS['FW_CSRF_FAILURES'],
        'all_rest_calls' => $GLOBALS['FW_REST_LOG'],
        'state_changing_rest_calls' => fw_state_changing_calls($GLOBALS['FW_REST_LOG']),
    ];
}

function fw_run_patched_streaming_request(string $method, array $params): array
{
    fw_prepare_superglobals($method, $params);

    $status = 200;
    $body = ['ok' => true];

    try {
        fw_patched_session_gate();
        new PatchedStreamingController();
    } catch (HttpAbort $e) {
        $status = $e->statusCode;
        $body = ['ok' => false, 'error' => $e->getMessage()];
    }

    return [
        'http_status' => $status,
        'body' => $body,
        'csrf_checks' => $GLOBALS['FW_CSRF_CHECKS'],
        'csrf_failures' => $GLOBALS['FW_CSRF_FAILURES'],
        'all_rest_calls' => $GLOBALS['FW_REST_LOG'],
        'state_changing_rest_calls' => fw_state_changing_calls($GLOBALS['FW_REST_LOG']),
    ];
}
