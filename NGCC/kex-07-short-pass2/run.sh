#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if bash results/kex-07/run_short_pass2_probe.sh >reproduction.log 2>&1; then
    if grep -Fq 'unsupported on this CPU' reproduction.log; then
        cat reproduction.log
        exit 77
    fi
    cat reproduction.log
    printf 'ATTACK kex-07-short-pass2 CONFIRMED source-level observation and controls\n'
else
    status=$?
    cat reproduction.log
    exit "$status"
fi
