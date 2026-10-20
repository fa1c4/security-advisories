# NGCC issue kit: hash-14-output-length-oob

This candidate-source-free kit contains the project-authored report, PoC, probe, and
runner. It was prepared against `ngcc-harness` commit
`6a13c278a0cf7a6b4bcee9bc2494932898486c70`. It includes no candidate
archive, specification PDF, submitted build script, KAT vector, prebuilt
binary, or IP paperwork. The source hash is checked before the probe runs.

## Run this finding

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh hash-14-output-length-oob
```

The included `.harness-root` selects `source/ngcc-harness` relative to the
package root. It returns 0 only when the trigger and controls pass
and the expected verdict appears. It returns 1 for a failed or skipped witness,
and 2 for an invalid checkout or selection.

## Run this witness directly

From this bug directory:

```sh
NGCC_HARNESS_ROOT=../source/ngcc-harness bash run_ngcc.sh
```

The direct witness returns 0 on a passing trigger and controls, 77 when the
pinned source or another dependency is unavailable, and another nonzero status
for setup, source-identity, build, or reproduction failure. Inspect
`reproduction.log` for the detailed probe output.

The report's credit placeholder must be filled by the discoverer before
posting the GitHub issue. No upstream vulnerability ID or acceptance is
claimed. See [the report](report.md), [reproducer](poc.md), and
[source evidence](evidence.md).
