# CreTAKE responder reads beyond short first message

**Assessment:** Directly reproduced source-level implementation defect. The finding remains candidate-only under `designs/architecture.md` DES-01, DES-04 and DES-05.

## Exact source and cause

`third_party/kex-03/source/Implementations/Reference_Implementation/CreTAKE128/CreTAKE-K2S-ZEN128-BiT128/KEX_AlgorithmInstance.c` (SHA-256 `486a2c42be4d9b62c6820da679212c52340083313694d1c10657b6bc84e675ed`).

Pass two copies TPK_LEN bytes from m1 without validating m1_len_bytes.

## Reproduction

After the package runner has prepared the source link, run the [probe runner](run_short_pass1_probe.sh) with `pass1` from this bug package root: `bash results/kex-03/run_short_pass1_probe.sh pass1`. Trigger: Provide one byte as the first message to the reference K2S responder. Observed result: ASan heap-buffer-overflow at KEX_AlgorithmInstance.c:158. Control: A 615-byte message reaches the same copy without an ASan error.

## Scope and repair gate

The exact wrapper was run with deterministic primitive stubs; other K2S profiles share source patterns but were not independently sanitized. A repair must remove the described source-level failure while preserving the control result; rerun this PoC and the target's submitted tests. No source under `third_party/` was modified for this reproduction.
