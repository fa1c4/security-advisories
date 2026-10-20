#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if bash results/sign-03/run_sign_failure_probe.sh >reproduction.log 2>&1; then
    if grep -Fq 'unsupported on this CPU' reproduction.log; then
        cat reproduction.log
        exit 77
    fi
    cat reproduction.log
    printf 'ATTACK sign-03-sign-error-status CONFIRMED source-level observation and controls\n'
else
    status=$?
    cat reproduction.log
    exit "$status"
fi
