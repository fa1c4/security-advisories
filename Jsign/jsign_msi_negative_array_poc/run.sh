#!/usr/bin/env bash
set -u
printf '[*] Running Jsign MSI/OLE2 NegativeArraySizeException PoC\n'
printf '[*] Input: crash.bin\n'
java -cp ".:lib/*" Poc
status=$?
printf '[*] exit: %s\n' "$status"
exit "$status"
