# ULEARN CAND-1cceb622460f standalone PoC

This local-only PoC simulates the bundled TinyMCE openmanager uploader:

- `fileactions.php?s=uploadfile` calls `upload_file()`.
- `uploadfolder`, `mediatype`, and the original filename are request-controlled.
- No authentication, CSRF, extension allowlist, or MIME/content checks are enforced before writing the file.

Run:

```bash
docker build -t poc-ulearn-openmanager-upload .
docker run --rm poc-ulearn-openmanager-upload
```

The PoC writes only to a temporary directory inside the container. In CLI mode it uses a safe local `rename()` to model the same destination-building logic, because PHP `move_uploaded_file()` only succeeds for real HTTP upload temp files.
