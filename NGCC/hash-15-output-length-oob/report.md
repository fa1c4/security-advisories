# Litchi-XOF (hash-15): CryptHash writes 128 bytes for shorter output requests

## Candidate / Affected

**Candidate:** Litchi-XOF, `hash-15`.  
**Affected:** Reference `CryptHash` wrapper; 512- and 768-bit output requests.

## Bug Description

A caller that requests 512 or 768 digest bits may allocate 64 or 96 output bytes, but `CryptHash` writes the full 1024-bit (128-byte) output and returns success. AddressSanitizer confirms out-of-bounds writes with exact-size buffers. Exploitability and deployed exposure have not been established.

## Root Cause Analysis / Evidence

**Source:** [`hash-15/Litchi/Implementations/Reference_Implementation/Litchi-XOF/CryptHash_AlgorithmInstance.c:14-17`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/hash-15/Litchi/Implementations/Reference_Implementation/Litchi-XOF/CryptHash_AlgorithmInstance.c#L14-L17) (SHA-256 `70a3c65c6ee00f3ae54c29eb4229b0ca6b9708a3772958160fd8c5ef6c5d8a7a`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

The wrapper ignores `digest_len_bits` and passes the fixed `DIGEST_BIT_LENGTH = 1024` to `litchi_xof`. A one-bit input changes 64 bytes beyond a 512-bit request and 32 bytes beyond a 768-bit request. Both exact-size wrapper buffers trigger ASan `heap-buffer-overflow`; direct short core calls and the 1024-bit wrapper call are memory-safe controls.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/hash-15-output-length-oob

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh hash-15-output-length-oob
```

Expected final marker: `hash-15-output-length-oob REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/hash-15-output-length-oob/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/hash-15-output-length-oob/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
