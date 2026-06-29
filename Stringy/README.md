# PoC: danielstjules/Stringy `isSerialized()` object injection side effect

Build and run:

```bash
docker build -t poc-stringy-isserialized-object-injection .
docker run --rm poc-stringy-isserialized-object-injection
```

Expected vulnerable output includes:

```text
[VULNERABLE] __wakeup executed during isSerialized() check.
```

The PoC uses the target `src/Stringy.php` copied from the uploaded InvAudit result pack.
