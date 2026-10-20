#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
source_root="$root/third_party/kex-08/source/Implementations and Test_Vectors/Implementations/Reference_Implementation"
build="$root/results/kex-08/build-ngcc-probe"
mkdir -p "$build"
api="$build/KEX_AlgorithmInstance.o"
cc -std=c99 -O0 -g -DRADIX_64 -Wno-incompatible-pointer-types \
   -ffunction-sections -fdata-sections \
   -I"$source_root/NIIKE-lv128/ngccapi/include" \
   -I"$source_root/common/include" \
   -I"$source_root/gf/include" \
   -I"$source_root/NIIKE-lv128/precomp/include" \
   -I"$source_root/ec/include" \
   -I"$source_root/protocols/include" \
   -c "$source_root/ngccapi/KEX_AlgorithmInstance.c" -o "$api"
cc -std=c99 -DRADIX_64 -I"$source_root/NIIKE-lv128/ngccapi/include" \
   -I"$source_root/common/include" -I"$source_root/NIIKE-lv128/precomp/include" \
   "$root/results/kex-08/output_length_probe.c" "$api" \
   -Wl,--gc-sections \
   -Wl,--wrap=make_SecretKey -Wl,--wrap=niike_PublicKey \
   -Wl,--wrap=encode_bundlevertex -Wl,--wrap=decode_bundlevertex \
   -Wl,--wrap=niike_SecretAgreement -Wl,--wrap=fp2_encode \
   -o "$build/output_length_probe"
"$build/output_length_probe"
