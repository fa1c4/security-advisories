# TasmoAdmin actions clean CSRF PoC

This PoC reproduces the missing CSRF protection in `tasmoadmin/pages/actions.php` for cleanup actions such as `clean=config`.
The harness simulates an authenticated session and invokes the vulnerable GET-controlled branch without a CSRF token.

## Build

```bash
docker build -t poc-tasmoadmin-actions-clean-csrf .
```

## Run

```bash
docker run --rm poc-tasmoadmin-actions-clean-csrf
```

## Expected vulnerable output

```text
config exists before forged GET: yes
csrf token supplied: no
config exists after forged GET: no
[VULNERABLE] GET request without a CSRF token deleted TasmoAdmin configuration data.
```
