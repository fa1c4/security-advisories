#!/usr/bin/env bash
set -u
printf '[*] Running kie-soup CronExpression hang PoC\n'
printf '[*] Input: /tmp/crash.bin\n'
printf '[*] Timeout: 10 seconds\n'
timeout 10 java -cp "/work:/work/libs/*" Poc
status=$?
printf '[*] exit: %s\n' "$status"
if [ "$status" -eq 124 ]; then
  printf '[*] Reproduced: parser did not return before timeout.\n'
fi
exit "$status"
