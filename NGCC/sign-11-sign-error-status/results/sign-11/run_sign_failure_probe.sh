#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
src="$root/third_party/sign-11/source/Implementations/Reference_Implementation/Flextree-160f"
build="$root/results/sign-11/build"
mkdir -p "$build"
gcc -std=gnu11 -O0 -g -DPARAMS=flextree-160f -DFLEXTREE -I"$src" \
    "$src/sign.c" "$src/address.c" "$src/auxfunc.c" \
    "$src/counters.c" "$src/hash_sm3.c" "$src/merkle.c" \
    "$src/pors_fp.c" "$src/thash_sm3_simple.c" "$src/utils.c" \
    "$src/utilsx1.c" "$src/wots.c" "$src/wotsx1.c" \
    "$root/results/sign-03/sign_failure_probe.c" \
    -Wl,--wrap=hash_message -o "$build/probe"
"$build/probe" control
"$build/probe" fail
