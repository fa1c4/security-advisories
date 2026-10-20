# Sigurd (sign-24): Partial allocation failure leaks working buffers

## Candidate / Affected

**Candidate:** Sigurd, `sign-24`.  
**Affected:** Reference and optimized Sigurd-128, -256, and -512 signer and verifier allocation helpers.

## Bug Description

If a later working-buffer allocation fails, earlier allocations remain live when the helper returns an error. The PoC reproduces this in all six reference and optimized variants; deployment impact from repeated failures has not been established.

## Root Cause Analysis / Evidence

**Source:** [`sign-24/Implementations/Reference_Implementation/Sigurd-128/SIG_AlgorithmInstance.c:204-259`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/sign-24/Implementations/Reference_Implementation/Sigurd-128/SIG_AlgorithmInstance.c#L204-L259) (SHA-256 `facbeb5b03facc60963589077dff376c8a0f6712e6b5e15e53ec72b9a0d3a449`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

`alloc_sign_buffers` and `alloc_verify_buffers` allocate several fields, then return `-1` on any `NULL` field without cleaning up successful earlier allocations. Public callers at lines 434, 487, and 559 return on that error without invoking the corresponding free helper. With the second allocation denied, all six signer and all six verifier helpers returned `-1` with one allocation live; invoking each submitted free helper afterward reduced the count to zero.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/sign-24-partial-allocation-leak

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh sign-24-partial-allocation-leak
```

Expected final marker: `sign-24-partial-allocation-leak REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/sign-24-partial-allocation-leak/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/sign-24-partial-allocation-leak/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
