# Laurus-XOF (hash-14): CryptHash writes 256 bytes for shorter output requests

## Candidate / Affected

**Candidate:** Laurus-XOF, `hash-14`.  
**Affected:** Reference `CryptHash` wrapper; 512- and 1024-bit output requests.

## Bug Description

A caller that requests a 512- or 1024-bit digest may allocate exactly 64 or 128 output bytes, but `CryptHash` writes the full 2048-bit (256-byte) output and returns success. AddressSanitizer confirms out-of-bounds writes with exact-size buffers. Exploitability and deployed exposure have not been established.

## Root Cause Analysis / Evidence

**Source:** [`hash-14/Laurus/Implementations/Reference_Implementation/Laurus-XOF/CryptHash_AlgorithmInstance.c:14-17`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/hash-14/Laurus/Implementations/Reference_Implementation/Laurus-XOF/CryptHash_AlgorithmInstance.c#L14-L17) (SHA-256 `01fe50b80ba0a9130f7b557926b68ccb9d79ab3a2663aaa63067f22f20042e6b`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

The public wrapper discards `digest_len_bits` and passes the fixed `DIGEST_BIT_LENGTH = 2048` to `laurus_xof`; the core itself accepts the requested length. A one-bit input produces a matching requested prefix but modifies 191 bytes beyond the 512-bit request or 128 bytes beyond the 1024-bit request in the guarded check. Exact 64- and 128-byte wrapper buffers trigger ASan `heap-buffer-overflow`; direct short core calls and a 2048-bit wrapper call are memory-safe controls.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/hash-14-output-length-oob

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh hash-14-output-length-oob
```

Expected final marker: `hash-14-output-length-oob REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/hash-14-output-length-oob/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/hash-14-output-length-oob/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
