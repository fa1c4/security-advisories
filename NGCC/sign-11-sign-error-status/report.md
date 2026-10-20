# FlexTree (sign-11): Signed-message API returns success after inner signing failure

## Candidate / Affected

**Candidate:** FlexTree, `sign-11`.  
**Affected:** Reference `crypto_sign`; eight parameter variants share byte-identical `sign.c`, while the PoC executes Flextree-160f.

## Bug Description

When inner signing fails, the public signed-message API still returns 0 and computes its output length from an uninitialized local. The forced-failure PoC demonstrates false success; it does not establish a natural failure rate or forgery.

## Root Cause Analysis / Evidence

**Source:** [`sign-11/Implementations/Reference_Implementation/Flextree-160f/sign.c:277-288`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/sign-11/Implementations/Reference_Implementation/Flextree-160f/sign.c#L277-L288) (SHA-256 `e372574d999c97994e8aecdf983471a617755b8bb9989dc8ed5d17c90c00afe2`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

`crypto_sign_signature` returns `-1` when `hash_message` fails at lines 133-134, before setting the signature length. `crypto_sign` discards that return value at line 283, computes `*smlen` from the uninitialized `siglen` at line 286, and returns 0. The real-hash control signs successfully; the injected hash failure still yields outer status 0. The failed-path length is indeterminate and is not asserted as a stable value.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/sign-11-sign-error-status

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh sign-11-sign-error-status
```

Expected final marker: `sign-11-sign-error-status REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/sign-11-sign-error-status/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/sign-11-sign-error-status/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
