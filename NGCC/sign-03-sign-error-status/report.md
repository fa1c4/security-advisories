# CEDRUS+C (sign-03): Signed-message API returns success after inner signing failure

## Candidate / Affected

**Candidate:** CEDRUS+C, `sign-03`.  
**Affected:** Reference `crypto_sign`; eight parameter variants share byte-identical `sign.c`, while the PoC executes CEDRUSC-160f.

## Bug Description

When inner signing fails, the public signed-message API still returns 0 and computes its output length from an uninitialized local. The forced-failure PoC demonstrates false success; it does not establish a natural failure rate or forgery.

## Root Cause Analysis / Evidence

**Source:** [`sign-03/Implementations/Reference_Implementation/CEDRUSC-160f/sign.c:285-292`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/sign-03/Implementations/Reference_Implementation/CEDRUSC-160f/sign.c#L285-L292) (SHA-256 `3529d1d2fb6a117fa19f413af6c910fb7c130b9a99adc1bcbe505a2027937a3d`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

`crypto_sign_signature` returns `-1` when `hash_message` fails at lines 132-133, before assigning `*siglen` at line 173. `crypto_sign` ignores that status at lines 285-292, uses the uninitialized `siglen`, and returns 0. The real-hash control returned status 0 with a normal signed length; the injected hash failure also returned status 0. The observed failed-path length is indeterminate and is not asserted as a stable value.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/sign-03-sign-error-status

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh sign-03-sign-error-status
```

Expected final marker: `sign-03-sign-error-status REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/sign-03-sign-error-status/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/sign-03-sign-error-status/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
