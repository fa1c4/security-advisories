# Pinkary notification deletion CSRF / state-changing GET PoC

This standalone harness executes the original `NotificationController::show()` method from the captured source with minimal Laravel-compatible stubs.

## Build and run

```bash
docker build -t poc-pinkary-notification-get-delete .
docker run --rm poc-pinkary-notification-get-delete
```

Expected vulnerable output includes:

```text
[VULNERABLE] GET /notifications/{notification} caused a persistent delete without CSRF validation.
```

## Vulnerability condition reproduced

The captured route maps `GET /notifications/{notification}` to `NotificationController::show()`. The controller deletes the `DatabaseNotification` when the related question already has an answer. A GET request does not carry or validate a CSRF token, yet causes a persistent state change.
