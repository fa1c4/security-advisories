# Croogo FileManager Arbitrary File Write / Path Traversal Local Docker PoC

This Docker lab verifies and reproduces, in an isolated local environment, a Croogo Admin FileManager arbitrary file write / path traversal issue at commit:

```text
fc0648659dcb5790ba8a7429250dae492ca724ec
```

The lab is intended for local verification only. It does not target any third-party system.

## What the lab does

1. Clones `croogo/croogo`.
2. Checks out the affected commit.
3. Verifies the vulnerable source patterns:
   - `FileManager.editablePaths` is configured to `WWW_ROOT/assets`.
   - `FileManager::isEditable()` uses `Configure::check('FileManager.editablePaths')` instead of `Configure::read(...)`.
   - `createFile()` checks `isEditable($path)` and then writes `$path . name` with attacker-controlled content.
4. Recreates the vulnerable path-check logic locally.
5. Demonstrates that a file can be written outside the intended `assets` editable root.
6. Starts a local PHP server and confirms a benign proof file executes from the local webroot.

## Usage

```bash
docker build -t croogo-filemanager-write-poc .
docker run --rm croogo-filemanager-write-poc
```

Expected success markers:

```text
[+] Source-level vulnerable pattern confirmed.
[+] Vulnerable isEditable() accepted the outside path.
[+] Arbitrary file write reproduced: /tmp/croogo-filemanager-lab/webroot/croogo_poc.php
[+] PHP proof file executed successfully in the local webroot.
[+] Vulnerability reproduced in local lab: configured assets-only boundary was bypassed and a file was written to webroot.
```

## Notes

This is a source-verified logic-level Docker lab rather than a full Croogo admin deployment. Full exploitation in a real app requires an authenticated Croogo admin/FileManager user and a valid CakePHP CSRF token.
