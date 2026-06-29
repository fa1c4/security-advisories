# Beanbun unsafe unserialize PoC

Build and run:

```bash
docker build -t beanbun-unserialize-dos-poc .
docker run --rm beanbun-unserialize-dos-poc
```

Expected successful reproduction output contains:

```text
Cannot use object of type stdClass as array
[+] Reproduced: unsafe unserialize allows a remote serialized object to crash the worker
```

The PoC uses the original vulnerable `Server.php` and calls `Beanbun\Lib\Server::onMessage()` with the payload a remote TCP client would control.
