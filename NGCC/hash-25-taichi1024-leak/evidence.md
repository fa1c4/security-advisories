# Bug report: TaiChi-1024 leaks its padding buffer on successful hashes

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh hash-25-taichi1024-leak
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `hash-25-taichi1024-leak/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Status:** Reproduced implementation bug; deployment impact remains a candidate. **Affected scope:** `hash-25` TaiChi-1024 reference `CryptHash` API. Source: `third_party/hash-25/source/TaiChi/Implementations/Reference_Implementation/TaiChi-1024/CryptHash_AlgorithmInstance.c`, SHA-256 `5edc85a88bb7a7be85eeff3a695572aeb74587c42facf888d18140bd68272887`.

## Root cause and observed effect

`TaiChi_Hash` allocates a padding buffer at line 246, computes the digest, and returns at line 279 without a `free`. `CryptHash` returns success. The 512- and 768-bit sibling sources free their corresponding buffer. Clang Static Analyzer also reports a potential `unix.Malloc` leak at line 271.

## Lower-level PoC

The verified `run_ngcc.sh` entry point invokes `run.sh`, which calls
`results/hash-25/run_taichi1024_leak.sh` from this bug directory.

The [Makefile](results/hash-25/Makefile) builds the submitted source and [allocation-counting harness](results/hash-25/taichi1024_leak_harness.c). [taichi1024_leak_input.bin](results/hash-25/taichi1024_leak_input.bin) is a 21-byte valid message (SHA-256 `72100861f7cf95d3eee912625b505ed00bd64e66503c1c2b765521d8f997caaf`). Expected output is `CryptHash status=0, allocations=1, frees=0, outstanding=1`. The harness also checks that the digest differs from its initial sentinel, demonstrating a completed hash rather than an early error. [PoC details](results/hash-25/poc-taichi1024-leak.md).

The evidence establishes one outstanding allocation per tested call. Memory exhaustion depends on repeated use and deployment policy; no remote denial-of-service claim is made.
