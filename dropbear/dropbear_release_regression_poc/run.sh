#!/usr/bin/env bash
set -u
printf '[*] Testing Dropbear ref: %s\n' "${DROPBEAR_REF:-unknown}"
/poc_verify /crash.bin
status=$?
printf '[*] exit: %s\n' "$status"
if [ "$status" -eq 1 ]; then
  printf '[!] Result: crafted key/signature verification behavior reproduced on this ref.\n'
else
  printf '[*] Result: crafted key/signature verification behavior not reproduced on this ref.\n'
fi
exit "$status"
