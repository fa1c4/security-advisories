# Reproducer: NEV-AKE reads beyond a short responder message

## Run

From the NGCC package root, download the pinned source and run this finding:

```sh
source/download_targets.sh
tools/reproduce.sh kex-07-short-pass2
```

The direct per-bug witness can also run from this bug directory:

```sh
# From this bug directory
NGCC_HARNESS_ROOT=../source/ngcc-harness bash run_ngcc.sh
```

`run.sh` invokes `results/kex-07/run_short_pass2_probe.sh` inside this bundle and writes the Git-ignored `reproduction.log`. It compiles the pinned downloaded candidate source and the project-authored probe; it does not need a downloaded candidate binary. Use the exact source paths and hashes in [detailed evidence](evidence.md) to compare against the official submission.

## Expected result

- Trigger: A one-byte responder message yields an AddressSanitizer heap-buffer-overflow read.
- Control: The exact 1127-byte buffer returns without sanitizer error under the same zeroed-state setup.
- Exit 0 from either runner: both trigger and control checks passed.
- Direct witness exit 77: pinned source or another dependency unavailable; no finding is claimed from that run.
- Other direct-witness exit: build, setup, or reproduction failure; inspect `reproduction.log`.
- Unified runner exit 1: failed or skipped witness; exit 2: invalid checkout or selection.

## Scope

The control tests the memory boundary, not successful agreement; disclosure is unshown. This package is proposed for a GitHub issue, with no upstream vulnerability ID assigned.
