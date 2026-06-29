#!/bin/sh
set -eu
php -S 127.0.0.1:8080 -t /poc >/tmp/qm-poc-server.log 2>&1 &
PID=$!
trap 'kill $PID >/dev/null 2>&1 || true' EXIT
sleep 1
RESPONSE=$(curl -s -i http://127.0.0.1:8080/PoC.php)
printf '%s\n' "$RESPONSE"
COOKIE_LINE=$(printf '%s\n' "$RESPONSE" | grep -i '^Set-Cookie:' || true)
if [ -z "$COOKIE_LINE" ]; then
  echo '[NOT REPRODUCED] no Set-Cookie header observed'
  exit 1
fi
if printf '%s' "$COOKIE_LINE" | grep -qi 'HttpOnly'; then
  echo '[NOT VULNERABLE] cookie contains HttpOnly'
  exit 1
fi
if printf '%s' "$COOKIE_LINE" | grep -qi 'SameSite'; then
  echo '[NOTE] cookie contains SameSite; this target version may differ from the analyzed source'
fi
echo '[VULNERABLE] Query Monitor emits a WordPress logged_in auth-cookie value in QM_COOKIE without HttpOnly and without SameSite.'
