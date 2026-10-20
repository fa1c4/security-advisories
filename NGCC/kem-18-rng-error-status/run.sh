#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if bash results/ngcc_119_source_audit/run_kem_rng_failure_probe.sh kem-18 >reproduction.log 2>&1; then
    if grep -Fq 'unsupported on this CPU' reproduction.log; then
        cat reproduction.log
        exit 77
    fi
    cat reproduction.log
    printf 'ATTACK kem-18-rng-error-status CONFIRMED source-level observation and controls\n'
else
    status=$?
    cat reproduction.log
    exit "$status"
fi
