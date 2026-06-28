# V-0783 WardrobeCMS installer CSRF PoC

Build and run:

```bash
docker build -t poc-0783 .
docker run --rm poc-0783
```

The container starts a local vulnerable harness for the WardrobeCMS installer route and then executes `PoC.php`. The PoC sends `POST /install/config` without `_token`. Success is proven by the generated `state/wardrobe.php` file containing the attacker-controlled site title and `installed => true`.

This PoC is limited to a local Docker harness and does not target any external host.
