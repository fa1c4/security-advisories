# FlexTree signed-message API masks signing failure

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh sign-11-sign-error-status
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `sign-11-sign-error-status/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Assessment:** Directly reproduced source-level implementation defect. The finding remains candidate-only under `designs/architecture.md` DES-01, DES-04 and DES-05.

## Exact source and cause

`third_party/sign-11/source/Implementations/Reference_Implementation/Flextree-160f/sign.c` (SHA-256 `e372574d999c97994e8aecdf983471a617755b8bb9989dc8ed5d17c90c00afe2`).

crypto_sign ignores the inner signing status, computes smlen from an uninitialized siglen and returns success.

The broader investigation and variant details are in [the shared evidence report](results/sign-11/bug-report-sign-status-and-xof-leak.md).

## Reproduction

The [dedicated PoC description](results/sign-11/poc-sign-error-status.md). Trigger: Force hash_message to fail inside crypto_sign_signature. Observed result: The outer crypto_sign still reports status 0 and uses an uninitialized signature length. Control: The real-hash control signs successfully; the probe checks status and message copying, not the unstable garbage length.

## Scope and repair gate

One reference variant was run; eight share the exact sign.c hash. A repair must remove the described source-level failure while preserving the control result; rerun this PoC and the target's submitted tests. No source under `third_party/` was modified for this reproduction.
