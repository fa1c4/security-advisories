# NEV-AKE (kex-07): A short responder message causes an out-of-bounds read

## Candidate / Affected

**Candidate:** NEV-AKE, `kex-07`.  
**Affected:** NEV-AKE-C1-c reference `kex_derive_ss_a` with `PARAMS=1`; the wrapper and core are byte-identical across nine reference parameter directories, but only C1-c was executed.

## Bug Description

The initiator accepts a one-byte responder message and passes it into code that reads a fixed 1127-byte message. AddressSanitizer reports a heap out-of-bounds read. The PoC tests the memory boundary with zeroed state; it does not establish a successful exchange or disclosure.

## Root Cause Analysis / Evidence

**Source:** [`kex-07/Implementations and Test_Vectors/Implementations/Reference_Implementation/NEV-AKE-C1-c/kat_test/KEX_AlgorithmInstance.c:158-173`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/kex-07/Implementations%20and%20Test_Vectors/Implementations/Reference_Implementation/NEV-AKE-C1-c/kat_test/KEX_AlgorithmInstance.c#L158-L173) (SHA-256 `563d9027b58c44d5ce987e7b70802766105646bd6e563a59f735fbfe764da40c`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

The wrapper discards `mb_len_bytes` and calls `ake_der_init` at line 172. In the same source tree, `ake.c:205` forwards the fixed-length message to `ake_pke_decrypt_verify`, which reads the ciphertext at `ake.c:103`. The one-byte allocation triggers ASan `heap-buffer-overflow`; an exact 1127-byte buffer finishes without an ASan error under the same state setup.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/kex-07-short-pass2

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh kex-07-short-pass2
```

Expected final marker: `kex-07-short-pass2 REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kex-07-short-pass2/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kex-07-short-pass2/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
