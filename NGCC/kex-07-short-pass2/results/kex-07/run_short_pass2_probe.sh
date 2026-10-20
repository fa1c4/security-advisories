#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
src="$root/third_party/kex-07/source/Implementations and Test_Vectors/Implementations/Reference_Implementation/NEV-AKE-C1-c"
build="$root/results/kex-07/build"
mkdir -p "$build"
gcc -std=gnu11 -O1 -g -fsanitize=address -fno-omit-frame-pointer \
    -ffunction-sections -fdata-sections -DPARAMS=1 -DUSE_ICCS \
    -I"$src" -I"$src/symmetrics" -I"$src/kat_test" \
    "$src/poly.c" "$src/ntt.c" "$src/owpke.c" "$src/owkem.c" \
    "$src/verify.c" "$src/sample.c" "$src/pack.c" "$src/cca.c" \
    "$src/cpa.c" "$src/ake.c" "$src/symmetrics/hashkdf.c" \
    "$src/symmetrics/fips202.c" "$src/symmetrics/auxfunc.c" \
    "$src/kat_test/KEX_AlgorithmInstance.c" \
    "$root/results/kex-07/short_pass2_probe.c" \
    -Wl,--gc-sections -o "$build/probe"
ASAN_OPTIONS=detect_leaks=0 "$build/probe" valid
if ASAN_OPTIONS=detect_leaks=0 "$build/probe" short >"$build/short_stdout.log" 2>"$build/short_asan.log"; then
    echo 'Expected a short pass-two message read' >&2
    exit 1
fi
if ! rg -q 'ERROR: AddressSanitizer: heap-buffer-overflow' "$build/short_asan.log"; then
    echo "Unexpected failure; inspect $build/short_asan.log" >&2
    exit 1
fi
rg -n 'ERROR: AddressSanitizer:|ake.c:|KEX_AlgorithmInstance.c:' "$build/short_asan.log" | head -6
