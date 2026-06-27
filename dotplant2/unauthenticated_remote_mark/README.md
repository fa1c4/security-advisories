# dotplant2_installer_complete_missing_installer_step_guard

Standalone Docker PoC for `DevGroup-ru/dotplant2` finding `CAND-0e17ef3ac330`.

## Bug

`InstallerController::actionComplete()` can write `@app/installed.mark = 1` before the installation flow is legitimately completed.

## Root cause

The installer configuration disables CSRF validation, and the complete action does not enforce POST, a one-time installer token, or a validated final-step/session state.

## Run

```bash
docker build -t poc-dotplant2-installer-complete .
docker run --rm poc-dotplant2-installer-complete
```

This PoC is local-only and uses in-memory state. It does not start dotplant2, touch a database, or contact external services.
