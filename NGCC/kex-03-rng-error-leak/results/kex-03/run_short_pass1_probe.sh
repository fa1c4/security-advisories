#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
src="$root/third_party/kex-03/source/Implementations/Reference_Implementation/CreTAKE128/CreTAKE-K2S-ZEN128-BiT128"
build="$root/results/kex-03/build"
mkdir -p "$build"
selected_case=${1:-all}
if [[ "$selected_case" != all && "$selected_case" != pass1 &&
      "$selected_case" != pass2 && "$selected_case" != rng ]]; then
    echo "usage: $0 [all|pass1|pass2|rng]" >&2
    exit 2
fi
gcc -std=gnu11 -O1 -g -fno-omit-frame-pointer -fsanitize=address \
    -ffunction-sections -fdata-sections -I"$src" \
    "$root/results/kex-03/short_pass1_probe.c" \
    -Wl,--gc-sections,--wrap=calloc,--wrap=free -o "$build/probe"
if [[ "$selected_case" == all || "$selected_case" == pass1 ]]; then
ASAN_OPTIONS=detect_leaks=0 "$build/probe" valid
if ASAN_OPTIONS=detect_leaks=0 "$build/probe" >"$build/stdout.log" 2>"$build/asan.log"; then
    echo 'Expected AddressSanitizer to report a short-message read' >&2
    exit 1
fi
if ! grep -q 'ERROR: AddressSanitizer: heap-buffer-overflow' "$build/asan.log"; then
    echo "Unexpected failure; inspect $build/asan.log" >&2
    exit 1
fi
rg -n 'ERROR: AddressSanitizer:|KEX_AlgorithmInstance.c:' "$build/asan.log" | head -5
fi
if [[ "$selected_case" == all || "$selected_case" == pass2 ]]; then
ASAN_OPTIONS=detect_leaks=0 "$build/probe" derive_valid
if ASAN_OPTIONS=detect_leaks=0 "$build/probe" derive_short >"$build/derive_stdout.log" 2>"$build/derive_asan.log"; then
    echo 'Expected AddressSanitizer to report a short pass-two message read' >&2
    exit 1
fi
if ! grep -q 'ERROR: AddressSanitizer: heap-buffer-overflow' "$build/derive_asan.log"; then
    echo "Unexpected failure; inspect $build/derive_asan.log" >&2
    exit 1
fi
rg -n 'ERROR: AddressSanitizer:|KEX_AlgorithmInstance.c:' "$build/derive_asan.log" | head -5
fi
if [[ "$selected_case" == all || "$selected_case" == rng ]]; then
ASAN_OPTIONS=detect_leaks=0 "$build/probe" valid
ASAN_OPTIONS=detect_leaks=0 "$build/probe" rng_fail
fi
