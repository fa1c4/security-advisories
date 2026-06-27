# TeamToy CAND-13e7a27a275b standalone PoC

This is a local-only Docker PoC for an authenticated PHP object injection primitive in `easychen/TeamToy`.

## Vulnerability

`controller/api.class.php::apiController::user_update_settings()` accepts the request parameter `value` and calls PHP native `unserialize()` on it before validating the decoded type:

```php
if (!$value = unserialize(v('value'))) {
    $value = z(t(v('value')));
    ...
} else {
    if (!is_array($value)) {
        return self::send_error(...);
    }
}
```

The later `is_array()` check is too late. For a serialized object, PHP instantiates the object and can invoke magic methods such as `__wakeup()` during `unserialize()` before the endpoint returns the `VALUE` error.

## Run

```bash
docker build -t poc-teamtoy-object-injection .
docker run --rm poc-teamtoy-object-injection
```

## Expected success

The run should end with:

```text
VULNERABLE: TeamToy CAND-13e7a27a275b reproduced successfully.
```

The PoC demonstrates:

1. no-token requests are rejected before the sink;
2. a valid token plus a serialized object in `value` invokes `__wakeup()`;
3. a normal serialized array is still accepted;
4. a patched control using `unserialize(..., ['allowed_classes' => false])` rejects the object without invoking `__wakeup()`.

## Safety

The PoC does not connect to TeamToy, a database, or any external service. The harmless proof gadget only writes a marker file under `/tmp` to prove object instantiation.
