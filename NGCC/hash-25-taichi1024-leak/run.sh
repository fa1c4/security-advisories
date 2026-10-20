#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if bash results/hash-25/run_taichi1024_leak.sh >reproduction.log 2>&1; then
    if grep -Fq 'unsupported on this CPU' reproduction.log; then
        cat reproduction.log
        exit 77
    fi
    cat reproduction.log
    printf 'ATTACK hash-25-taichi1024-leak CONFIRMED source-level observation and controls\n'
else
    status=$?
    cat reproduction.log
    exit "$status"
fi
