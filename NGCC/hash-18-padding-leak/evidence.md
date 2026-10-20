# MEGASCON successful hash leaks padding

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh hash-18-padding-leak
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `hash-18-padding-leak/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Assessment:** Directly reproduced source-level implementation defect. The finding remains candidate-only under `designs/architecture.md` DES-01, DES-04 and DES-05.

## Exact source and cause

`third_party/hash-18/source/Megascon/Implementations/Reference_Implementation/MEGASCON-384-XOF/CryptHash_AlgorithmInstance.c` (SHA-256 `57a4a46df1f3b2f54bba4f87f7d87ed610f602bfa8ada00630f3d5993594f727`).

The sponge allocates padded_input and returns after digest generation without freeing it.

The broader investigation and variant details are in [the shared evidence report](results/ngcc_119_source_audit/bug-report-hash18-hash20-padding-leaks.md).

## Reproduction

The [dedicated PoC description](results/hash-18/poc-padding-leak.md). Trigger: Hash a one-bit message through each of six reference instances. Observed result: Each successful call leaves one tracked padding allocation live. Control: The harness verifies changed digest bytes and releases the retained allocation after counting.

## Scope and repair gate

Only six reference instances were run; no deployed denial-of-service claim is made. A repair must remove the described source-level failure while preserving the control result; rerun this PoC and the target's submitted tests. No source under `third_party/` was modified for this reproduction.
