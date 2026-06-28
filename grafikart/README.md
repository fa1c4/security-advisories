# Grafikart.fr CAND-88f7e917dbe8 standalone PoC

This local-only PoC simulates the vulnerable Grafikart.fr Laravel control flow:

- `bootstrap/app.php` calls `validateCsrfTokens(except: ['*'])`.
- Authenticated profile routes include `POST /profil`.
- `UserController::update()` fills and saves the current user.

Run:

```bash
docker build -t poc-grafikart-global-csrf-disabled .
docker run --rm poc-grafikart-global-csrf-disabled
```

The PoC never contacts a real Grafikart.fr instance.
