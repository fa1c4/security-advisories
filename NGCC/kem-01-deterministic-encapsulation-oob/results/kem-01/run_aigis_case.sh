#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

case "${1:-}" in
    detenc|dec|reject) mode="$1" ;;
    *) echo "usage: $0 {detenc|dec|reject}" >&2; exit 2 ;;
esac

mkdir -p logs
if ! make -s >logs/build.log 2>&1; then
    cat logs/build.log >&2
    exit 1
fi
for variant in I II III; do
    log="logs/${mode}_${variant}.log"
    if [[ "$mode" == reject ]]; then
        ASAN_OPTIONS=detect_leaks=0 "./aigis_harness_${variant}" \
            reject input_seed.bin input_reject.txt >"$log" 2>&1
        grep -Fq 're-encryption mismatch=1, secret equals H3(s,c)=no, unchanged after s changes=yes' "$log"
    else
        if ASAN_OPTIONS=detect_leaks=0:halt_on_error=1 "./aigis_harness_${variant}" \
            "$mode" input_seed.bin >"$log" 2>&1; then
            echo "Expected AddressSanitizer failure for ${variant}/${mode}" >&2
            exit 1
        fi
        if [[ "$mode" == detenc ]]; then
            grep -Fq 'ERROR: AddressSanitizer: stack-buffer-overflow' "$log"
            grep -Fq 'mdkem_enc' "$log"
        else
            grep -Fq 'ERROR: AddressSanitizer: heap-buffer-overflow' "$log"
            grep -Fq 'mkem_dec' "$log"
        fi
    fi
    echo "${variant}/${mode}: reproduced (${log})"
done
