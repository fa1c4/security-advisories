#!/usr/bin/env bash
set -euo pipefail

package_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
checkout="$package_root/source/ngcc-harness"
remote=${NGCC_SOURCE_REMOTE:-https://github.com/ngcc-dev/ngcc-harness.git}
commit=6a13c278a0cf7a6b4bcee9bc2494932898486c70
candidates=(
    hash-14 hash-15 hash-18 hash-20 hash-25
    kem-01 kem-09 kem-18
    kex-02 kex-03 kex-07 kex-08
    sign-03 sign-11 sign-24
)

verify_checkout() {
    local directory=${1:-$checkout}
    local actual top candidate tracked
    if [[ ! -e "$directory/.git" ]]; then
        printf 'Expected a Git checkout at %s\n' "$directory" >&2
        return 2
    fi
    top=$(git -C "$directory" rev-parse --show-toplevel) || return 2
    if [[ "$top" != "$directory" ]]; then
        printf 'Unexpected Git worktree root: %s\n' "$top" >&2
        return 2
    fi
    actual=$(git -C "$directory" rev-parse HEAD) || return 2
    if [[ "$actual" != "$commit" ]]; then
        printf 'Expected ngcc-harness commit %s; found %s\n' "$commit" "$actual" >&2
        return 2
    fi
    for candidate in "${candidates[@]}"; do
        if [[ ! -d "$directory/$candidate" ]] ||
           ! git -C "$directory" diff --quiet -- "$candidate" ||
           ! git -C "$directory" diff --cached --quiet -- "$candidate"; then
            printf 'Missing or modified candidate source: %s\n' "$candidate" >&2
            return 2
        fi
    done
    while IFS= read -r -d '' tracked; do
        if [[ ! -f "$directory/$tracked" ]]; then
            printf 'Missing tracked source file: %s\n' "$tracked" >&2
            return 2
        fi
    done < <(git -C "$directory" ls-files -z -- "${candidates[@]}")
}

if [[ -e "$checkout" || -L "$checkout" ]]; then
    verify_checkout
    printf 'Source ready: %s (%s)\n' "$checkout" "$commit"
    exit 0
fi

mkdir -p "$package_root/source"
staging=$(mktemp -d "$package_root/source/.ngcc-harness.XXXXXXXX")
cleanup() { rm -rf -- "$staging"; }
trap cleanup EXIT

git clone --quiet --filter=blob:none --sparse --no-checkout "$remote" "$staging"
git -C "$staging" checkout --quiet --detach "$commit"
git -C "$staging" sparse-checkout set --cone "${candidates[@]}"

verify_checkout "$staging"
mv -- "$staging" "$checkout"
trap - EXIT
printf 'Source ready: %s (%s)\n' "$checkout" "$commit"
