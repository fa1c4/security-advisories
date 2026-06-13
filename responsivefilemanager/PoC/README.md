# ResponsiveFilemanager `dialog.php` Unauthenticated Upload Docker Reproduction

This Docker lab reproduces the ResponsiveFilemanager `filemanager/dialog.php` unauthenticated unrestricted file upload issue in a local-only environment.

The PoC uploads a benign PHP file that only prints a marker string:

```text
RF_UPLOAD_POC_OK
```

No real target, credential, hostname, or public IP is used.

## Files

```text
Dockerfile
.dockerignore
docker-entrypoint.sh
poc/reproduce-local.sh
```

## Build

```bash
docker build -t rfm-dialog-upload-poc .
```

The image clones the upstream ResponsiveFilemanager repository and checks out the affected commit:

```text
51eddae5190cfc4408ade40575ee63404fead0b9
```

## Run and reproduce automatically

```bash
docker run --rm -p 127.0.0.1:8000:8000 rfm-dialog-upload-poc
```

Expected successful proof output:

```text
[+] Vulnerability reproduced successfully in local Docker lab.
```

You should also see:

```text
RF_UPLOAD_POC_OK
```

## Inspect from host

After the container starts, the vulnerable app is available on the host at:

```text
http://127.0.0.1:8000/filemanager/dialog.php
```

The uploaded proof file URL is printed by the PoC as:

```text
http://127.0.0.1:8000/source/<uploaded-file>.php
```

## Run server only, without automatic PoC

```bash
docker run --rm -e RFM_AUTO_POC=0 -p 127.0.0.1:8000:8000 rfm-dialog-upload-poc
```

Then execute the PoC manually inside the running container:

```bash
docker exec -it <container_id> /opt/poc/reproduce-local.sh http://127.0.0.1:8000
```

## Evidence

The automatic run writes a log inside the container:

```text
/opt/evidence/reproduction.log
```

To keep it on the host, run with a mounted directory:

```bash
mkdir -p evidence
docker run --rm \
  -p 127.0.0.1:8000:8000 \
  -v "$PWD/evidence:/opt/evidence" \
  rfm-dialog-upload-poc
```

## Safety Notes

- Bind the published port to `127.0.0.1`, not `0.0.0.0`.
- Use only in a local lab.
- Do not use real targets.
- Do not replace the benign marker payload with a webshell.
