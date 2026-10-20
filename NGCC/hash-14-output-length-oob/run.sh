#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if bash results/hash-14/run.sh >reproduction.log 2>&1; then
    if grep -Fq 'unsupported on this CPU' reproduction.log; then
        cat reproduction.log
        exit 77
    fi
    cat reproduction.log
    printf 'ATTACK hash-14-output-length-oob CONFIRMED source-level observation and controls\n'
else
    status=$?
    cat reproduction.log
    exit "$status"
fi
