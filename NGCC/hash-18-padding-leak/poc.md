# Reproducer: MEGASCON successful hashes retain the padding allocation

## Run

From the NGCC package root, download the pinned source and run this finding:

```sh
source/download_targets.sh
tools/reproduce.sh hash-18-padding-leak
```

The direct per-bug witness can also run from this bug directory:

```sh
# From this bug directory
NGCC_HARNESS_ROOT=../source/ngcc-harness bash run_ngcc.sh
```

`run.sh` invokes `results/ngcc_119_source_audit/run_hash_padding_leak.sh` inside this bundle and writes the Git-ignored `reproduction.log`. It compiles the pinned downloaded candidate source and the project-authored probe; it does not need a downloaded candidate binary. Use the exact source paths and hashes in [detailed evidence](evidence.md) to compare against the official submission.

## Expected result

- Trigger: Each successful one-bit-message call leaves one tracked padding allocation live.
- Control: The probe verifies changed digest bytes and releases the retained block after counting.
- Exit 0 from either runner: both trigger and control checks passed.
- Direct witness exit 77: pinned source or another dependency unavailable; no finding is claimed from that run.
- Other direct-witness exit: build, setup, or reproduction failure; inspect `reproduction.log`.
- Unified runner exit 1: failed or skipped witness; exit 2: invalid checkout or selection.

## Scope

Repeated use can consume process memory; remote resource exhaustion has not been demonstrated. This package is proposed for a GitHub issue, with no upstream vulnerability ID assigned.
