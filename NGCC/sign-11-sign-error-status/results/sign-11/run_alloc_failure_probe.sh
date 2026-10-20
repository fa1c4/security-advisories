#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
src="$root/third_party/sign-11/source/Implementations/Reference_Implementation/Flextree-160f"
build="$root/results/sign-11/build"
mkdir -p "$build"
gcc -std=gnu11 -O0 -g -I"$src" "$src/auxfunc.c" \
    "$root/results/sign-05/alloc_failure_probe.c" \
    -Wl,--wrap=malloc,--wrap=free -o "$build/alloc_probe"
"$build/alloc_probe" control
"$build/alloc_probe" fail_second
