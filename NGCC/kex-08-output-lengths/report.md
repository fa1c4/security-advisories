# NIIKE (kex-08): Successful API calls leave output lengths unchanged

## Candidate / Affected

**Candidate:** NIIKE, `kex-08`.  
**Affected:** NIIKE-lv128 reference `kex_init_a`, `kex_init_b`, `kex_derive_ss_a`, and `kex_derive_ss_b`.

## Bug Description

Both parties return success and write output bytes while leaving public-key, secret-key, state, and shared-key length outputs at caller-supplied values. This is an API bookkeeping and interoperability defect; primitive stubs isolate the length behavior, so key agreement was not tested.

## Root Cause Analysis / Evidence

**Source:** [`kex-08/Implementations and Test_Vectors/Implementations/Reference_Implementation/ngccapi/KEX_AlgorithmInstance.c:59-160`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/kex-08/Implementations%20and%20Test_Vectors/Implementations/Reference_Implementation/ngccapi/KEX_AlgorithmInstance.c#L59-L160) (SHA-256 `0d6ac6afb881ba4008697eb51161573a9a87118012ad2a47061e863c7a9b2fbd`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

The four wrapper functions do not assign their documented output-length pointers before returning 0. With zero-initialized lengths, the calls still wrote output bytes but reported zero lengths; prefilled control lengths remained 4160, 196, and 64, which explains why a KAT that initializes those lengths can miss the omission.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/kex-08-output-lengths

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh kex-08-output-lengths
```

Expected final marker: `kex-08-output-lengths REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kex-08-output-lengths/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kex-08-output-lengths/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
