#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
build="$root/results/sign-24/build"
mkdir -p "$build"
for profile in Reference_Implementation Optimized_Implementation; do
    for instance in 128 256 512; do
        src="$root/third_party/sign-24/source/Implementations/$profile/Sigurd-$instance"
        binary="$build/$profile-$instance"
        gcc -std=gnu11 -O0 -ffunction-sections -fdata-sections -I"$src" \
            "$root/results/sign-24/alloc_failure_probe.c" \
            -Wl,--gc-sections,--wrap=malloc,--wrap=free -o "$binary"
        printf '%s/%s\n' "$profile" "$instance"
        "$binary"
    done
done
