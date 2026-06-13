#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/opt/ResponsiveFilemanager"
HOST="0.0.0.0"
PORT="${PORT:-8000}"
INTERNAL_TARGET="http://127.0.0.1:${PORT}"

mkdir -p /opt/evidence

cd "${APP_DIR}/filemanager"

echo "[*] Starting PHP built-in server for ResponsiveFilemanager..."
echo "[*] Document root: ${APP_DIR}"
echo "[*] Listening on: ${HOST}:${PORT}"

php -S "${HOST}:${PORT}" -t "${APP_DIR}" > /tmp/rfm-php-server.log 2>&1 &
SERVER_PID="$!"

cleanup() {
  if kill -0 "$SERVER_PID" >/dev/null 2>&1; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

for i in $(seq 1 50); do
  if curl -fsS "${INTERNAL_TARGET}/filemanager/dialog.php" >/dev/null 2>&1; then
    break
  fi
  sleep 0.2
  if [[ "$i" -eq 50 ]]; then
    echo "[!] PHP server did not become ready in time. Server log follows:"
    cat /tmp/rfm-php-server.log || true
    exit 1
  fi
done

echo "[*] Server is ready: ${INTERNAL_TARGET}"

auto_poc="${RFM_AUTO_POC:-1}"
if [[ "$auto_poc" == "1" ]]; then
  echo "[*] Running local vulnerability reproduction..."
  /opt/poc/reproduce-local.sh "${INTERNAL_TARGET}" | tee /opt/evidence/reproduction.log
  echo "[*] Reproduction log saved in container: /opt/evidence/reproduction.log"
else
  echo "[*] RFM_AUTO_POC=0, skipping automatic PoC."
fi

echo "[*] Container is still running so you can inspect the app from the host."
echo "[*] Recommended host URL: http://127.0.0.1:${PORT}/filemanager/dialog.php"
echo "[*] To stop: Ctrl+C, or docker stop <container>."
echo "[*] PHP server log: /tmp/rfm-php-server.log"

tail -f /tmp/rfm-php-server.log
