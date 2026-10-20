# MOZI (hash-20): Successful hashes retain the padding allocation

## Candidate / Affected

**Candidate:** MOZI, `hash-20`.  
**Affected:** Six reference `CryptHash` instances: 384, 512, 768, 1024, 384-XOF, and 512-XOF.

## Bug Description

Each tested successful hash call leaves its padded-message allocation live. Repeated calls can increase process memory use; remote resource exhaustion has not been demonstrated.

## Root Cause Analysis / Evidence

**Source:** [`hash-20/Mozi/Implementations/Reference_Implementation/MOZI-384-XOF/CryptHash_AlgorithmInstance.c:155-197`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/hash-20/Mozi/Implementations/Reference_Implementation/MOZI-384-XOF/CryptHash_AlgorithmInstance.c#L155-L197) (SHA-256 `3f494705e35bd5bfd47f60d7fba57cc213913dbb84bb6ad3992568439ec2d96c`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

`hash_sponge` allocates `padded_input` at line 157 and returns after digest generation at line 197 without freeing it. For a one-bit message, each of the six instances returned status 0, changed the digest, and left one tracked allocation outstanding (`allocations=1`, `frees=0`). The probe releases the allocation after measuring it.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/hash-20-padding-leak

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh hash-20-padding-leak
```

Expected final marker: `hash-20-padding-leak REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/hash-20-padding-leak/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/hash-20-padding-leak/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
