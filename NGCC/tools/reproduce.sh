#!/bin/sh
# PQCFuzz dispatcher adapted from ngcc-harness tools/reproduce.sh.
# The unmodified upstream script is retained at tools/upstream/reproduce.sh.

set -u

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd) || exit 2
cd "$root" || exit 2

if [ "$#" -gt 1 ]; then
    echo "usage: tools/reproduce.sh [--list|candidate-id|bug-id]" >&2
    exit 2
fi

only=${1:-}
if [ "$only" = --list ]; then
    printf '%s\n' \
        hash-14-output-length-oob \
        hash-15-output-length-oob \
        hash-18-padding-leak \
        hash-20-padding-leak \
        hash-25-taichi1024-leak \
        kem-01-deterministic-encapsulation-oob \
        kem-09-rng-error-status \
        kem-18-rng-error-status \
        kex-02-short-m123 \
        kex-03-rng-error-leak \
        kex-07-short-pass2 \
        kex-08-output-lengths \
        sign-03-sign-error-status \
        sign-11-sign-error-status \
        sign-24-partial-allocation-leak
    exit 0
fi

if [ -n "${NGCC_HARNESS_ROOT:-}" ]; then
    harness=$NGCC_HARNESS_ROOT
elif [ -r "$root/.harness-root" ]; then
    IFS= read -r harness < "$root/.harness-root"
else
    echo "Set NGCC_HARNESS_ROOT to a clean ngcc-harness checkout, or write its path to $root/.harness-root" >&2
    exit 2
fi

if [ -z "$harness" ] || [ ! -d "$harness" ] ||
   ! git -C "$harness" rev-parse --verify HEAD >/dev/null 2>&1; then
    echo "Invalid ngcc-harness checkout: $harness. Run source/download_targets.sh first, or set NGCC_HARNESS_ROOT." >&2
    exit 2
fi
NGCC_HARNESS_ROOT=$(CDPATH= cd -- "$harness" && pwd) || exit 2
export NGCC_HARNESS_ROOT
expected_commit=6a13c278a0cf7a6b4bcee9bc2494932898486c70
actual_commit=$(git -C "$NGCC_HARNESS_ROOT" rev-parse HEAD) || exit 2
if [ "$actual_commit" != "$expected_commit" ]; then
    echo "Expected ngcc-harness commit $expected_commit; found $actual_commit" >&2
    exit 2
fi

selected=0
passed=0
failed=0
skipped=0

# Like upstream run_target, each source-built witness returns 0 on a reproduced
# observation and controls, 77 on an unavailable dependency, or nonzero on error.
run_target() {
    candidate=$1
    bug=$2
    case "$only" in
        ''|all|"$candidate"|"$bug") ;;
        *) return 0 ;;
    esac
    selected=$((selected + 1))
    echo "== $bug =="

    if [ ! -f "$bug/run_ngcc.sh" ]; then
        echo "UNEXPECTED $bug missing reproducer"
        failed=$((failed + 1))
        return 0
    fi

    output=$(bash "$bug/run_ngcc.sh" 2>&1)
    result=$?
    printf '%s\n' "$output"
    if [ "$result" -eq 0 ]; then
        case "$output" in
            *"ATTACK $bug CONFIRMED"*)
                echo "$bug REPRODUCER PASS"
                passed=$((passed + 1)) ;;
            *)
                echo "UNEXPECTED $bug returned success without its verdict"
                failed=$((failed + 1)) ;;
        esac
    elif [ "$result" -eq 77 ]; then
        echo "SKIP $bug (dependency or pinned source unavailable)"
        skipped=$((skipped + 1))
    else
        echo "UNEXPECTED $bug reproducer failure (exit $result)"
        failed=$((failed + 1))
    fi
    echo
}

run_target hash-14 hash-14-output-length-oob
run_target hash-15 hash-15-output-length-oob
run_target hash-18 hash-18-padding-leak
run_target hash-20 hash-20-padding-leak
run_target hash-25 hash-25-taichi1024-leak
run_target kem-01 kem-01-deterministic-encapsulation-oob
run_target kem-09 kem-09-rng-error-status
run_target kem-18 kem-18-rng-error-status
run_target kex-02 kex-02-short-m123
run_target kex-03 kex-03-rng-error-leak
run_target kex-07 kex-07-short-pass2
run_target kex-08 kex-08-output-lengths
run_target sign-03 sign-03-sign-error-status
run_target sign-11 sign-11-sign-error-status
run_target sign-24 sign-24-partial-allocation-leak

if [ "$selected" -eq 0 ]; then
    echo "Unknown candidate or bug ID: $only" >&2
    exit 2
fi

printf 'SUMMARY selected=%s passed=%s failed=%s skipped=%s\n' \
    "$selected" "$passed" "$failed" "$skipped"
if [ "$failed" -ne 0 ] || [ "$skipped" -ne 0 ]; then
    exit 1
fi
