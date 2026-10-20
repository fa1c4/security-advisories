# Laurus-XOF `CryptHash` writes past the requested digest buffer

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh hash-14-output-length-oob
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `hash-14-output-length-oob/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Assessment:** Reproduced out-of-bounds write in the submitted `hash-14`
Laurus-XOF reference public wrapper. This is a source-level implementation
finding. It does not establish deployment exposure or exploitability.

## Contract and root cause

The submitted specification at `third_party/hash-14/specification.pdf`
(SHA-256 `63190edaf6bd2b85c93f21f49412567cfcb0c8330063c9124d19cd64cd91b141`),
PDF pp. 5 and 11, describes Laurus-XOF as an extendable-output function with
an output length chosen for the call. The submitted reference
`CryptHash_AlgorithmInstance.h:32-37` calls `digest_len_bits` the total bits of
the digest. The wrapper `CryptHash_AlgorithmInstance.c:14-17` ignores that
parameter and always calls `laurus_xof` with `DIGEST_BIT_LENGTH = 2048` from
its header at line 24. The wrapper source SHA-256 is
`01fe50b80ba0a9130f7b557926b68ccb9d79ab3a2663aaa63067f22f20042e6b`.
The core accepts the caller-selected bit length and writes that many bits
(`laurus_xof.c:217-246`).

## Reproduction

The verified `run_ngcc.sh` entry point invokes `run.sh`, which calls
`results/hash-14/run.sh`. The included `results/hash-14/Makefile` compiles the original reference wrapper and core with GCC
AddressSanitizer. A one-bit message `0x80` is the fixed input.

| Requested output | Caller buffer | Guarded output | Exact-size result |
| --- | ---: | --- | --- |
| 512 bits | 64 bytes | Matches direct 512-bit core prefix; wrapper writes through byte 255 | ASan `heap-buffer-overflow` |
| 1024 bits | 128 bytes | Matches direct 1024-bit core prefix; wrapper writes through byte 255 | ASan `heap-buffer-overflow` |
| 2048 bits | 256 bytes | Matches direct 2048-bit core output | Returns 0 without ASan error |

Direct `laurus_xof` calls with exact 64- and 128-byte buffers return without
ASan error. The guarded buffer remains unchanged after byte 255. Thus the
short-output memory corruption is caused by the public wrapper's fixed length
argument. The sanitizer traces are retained in ignored
`results/hash-14/build/asan-512.log` and `asan-1024.log` after the run.

The wrapper should pass the validated requested length to the core and test
short and default lengths with exact-size buffers. The source files under
`third_party/` have not been edited.
