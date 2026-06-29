# TasmoAdmin firmware upload CSRF PoC

This PoC reproduces the missing CSRF protection in the authenticated firmware upload flow.
It starts a local PHP built-in server that contains a minimal copy of the vulnerable upload logic and submits a multipart POST request without any CSRF token.

## Build

```bash
docker build -t poc-tasmoadmin-firmware-upload-csrf .
```

## Run

```bash
docker run --rm poc-tasmoadmin-firmware-upload-csrf
```

## Expected vulnerable output

```text
minimal firmware written: yes
full firmware written: yes
csrf token supplied: no
[VULNERABLE] Multipart POST without a CSRF token wrote firmware files to disk.
```
