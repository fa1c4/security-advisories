# jbig2dec text-region SBNUMINSTANCES CPU denial of service

## Overview

jbig2dec commit `71f2b02f821a4664d0777fcf8845f56614cedc89` is vulnerable to a
long-running decode hang when processing crafted JBIG2 text-region data. The
issue is reachable through the normal `jbig2dec` command-line decoder and
through the OSS-Fuzz `jbig2_fuzzer` target.

The primary input is only 860 bytes but drives the text-region decoder into a
loop bounded by an attacker-controlled `SBNUMINSTANCES` value of
`0xffffffff`.

## Impact

An attacker who can cause an application or service to decode an untrusted JBIG2
stream with jbig2dec can consume CPU for an excessive amount of time. This is a
denial-of-service issue. No memory corruption or code execution is claimed.

## Tested Version

- Project: jbig2dec
- Tested commit: `71f2b02f821a4664d0777fcf8845f56614cedc89`
- Built CLI version: `jbig2dec 0.20`
- Primary trigger: `timeout-913f58bc349328d7`
- Secondary trigger: `timeout-9ca16597ba597ba8`

## Reproduction

Build the PoC image:

```sh
cd PoC
docker build -t jbig2dec-timeout-poc .
```

Confirm the fuzzer timeout:

```sh
docker run --rm jbig2dec-timeout-poc \
  bash -c 'timeout 300 /out/jbig2_fuzzer /tmp/timeout-913f58bc349328d7; echo "exit=$?"'
```

Observed result:

```text
/out/jbig2_fuzzer: Running 1 inputs 1 time(s) each.
Running: /tmp/timeout-913f58bc349328d7
==8== libFuzzer: run interrupted; exiting
exit=124
```

Confirm the real CLI timeout:

```sh
docker run --rm jbig2dec-timeout-poc \
  bash -c 'timeout --preserve-status 30 /src/jbig2dec/jbig2dec -q -o /tmp/out.pbm /tmp/timeout-913f58bc349328d7; echo "exit=$?"'
```

Observed result:

```text
exit=143
```

The same CLI command also timed out for `timeout-9ca16597ba597ba8`. The bundled
`timeout-c4f782501f1eabe3` completed locally and is retained as a comparison
input from the original fuzzing package.

## Source-Level Root Cause

The vulnerable path is the text-region decoder:

```text
jbig2_data_in()
  jbig2_parse_segment()
    jbig2_text_region()
      jbig2_decode_text_region()
        jbig2_arith_int_decode()
          jbig2_arith_decode()
```

In `jbig2_text.c`, `jbig2_text_region()` reads the text-region symbol-instance
count directly from the segment body:

```c
/* 7.4.3.1.4 */
if (segment->data_length - offset < 4) {
    code = jbig2_error(ctx, JBIG2_SEVERITY_FATAL, segment->number,
                       "segment too short");
    goto cleanup2;
}
params.SBNUMINSTANCES = jbig2_get_uint32(segment_data + offset);
offset += 4;
```

Later, `jbig2_decode_text_region()` uses the value as an upper bound:

```c
NINSTANCES = 0;

while (NINSTANCES < params->SBNUMINSTANCES) {
    ...
    NINSTANCES++;
}
```

There is no sanity check tying `SBNUMINSTANCES` to the region dimensions, symbol
dictionary availability, data length, or any practical decode budget.

For the primary input, GDB on an unoptimized debug build showed this state at
the start of the problematic text-region loop:

```text
segment->number = 4
size = 17
params.SBHUFF = 0
params.SBREFINE = 2
params.SBNUMINSTANCES = 4294967295
params.LOGSBSTRIPS = 1
params.SBSTRIPS = 2
n_dicts = 0
SBNUMSYMS = 0
NINSTANCES = 0
```

After letting the decoder run briefly and interrupting it:

```text
NINSTANCES = 8351257
params.SBNUMINSTANCES = 4294967295
STRIPT = 60283298
CURT = 696018548
RI = -3
```

The hot stack was:

```text
#0 jbig2_arith_decode
#1 jbig2_arith_int_decode
#2 jbig2_decode_text_region
#3 jbig2_text_region
#4 jbig2_parse_segment
#5 jbig2_data_in
#6 main
```

The arithmetic decoder itself is not the main bug. When the compressed data
stream ends, `jbig2_arith_bytein()` may synthesize a terminating marker and
continue producing decoded bits according to its stream convention. The DoS is
created by combining that behavior with the unchecked four-billion text-region
instance count.

## Why This Is Attacker Reachable

The primary trigger begins with a normal JBIG2 file signature and is processed
by `jbig2dec` as a single input file. The normal CLI decoder times out without
using the fuzzer harness. Therefore the behavior is reachable through the
product's supported file-decoding path.

## Suggested Fix

Add validation before entering `jbig2_decode_text_region()` or at the beginning
of that function. Possible checks:

- Reject `SBNUMINSTANCES` values that are inconsistent with the region size or
  exceed a conservative implementation limit.
- Treat `n_dicts == 0 && SBNUMINSTANCES > 0` as fatal instead of only warning.
- Abort text-region decoding when arithmetic-coded input has reached a terminal
  state but the decoder is still expected to produce many more symbol
  instances.
- Add a decode-work budget for malformed inputs so small files cannot force
  billions of iterations.

The safest immediate mitigation is to reject malformed text regions with
impossible symbol-instance counts.
