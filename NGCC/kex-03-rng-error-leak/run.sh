#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if bash results/kex-03/run_short_pass1_probe.sh rng >reproduction.log 2>&1; then
    if grep -Fq 'unsupported on this CPU' reproduction.log; then
        cat reproduction.log
        exit 77
    fi
    cat reproduction.log
    printf 'ATTACK kex-03-rng-error-leak CONFIRMED source-level observation and controls\n'
else
    status=$?
    cat reproduction.log
    exit "$status"
fi
