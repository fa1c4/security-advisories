# FlexTree signed-message API masks signing failure

**Assessment:** Directly reproduced source-level implementation defect. The finding remains candidate-only under `designs/architecture.md` DES-01, DES-04 and DES-05.

## Exact source and cause

`third_party/sign-11/source/Implementations/Reference_Implementation/Flextree-160f/sign.c` (SHA-256 `e372574d999c97994e8aecdf983471a617755b8bb9989dc8ed5d17c90c00afe2`).

crypto_sign ignores the inner signing status, computes smlen from an uninitialized siglen and returns success.

The broader investigation and variant details are in [the shared evidence report](bug-report-sign-status-and-xof-leak.md).

## Reproduction

Run the [dedicated PoC](poc-sign-error-status.md). Trigger: Force hash_message to fail inside crypto_sign_signature. Observed result: The outer crypto_sign still reports status 0 and uses an uninitialized signature length. Control: The real-hash control signs successfully; the probe checks status and message copying, not the unstable garbage length.

## Scope and repair gate

One reference variant was run; eight share the exact sign.c hash. A repair must remove the described source-level failure while preserving the control result; rerun this PoC and the target's submitted tests. No source under `third_party/` was modified for this reproduction.
