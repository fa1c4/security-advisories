#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
build="$root/results/kex-02/build"
mkdir -p "$build"
selected_message=${1:-all}
if [[ "$selected_message" != all && "$selected_message" != m1 &&
      "$selected_message" != m2 && "$selected_message" != m3 ]]; then
    echo "usage: $0 [all|m1|m2|m3]" >&2
    exit 2
fi
for level in 128 256 512; do
    src="$root/third_party/kex-02/source/Implementations and Test_Vectors/Implementations/Reference_Implementation/AFS_KEX_C$level"
    flags=("-DBWKEM_C${level}_USE_ICCS_AUXFUNC")
    if [[ "$level" == 128 ]]; then flags+=("-DBWKEM128_INTERNAL_COMPAT"); fi
    gcc -std=gnu11 -O1 -g -fsanitize=address -fno-omit-frame-pointer \
        "${flags[@]}" -I"$src" \
        "$src/BWcoding.c" "$src/cbd.c" "$src/indcpa.c" "$src/kem.c" \
        "$src/ntt.c" "$src/poly.c" "$src/polyvec.c" \
        "$src/randombytes_api.c" "$src/reduce.c" "$src/symmetric-iccs.c" \
        "$src/verify.c" "$src/auxfunc.c" "$src/KEX_AlgorithmInstance.c" \
        "$root/results/kex-02/short_pass1_probe.c" -o "$build/probe_$level"
    echo "AFS_KEX_C$level"
    for message in m1 m2 m3; do
        if [[ "$selected_message" != all && "$selected_message" != "$message" ]]; then continue; fi
        case "$message" in
            m1) expected_function=kex_generate_pass2_msg_b; expected_line=177 ;;
            m2) expected_function=kex_generate_pass3_msg_a; expected_line=217 ;;
            m3) expected_function=kex_generate_pass4_msg_b; expected_line=264 ;;
        esac
        ASAN_OPTIONS=detect_leaks=0 "$build/probe_$level" "${message}_valid"
        if ASAN_OPTIONS=detect_leaks=0 "$build/probe_$level" "${message}_short" >"$build/short_${level}_${message}_stdout.log" 2>"$build/short_${level}_${message}_asan.log"; then
            echo "Expected a short $message read" >&2
            exit 1
        fi
        asan_log="$build/short_${level}_${message}_asan.log"
        if ! grep -Fq 'ERROR: AddressSanitizer: heap-buffer-overflow' "$asan_log" ||
           ! grep -Fq "in $expected_function" "$asan_log" ||
           ! grep -Fq "KEX_AlgorithmInstance.c:$expected_line" "$asan_log"; then
            echo "Unexpected failure; inspect $build/short_${level}_${message}_asan.log" >&2
            exit 1
        fi
        grep -m 5 -nE 'ERROR: AddressSanitizer:|KEX_AlgorithmInstance.c:|kem.c:' "$asan_log"
        printf 'PASS AFS_KEX_C%s %s: one-byte ASan trace and exact-size control\n' "$level" "$message"
    done
done
