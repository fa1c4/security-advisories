#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
build="$root/results/ngcc_119_source_audit/build_kem_rng"
mkdir -p "$build"
selected_target=${1:-all}
if [[ "$selected_target" != all && "$selected_target" != kem-09 && "$selected_target" != kem-18 ]]; then
    echo "usage: $0 [all|kem-09|kem-18]" >&2
    exit 2
fi
if [[ "$selected_target" == all || "$selected_target" == kem-09 ]]; then
for variant in Cheetah128 Cheetah256 Cheetah384 Cheetah512; do
    src="$root/third_party/kem-09/source/Implementations/Reference_Implementation/$variant"
    gcc -std=gnu11 -O1 -g -I"$src" \
        "$src/KEM_Cheetah.c" "$src/auxfunc.c" "$src/mod.c" \
        "$src/ntt.c" "$src/poly.c" \
        "$root/results/ngcc_119_source_audit/kem_rng_failure_probe.c" \
        -o "$build/$variant"
    echo "$variant"
    "$build/$variant" control
    "$build/$variant" fail
done
fi
if [[ "$selected_target" == all || "$selected_target" == kem-18 ]]; then
for variant in Loong128 Loong256 Loong384 Loong512; do
    src="$root/third_party/kem-18/source/Implementations/Reference_Implementation/$variant"
    gcc -std=gnu11 -O1 -g -I"$src" \
        "$src/KEM_Loong.c" "$src/auxfunc.c" "$src/poly.c" \
        "$root/results/ngcc_119_source_audit/kem_rng_failure_probe.c" \
        -o "$build/$variant"
    echo "$variant"
    "$build/$variant" control
    "$build/$variant" fail
done
fi
