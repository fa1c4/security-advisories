# MOZI successful hash leaks padding

**Assessment:** Directly reproduced source-level implementation defect. The finding remains candidate-only under `designs/architecture.md` DES-01, DES-04 and DES-05.

## Exact source and cause

`third_party/hash-20/source/Mozi/Implementations/Reference_Implementation/MOZI-384-XOF/CryptHash_AlgorithmInstance.c` (SHA-256 `3f494705e35bd5bfd47f60d7fba57cc213913dbb84bb6ad3992568439ec2d96c`).

The sponge allocates padded_input and returns after digest generation without freeing it.

The broader investigation and variant details are in [the shared evidence report](../ngcc_119_source_audit/bug-report-hash18-hash20-padding-leaks.md).

## Reproduction

Run the [dedicated PoC](poc-padding-leak.md). Trigger: Hash a one-bit message through each of six reference instances. Observed result: Each successful call leaves one tracked padding allocation live. Control: The harness verifies changed digest bytes and releases the retained allocation after counting.

## Scope and repair gate

Only six reference instances were run; no deployed denial-of-service claim is made. A repair must remove the described source-level failure while preserving the control result; rerun this PoC and the target's submitted tests. No source under `third_party/` was modified for this reproduction.
