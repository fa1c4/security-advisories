#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
build="$root/results/ngcc_119_source_audit/build"
mkdir -p "$build"
selected_target=${1:-all}
if [[ "$selected_target" != all && "$selected_target" != hash-18 && "$selected_target" != hash-20 ]]; then
    echo "usage: $0 [all|hash-18|hash-20]" >&2
    exit 2
fi

for pair in 'hash-18 Megascon MEGASCON-384' \
            'hash-18 Megascon MEGASCON-512' \
            'hash-18 Megascon MEGASCON-768' \
            'hash-18 Megascon MEGASCON-1024' \
            'hash-18 Megascon MEGASCON-384-XOF' \
            'hash-18 Megascon MEGASCON-512-XOF' \
            'hash-20 Mozi MOZI-384' \
            'hash-20 Mozi MOZI-512' \
            'hash-20 Mozi MOZI-768' \
            'hash-20 Mozi MOZI-1024' \
            'hash-20 Mozi MOZI-384-XOF' \
            'hash-20 Mozi MOZI-512-XOF'; do
    read -r target algorithm instance <<<"$pair"
    if [[ "$selected_target" != all && "$selected_target" != "$target" ]]; then continue; fi
    src="$root/third_party/$target/source/$algorithm/Implementations/Reference_Implementation/$instance"
    binary="$build/$target-$instance"
    gcc -std=c11 -O1 -g -I"$src" \
        "$root/results/ngcc_119_source_audit/hash_padding_leak_harness.c" \
        "$src/CryptHash_AlgorithmInstance.c" \
        -Wl,--wrap=malloc,--wrap=free -o "$binary"
    printf '%s/%s: ' "$target" "$instance"
    "$binary"
done
