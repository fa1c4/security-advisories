#!/usr/bin/env bash
set -euo pipefail
ulimit -c 0
here=$(cd "$(dirname "$0")" && pwd)
make -C "$here" all

for bits in 512 1024 2048; do
    ASAN_OPTIONS=detect_leaks=0 "$here/build/poc" guarded "$bits"
done
for bits in 512 1024; do
    log="$here/build/asan-$bits.log"
    if ASAN_OPTIONS=detect_leaks=0:halt_on_error=1 "$here/build/poc" exact "$bits" >"$log" 2>&1; then
        echo "Expected an AddressSanitizer failure for $bits bits" >&2
        exit 1
    fi
    if ! grep -q 'ERROR: AddressSanitizer: heap-buffer-overflow' "$log"; then
        echo "Unexpected failure for $bits bits; inspect $log" >&2
        exit 1
    fi
    echo "bits=$bits exact_buffer=AddressSanitizer_heap_buffer_overflow"
done
for bits in 512 1024; do
    ASAN_OPTIONS=detect_leaks=0 "$here/build/poc" core_exact "$bits"
done
ASAN_OPTIONS=detect_leaks=0 "$here/build/poc" exact 2048
