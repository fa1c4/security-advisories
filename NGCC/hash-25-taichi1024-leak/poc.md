# Reproducer: TaiChi-1024 successful hashes retain the padding allocation

## Run

From the NGCC package root, download the pinned source and run this finding:

```sh
source/download_targets.sh
tools/reproduce.sh hash-25-taichi1024-leak
```

The direct per-bug witness can also run from this bug directory:

```sh
# From this bug directory
NGCC_HARNESS_ROOT=../source/ngcc-harness bash run_ngcc.sh
```

`run.sh` invokes `results/hash-25/run_taichi1024_leak.sh` inside this bundle and writes the Git-ignored `reproduction.log`. It compiles the pinned downloaded candidate source and the project-authored probe; it does not need a downloaded candidate binary. Use the exact source paths and hashes in [detailed evidence](evidence.md) to compare against the official submission.

## Expected result

- Trigger: A valid message returns success and changes the digest while one allocation remains live.
- Control: The allocation tracker releases the block after counting; the 512/768 sibling sources free theirs.
- Exit 0 from either runner: both trigger and control checks passed.
- Direct witness exit 77: pinned source or another dependency unavailable; no finding is claimed from that run.
- Other direct-witness exit: build, setup, or reproduction failure; inspect `reproduction.log`.
- Unified runner exit 1: failed or skipped witness; exit 2: invalid checkout or selection.

## Scope

Repeated use can consume process memory; remote resource exhaustion has not been demonstrated. This package is proposed for a GitHub issue, with no upstream vulnerability ID assigned.
