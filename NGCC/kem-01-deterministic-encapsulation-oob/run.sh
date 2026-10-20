#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if bash results/kem-01/run_poc_deterministic_encapsulation_oob.sh >reproduction.log 2>&1; then
    if grep -Fq 'unsupported on this CPU' reproduction.log; then
        cat reproduction.log
        exit 77
    fi
    cat reproduction.log
    printf 'ATTACK kem-01-deterministic-encapsulation-oob CONFIRMED source-level observation and controls\n'
else
    status=$?
    cat reproduction.log
    exit "$status"
fi
