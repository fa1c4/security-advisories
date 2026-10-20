# Reproducer: Aigis-Enc+ deterministic encapsulation writes past a stack buffer

## Run

From the NGCC package root, download the pinned source and run this finding:

```sh
source/download_targets.sh
tools/reproduce.sh kem-01-deterministic-encapsulation-oob
```

The direct per-bug witness can also run from this bug directory:

```sh
# From this bug directory
NGCC_HARNESS_ROOT=../source/ngcc-harness bash run_ngcc.sh
```

`run.sh` invokes `results/kem-01/run_poc_deterministic_encapsulation_oob.sh` inside this bundle and writes the Git-ignored `reproduction.log`. It compiles the pinned downloaded candidate source and the project-authored probe; it does not need a downloaded candidate binary. Use the exact source paths and hashes in [detailed evidence](evidence.md) to compare against the official submission.

## Expected result

- Trigger: After valid key generation, all three reference calls produce an AddressSanitizer stack-buffer-overflow.
- Control: The harness constructs a valid public key before invoking the exported function.
- Exit 0 from either runner: both trigger and control checks passed.
- Direct witness exit 77: pinned source or another dependency unavailable; no finding is claimed from that run.
- Other direct-witness exit: build, setup, or reproduction failure; inspect `reproduction.log`.
- Unified runner exit 1: failed or skipped witness; exit 2: invalid checkout or selection.

## Scope

Valid direct helper calls corrupt stack memory. The submitted KAT wrapper does not call mdkem_enc, and remote reachability is unshown. This package is proposed for a GitHub issue, with no upstream vulnerability ID assigned.
