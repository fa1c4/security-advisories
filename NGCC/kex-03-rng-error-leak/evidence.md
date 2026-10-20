# CreTAKE RNG-error path leaks two allocations

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh kex-03-rng-error-leak
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `kex-03-rng-error-leak/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Assessment:** Directly reproduced source-level implementation defect. The finding remains candidate-only under `designs/architecture.md` DES-01, DES-04 and DES-05.

## Exact source and cause

`third_party/kex-03/source/Implementations/Reference_Implementation/CreTAKE128/CreTAKE-K2S-ZEN128-BiT128/KEX_AlgorithmInstance.c` (SHA-256 `486a2c42be4d9b62c6820da679212c52340083313694d1c10657b6bc84e675ed`).

kex_generate_pass2_msg_b returns from its RNG-error branch before freeing pkb and tpk.

The broader investigation and variant details are in [the shared evidence report](results/kex-03/bug-report-short-pass1.md).

## Reproduction

The [dedicated PoC description](results/kex-03/poc-rng-error-leak.md). Trigger: Force randombytes to report failure after pkb and tpk are allocated. Observed result: The wrapper returns -1 with two target allocations live. Control: Successful RNG control returns after both allocations are freed; the harness releases retained blocks after counting.

## Scope and repair gate

This is an injected error path in one reference K2S instance. A repair must remove the described source-level failure while preserving the control result; rerun this PoC and the target's submitted tests. No source under `third_party/` was modified for this reproduction.
