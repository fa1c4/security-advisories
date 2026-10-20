# CheetahKEM keygen ignores DRNG failure

**Assessment:** Directly reproduced source-level implementation defect. The finding remains candidate-only under `designs/architecture.md` DES-01, DES-04 and DES-05.

## Exact source and cause

`third_party/kem-09/source/Implementations/Reference_Implementation/Cheetah128/KEM_Cheetah.c` (SHA-256 `1f75a39ebe4000aef5abe3d1ee150fa9aeb043f609a45a1189f36a47a65e1ca3`).

indcpa_keypair and kem_keygen consume DRNG output without propagating the failure status.

The broader investigation and variant details are in [the shared evidence report](../ngcc_119_source_audit/bug-report-kem09-kem18-rng-status.md).

## Reproduction

Run the [dedicated PoC](poc-rng-error-status.md). Trigger: Return -1 from both DRNG requests after filling deterministic test bytes. Observed result: All four reference variants still report keygen status 0 and advertised key lengths. Control: The successful-RNG control has the same requested lengths; the stub isolates status propagation.

## Scope and repair gate

The stub fills bytes before error; it does not model the contents of a real failed DRNG. A repair must remove the described source-level failure while preserving the control result; rerun this PoC and the target's submitted tests. No source under `third_party/` was modified for this reproduction.
