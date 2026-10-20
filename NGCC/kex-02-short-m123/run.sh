#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if bash results/kex-02/run_short_pass1_probe.sh all >reproduction.log 2>&1; then
    if grep -Fq 'unsupported on this CPU' reproduction.log; then
        cat reproduction.log
        exit 77
    fi
    cat reproduction.log
    printf 'ATTACK kex-02-short-m123 CONFIRMED nine source-level observations and controls\n'
else
    status=$?
    cat reproduction.log
    exit "$status"
fi
