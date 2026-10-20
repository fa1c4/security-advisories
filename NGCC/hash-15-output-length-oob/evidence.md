# Litchi-XOF `CryptHash` writes past the requested digest length

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh hash-15-output-length-oob
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `hash-15-output-length-oob/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Assessment:** Independently reproduced implementation bug in the submitted reference wrapper. This report does not promote the PQCFuzz `unverified_spec` candidate to a confirmed vulnerability.

## Affected source and contract

- Target: `hash-15`, `Litchi-XOF`, reference implementation. Source-tree SHA-256 in the S-tier run: `6bab82bda853334a6f628430e6309cd360997f966f5a5f08768da0be3bda5b41`.
- Specification: `third_party/hash-15/specification.pdf`, SHA-256 `454300f46f71ea5cb74daa4df99f0a1a9c2276cdeb9aa7f9ff72f178b8e32bec`, PDF pp. 5 and 7. It describes Litchi-XOF as variable-output and defines the result using the requested `|h|` bits.
- Public API: `third_party/hash-15/source/Litchi/Implementations/Reference_Implementation/Litchi-XOF/CryptHash_AlgorithmInstance.h:31-37` documents `digest_len_bits` as the total bits of digest. The same directory's `KAT_CryptHash.c:288,303` allocates `digest_len_bits / 8` bytes before calling `CryptHash`.
- Defective call: `CryptHash_AlgorithmInstance.c:14-17` ignores `digest_len_bits` and calls `litchi_xof(digest, DIGEST_BIT_LENGTH, ...)`; its header fixes `DIGEST_BIT_LENGTH` at 1024. The core writes the requested output at `litchi_xof.c:171` and correctly accepts shorter lengths when called directly.

## Reproduction

The verified `run_ngcc.sh` entry point invokes `run.sh`, which calls
`results/hash-15/run.sh`. The included `results/hash-15/Makefile` builds the archived wrapper, core and `results/hash-15/harness.c` with AddressSanitizer. `results/hash-15/input.txt` fixes the message to one bit (`0x80`). The script tests 512, 768 and 1024-bit requests with canary space and exact-size heap buffers. It expects ASan failure only for the two short wrapper calls and saves the full ASan traces under ignored `results/hash-15/build/`.

Observed with GCC 11.4.0 on x86-64:

| Request | Caller buffer | Bytes changed after requested end | Exact-size result |
| --- | ---: | ---: | --- |
| 512 bits | 64 bytes | 64 | ASan `heap-buffer-overflow`, first write at offset 64 |
| 768 bits | 96 bytes | 32 | ASan `heap-buffer-overflow`, first write at offset 96 |
| 1024 bits | 128 bytes | 0 | Returns 0 without ASan error |

The requested prefix equals a direct core call for each length. Direct `litchi_xof` calls with exact 64- and 96-byte buffers also return without an ASan error. Thus the defect is in the wrapper's fixed length argument, not the core's short-output path or a mismatch in digest bytes. The submitted `KAT_2_12` uses 1024-bit requests and does not exercise this failure.

## Impact and repair boundary

A caller that allocates the documented number of output bytes for a 512- or 768-bit request suffers a write beyond its buffer. Memory corruption is confirmed for this API use; exploitability and deployment exposure have not been established. Pass the validated caller-requested bit length to `litchi_xof` and add regression tests with exactly sized buffers for short and 1024-bit outputs. Keep any new range/error contract consistent with the specification and API; this PoC does not define one.

The S-tier campaign's 4,416 `LITCHI-XOF-BOUND-01` records remain candidate-only with draft specification extraction and `replay_status: not_run` in PQCFuzz. This independent C/ASan reproduction is external diagnostic evidence, not automatic SOP promotion.
