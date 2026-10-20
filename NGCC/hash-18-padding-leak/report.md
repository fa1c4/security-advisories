# MEGASCON (hash-18): Successful hashes retain the padding allocation

## Candidate / Affected

**Candidate:** MEGASCON, `hash-18`.  
**Affected:** Six reference `CryptHash` instances: 384, 512, 768, 1024, 384-XOF, and 512-XOF.

## Bug Description

Each tested successful hash call leaves its padded-message allocation live. Repeated calls can increase process memory use; remote resource exhaustion has not been demonstrated.

## Root Cause Analysis / Evidence

**Source:** [`hash-18/Megascon/Implementations/Reference_Implementation/MEGASCON-384-XOF/CryptHash_AlgorithmInstance.c:142-184`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/hash-18/Megascon/Implementations/Reference_Implementation/MEGASCON-384-XOF/CryptHash_AlgorithmInstance.c#L142-L184) (SHA-256 `57a4a46df1f3b2f54bba4f87f7d87ed610f602bfa8ada00630f3d5993594f727`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

`hash_sponge` allocates `padded_input` at line 144 and returns after digest generation at line 184 without freeing it. For a one-bit message, each of the six instances returned status 0, changed the digest, and left one tracked allocation outstanding (`allocations=1`, `frees=0`). The probe releases the allocation after measuring it.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/hash-18-padding-leak

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh hash-18-padding-leak
```

Expected final marker: `hash-18-padding-leak REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/hash-18-padding-leak/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/hash-18-padding-leak/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
