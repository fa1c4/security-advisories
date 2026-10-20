# NIIKE omits successful output lengths

**Assessment:** Directly reproduced source-level implementation defect. The finding remains candidate-only under `designs/architecture.md` DES-01, DES-04 and DES-05.

## Exact source and cause

`third_party/kex-08/source/Implementations and Test_Vectors/Implementations/Reference_Implementation/ngccapi/KEX_AlgorithmInstance.c` (SHA-256 `0d6ac6afb881ba4008697eb51161573a9a87118012ad2a47061e863c7a9b2fbd`).

kex_init_a/b and kex_derive_ss_a/b never assign their documented output-length pointers before returning success.

The broader investigation and variant details are in [the shared evidence report](bug-report-output-lengths.md).

## Reproduction

Run the [dedicated PoC](poc-output-lengths.md). Trigger: Initialize public, private, state and shared-key length outputs to zero or a sentinel. Observed result: Both sides write key bytes and return success while length outputs retain the caller's initial values. Control: Prefilled lengths remain 4160, 196 and 64, explaining why the submitted KAT masks the omission.

## Scope and repair gate

Primitive stubs isolate API bookkeeping and do not establish key agreement. A repair must remove the described source-level failure while preserving the control result; rerun this PoC and the target's submitted tests. No source under `third_party/` was modified for this reproduction.
