# Reproducer: Laurus-XOF writes past caller-selected digest buffers

## Run

From the NGCC package root, download the pinned source and run this finding:

```sh
source/download_targets.sh
tools/reproduce.sh hash-14-output-length-oob
```

The direct per-bug witness can also run from this bug directory:

```sh
# From this bug directory
NGCC_HARNESS_ROOT=../source/ngcc-harness bash run_ngcc.sh
```

`run.sh` invokes `results/hash-14/run.sh` inside this bundle and writes the Git-ignored `reproduction.log`. It compiles the pinned downloaded candidate source and the project-authored probe; it does not need a downloaded candidate binary. Use the exact source paths and hashes in [detailed evidence](evidence.md) to compare against the official submission.

## Expected result

- Trigger: A 512-bit request writes 192 bytes beyond its 64-byte buffer; a 1024-bit request writes 128 bytes beyond its 128-byte buffer.
- Control: Exact-size 2048-bit wrapper call and direct 512/1024-bit core calls remain within bounds.
- Exit 0 from either runner: both trigger and control checks passed.
- Direct witness exit 77: pinned source or another dependency unavailable; no finding is claimed from that run.
- Other direct-witness exit: build, setup, or reproduction failure; inspect `reproduction.log`.
- Unified runner exit 1: failed or skipped witness; exit 2: invalid checkout or selection.

## Scope

Caller-controlled output length can corrupt memory. No control-flow takeover or deployed exposure is demonstrated. This package is proposed for a GitHub issue, with no upstream vulnerability ID assigned.
