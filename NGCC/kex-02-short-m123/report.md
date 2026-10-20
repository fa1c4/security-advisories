# AFS-KEX (kex-02): Three incoming message lengths are ignored before fixed-size reads

## Candidate / Affected

**Candidate:** AFS-KEX, `kex-02`.  
**Affected:** C128, C256, and C512 reference implementations; receive-side passes two, three, and four.

## Bug Description

The three public receive-side functions accept an incoming message length but discard it before reading a fixed-size message. A one-byte input causes an AddressSanitizer `heap-buffer-overflow` at each protocol stage in all three parameter sets. This is one length-validation root cause recurring at three passes; no secret disclosure or deployed exploit has been demonstrated.

## Root Cause Analysis / Evidence

**Source:** [`kex-02/Implementations and Test_Vectors/Implementations/Reference_Implementation/AFS_KEX_C128/KEX_AlgorithmInstance.c:168-265`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/kex-02/Implementations%20and%20Test_Vectors/Implementations/Reference_Implementation/AFS_KEX_C128/KEX_AlgorithmInstance.c#L168-L265) (SHA-256 `186fdf2d8e1d4914773c3cea3f11bfdd267c9ade80cfbc9f7a6b740407dfcc14`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

The byte-identical reference wrappers discard `m1_len_bytes` at line 174, `m2_len_bytes` at line 214, and `m3_len_bytes` at line 257. The unsafe uses follow at lines 177, 217, and 264 respectively. Required bytes for C128/C256/C512 are `m1`: 768/1440/2944, `m2`: 784/1472/3008, and `m3`: 16/32/64. The combined PoC produces nine one-byte ASan traces and nine exact-size controls without ASan errors. The zeroed state and deterministic random stub isolate the buffer boundary; control return codes do not establish successful key exchange.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/kex-02-short-m123

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh kex-02-short-m123
```

Expected final marker: `kex-02-short-m123 REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kex-02-short-m123/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kex-02-short-m123/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
