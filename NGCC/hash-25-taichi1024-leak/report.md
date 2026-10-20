# TaiChi (hash-25): TaiChi-1024 leaks its padding buffer on successful hashes

## Candidate / Affected

**Candidate:** TaiChi, `hash-25`.  
**Affected:** TaiChi-1024 reference `CryptHash` / `TaiChi_Hash`.

## Bug Description

A valid `CryptHash` call returns success and produces a digest while leaving one padding allocation live. Repeated calls can increase process memory use; remote resource exhaustion has not been demonstrated.

## Root Cause Analysis / Evidence

**Source:** [`hash-25/TaiChi/Implementations/Reference_Implementation/TaiChi-1024/CryptHash_AlgorithmInstance.c:246-279`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/hash-25/TaiChi/Implementations/Reference_Implementation/TaiChi-1024/CryptHash_AlgorithmInstance.c#L246-L279) (SHA-256 `5edc85a88bb7a7be85eeff3a695572aeb74587c42facf888d18140bd68272887`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

`TaiChi_Hash` allocates the padding buffer at line 246 and returns at line 279 without freeing it. The allocation probe observed `status=0`, `allocations=1`, `frees=0`, and `outstanding=1`. The 512- and 768-bit sibling sources free their corresponding buffers; the probe releases the tracked block after counting.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/hash-25-taichi1024-leak

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh hash-25-taichi1024-leak
```

Expected final marker: `hash-25-taichi1024-leak REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/hash-25-taichi1024-leak/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/hash-25-taichi1024-leak/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
