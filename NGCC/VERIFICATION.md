# Verification record

Date: 2026-10-20 UTC.

Design requirements: `designs/architecture.md` DES-01 and DES-04 require exact
source provenance and retained control evidence; DES-02 and DES-05 require a
missing or failed check to remain distinct from a passing finding. This
workspace is an export of candidate evidence and does not change the PQCFuzz
campaign or promotion pipeline.

Observed checks, with commands expressed relative to this NGCC package root:

| Command or check | Result |
| --- | --- |
| `make -C tools` | Built the exact copied `ngcc_attack.c` with the exact copied ABI headers; compiler emitted two upstream `-Wclobbered` warnings. |
| `tools/reproduce.sh --list` | Listed 15 finding IDs. |
| `tools/reproduce.sh` | `SUMMARY selected=15 passed=15 failed=0 skipped=0` against the clean pinned ngcc-harness checkout. Each per-bug witness emitted its local `ATTACK <target-bug> CONFIRMED` marker after its trigger and control checks. |
| `tools/reproduce.sh kex-02` | `SUMMARY selected=1 passed=1 failed=0 skipped=0`; the merged `m1`/`m2`/`m3` witness passed its nine trigger/control pairs. |
| `tools/reproduce.sh kex-02-short-m123` after adding the pinned-commit guard | `SUMMARY selected=1 passed=1 failed=0 skipped=0`. |
| `NGCC_HARNESS_ROOT=/path/that/does-not-exist tools/reproduce.sh kex-02-short-m123` | Rejected the invalid checkout with exit 2. |
| `tools/reproduce.sh does-not-exist` | Rejected the unknown selection with exit 2. |
| Upstream source comparison | `ngcc_attack.c`, its Makefile, the retained upstream runner, and all four copied ABI headers matched the source checkout byte for byte. |

The 15 checks were also previously invoked independently from the staged
candidate-local layout. The aggregate test above verifies the package paths
and dispatcher. It does not exercise
the unchanged upstream `tools/upstream/reproduce.sh`, establish acceptance by
ngcc.dev, or validate against a different upstream commit.

## Copied package verification

The complete package was copied to `/home/zym/security-advisories/NGCC/` on
2026-10-20 UTC. From that directory, `tools/reproduce.sh` returned 0 with
`SUMMARY selected=15 passed=15 failed=0 skipped=0`. The copied local
`.harness-root` selected the same pinned checkout. The destination repository
recognized 195 publishable untracked files after the run; its `.gitignore`
excluded all 15 generated source links, the generated native binaries, logs,
and the local checkout selector. No official upstream issue ID was assigned.

## Documentation synchronization audit

On 2026-10-20 UTC, the 15 report, PoC, README, and evidence documents were
updated to name the package-local `tools/reproduce.sh <target-bug>` entry point,
identify the direct witness separately, and distinguish their exit statuses.
Legacy PQCFuzz-root commands in the exported evidence were replaced with
package-local paths. The historical `original-poc.md` files were later removed
from the report packages.

After copying the updates to `/home/zym/security-advisories/NGCC/`, all 196
source-package files matched byte for byte. Every one of the 60 current
per-bug documents named its own unified-runner command, and their local
Markdown links resolved. `tools/reproduce.sh --list` listed the same 15 IDs
from both directories. The full 15-witness run was not repeated after this
documentation-only update; the earlier copied-package result above remains
the last runtime check.

## Download-first workflow verification

On 2026-10-20 UTC, the advisory package was changed to use the package-included
`source/download_targets.sh` and `.harness-root` containing the
relative path `source/ngcc-harness`. The checkout under that path is ignored
by the advisory repository. The default download URL is the upstream
`ngcc-dev/ngcc-harness` repository; the script pins commit
`6a13c278a0cf7a6b4bcee9bc2494932898486c70` and selects all 15
candidate directories.

The existing pinned local checkout was copied into the ignored destination
for offline verification. Running `source/download_targets.sh` then returned
`Source ready` and validated its commit, candidate directories, and clean
candidate paths. A separate synthetic local Git remote exercised both a fresh
sparse clone and a repeat invocation; both returned 0 and materialized all 15
candidate directories. The actual GitHub download was not run because this
environment could not resolve `github.com`.

All 15 old generated source links and 13 generated build directories were
removed before the full run. With `NGCC_HARNESS_ROOT` unset, the package-local
`tools/reproduce.sh` returned 0 with
`SUMMARY selected=15 passed=15 failed=0 skipped=0`. Each of the 15 newly
generated source links resolved inside this package's ignored
`source/ngcc-harness/`; none resolved to the previous temporary checkout.
The advisory repository listed 197 publishable package files, while Git
ignored the downloaded source checkout.
