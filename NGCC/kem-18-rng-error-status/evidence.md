# LoongKEM keygen ignores DRNG failure

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh kem-18-rng-error-status
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `kem-18-rng-error-status/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Assessment:** Directly reproduced source-level implementation defect. The finding remains candidate-only under `designs/architecture.md` DES-01, DES-04 and DES-05.

## Exact source and cause

`third_party/kem-18/source/Implementations/Reference_Implementation/Loong128/KEM_Loong.c` (SHA-256 `7a3610ee965fe041caa72c39f96ac55045eaaef808a34ba80fbc9585835ac38a`).

indcpa_keypair and kem_keygen consume DRNG output without propagating the failure status.

The broader investigation and variant details are in [the shared evidence report](results/ngcc_119_source_audit/bug-report-kem09-kem18-rng-status.md).

## Reproduction

The [dedicated PoC description](results/kem-18/poc-rng-error-status.md). Trigger: Return -1 from both DRNG requests after filling deterministic test bytes. Observed result: All four reference variants still report keygen status 0 and advertised key lengths. Control: The successful-RNG control has the same requested lengths; the stub isolates status propagation.

## Scope and repair gate

The stub fills bytes before error; it does not model the contents of a real failed DRNG. A repair must remove the described source-level failure while preserving the control result; rerun this PoC and the target's submitted tests. No source under `third_party/` was modified for this reproduction.
