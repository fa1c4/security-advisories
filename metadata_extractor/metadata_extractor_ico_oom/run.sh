#!/usr/bin/env bash
set -u
printf '[*] Running metadata-extractor ICO OOM PoC\n'
printf '[*] Java heap: -Xmx16m\n'
printf '[*] Input: poc_input\n'
java -Xmx16m -cp /src:/src/metadata-extractor.jar PocReproducer
status=$?
printf '[*] exit: %s\n' "$status"
exit "$status"
