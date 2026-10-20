#!/usr/bin/env bash
set -euo pipefail
bundle=$(cd "$(dirname "$0")" && pwd)
: "${NGCC_HARNESS_ROOT:?Set NGCC_HARNESS_ROOT to the ngcc-harness checkout}"
candidate=hash-25
source_relative=TaiChi/Implementations/Reference_Implementation/TaiChi-1024/CryptHash_AlgorithmInstance.c
expected_sha256=5edc85a88bb7a7be85eeff3a695572aeb74587c42facf888d18140bd68272887
expected_commit=6a13c278a0cf7a6b4bcee9bc2494932898486c70
upstream=$(cd "$NGCC_HARNESS_ROOT" && pwd)
actual_commit=$(git -C "$upstream" rev-parse HEAD 2>/dev/null || true)
if [[ "$actual_commit" != "$expected_commit" ]] ||
   ! git -C "$upstream" diff --quiet -- "$candidate" ||
   ! git -C "$upstream" diff --cached --quiet -- "$candidate"; then
    printf 'Use the clean ngcc-harness commit %s for %s\n' "$expected_commit" "$candidate" >&2
    exit 2
fi
source_tree="$upstream/$candidate"
if [[ ! -f "$source_tree/$source_relative" ]]; then
    printf 'SKIP %s: source absent from ngcc-harness checkout\n' "$candidate" >&2
    exit 77
fi
actual_sha256=$(sha256sum "$source_tree/$source_relative" | cut -d ' ' -f 1)
if [[ "$actual_sha256" != "$expected_sha256" ]]; then
    printf 'Source SHA-256 mismatch for %s/%s\n' "$candidate" "$source_relative" >&2
    exit 2
fi
link="$bundle/third_party/$candidate/source"
mkdir -p "$(dirname "$link")"
if [[ -L "$link" ]]; then
    if [[ "$(readlink -f "$link")" != "$source_tree" ]]; then
        printf 'Existing source link points to another tree: %s\n' "$link" >&2
        exit 2
    fi
elif [[ -e "$link" ]]; then
    printf 'Source link path is already occupied: %s\n' "$link" >&2
    exit 2
else
    ln -s "$source_tree" "$link"
fi
cd "$bundle"
bash run.sh
