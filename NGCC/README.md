# NGCC issue reproducers

This package contains 15 PQCFuzz candidate findings.
The downloaded ngcc-harness checkout is kept under `source/ngcc-harness/` and ignored by the containing Git repository.
Each `<target-bug>/` directory contains one report, a PoC description, the focused witness source, a trigger and control runner, and its evidence.
`kex-02-short-m123` covers three message lengths with one common root cause.

All witnesses target the unmodified `ngcc-harness` checkout at commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`.
Each witness verifies its cited submitted-source SHA-256 before compiling or running.
The 15 observations are local candidates; a printed `CONFIRMED` means only that the stated source-level observation and controls reproduced.
It is not an NGCC acceptance, stable report ID, or automatic PQCFuzz vulnerability promotion.

## Download and run

From this NGCC package root, run the two commands below:

```sh
source/download_targets.sh
tools/reproduce.sh
```

The download script clones `ngcc-dev/ngcc-harness`, checks out pinned commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`, and materializes the 15 candidate directories with sparse checkout.
The downloaded repository lives at `source/ngcc-harness/` and is ignored by Git.
The included `.harness-root` contains the relative path `source/ngcc-harness`, so the runner finds it after the download without an environment variable.
Running the download script again verifies the existing checkout and leaves it in place.

To run one finding or inspect the available IDs:

```sh
tools/reproduce.sh kex-02-short-m123
tools/reproduce.sh --list
```

`NGCC_HARNESS_ROOT` remains an optional override for a different clean checkout of the pinned commit.
The runner exits 0 only when every selected witness returns success and its expected `ATTACK <target-bug> CONFIRMED` marker.
An unavailable source or dependency prints `SKIP` and makes the aggregate run fail; any other witness failure also makes it fail.
Each witness runs its own negative controls.
Build products and `reproduction.log` stay inside the corresponding bug directory.

## Relationship to ngcc-harness

The exact upstream `tools/reproduce.sh` is preserved at `tools/upstream/reproduce.sh`.
The executable `tools/reproduce.sh` here adapts its `run_target` and aggregate exit-status pattern to these 15 independent candidate-local witnesses.
It is PQCFuzz-authored, not the upstream runner.
The original `tools/ngcc_attack.c`, `tools/Makefile`, and the four link ABI headers are copied without edits from the same upstream commit; see `UPSTREAM_SNAPSHOT.md`.
Build the attack tool with `make -C tools` from this NGCC package root.
None of these 15 checks is an existing `ngcc_attack` subcommand, so the aggregate calls the source-built witnesses directly.
The copied upstream runner requires the full upstream repository and is retained for comparison, not as an entry point for this workspace.

The original PQCFuzz evidence remains in its research archive for provenance.
This workspace does not alter `ngcc-harness`'s maintained `security/vulnerabilities.csv`, candidate Makefiles, or upstream `tools/reproduce.sh`.
