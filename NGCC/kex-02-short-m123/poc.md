# Reproducer: AFS-KEX ignores all three incoming message lengths

## Run

From the NGCC package root, download the pinned source and run this finding:

```sh
source/download_targets.sh
tools/reproduce.sh kex-02-short-m123
```

The direct per-bug witness can also run from this bug directory:

```sh
# From this bug directory
NGCC_HARNESS_ROOT=../source/ngcc-harness bash run_ngcc.sh
```

`run.sh` builds the submitted C128, C256, and C512 reference sources with AddressSanitizer and the project-authored probe, then executes all three message modes for each variant. It writes the Git-ignored `reproduction.log`; the lower-level driver retains the full ASan traces under `results/kex-02/build/`. No downloaded candidate binary or submitter build script is required. The [evidence](evidence.md) records the source path and hash.

| Mode | Public API | One-byte trigger | Exact-size control, C128 / C256 / C512 | Expected ASan site |
| --- | --- | --- | --- | --- |
| `m1` | `kex_generate_pass2_msg_b` | `m1_len_bytes = 1` | 768 / 1,440 / 2,944 bytes | KEM decapsulation called at wrapper line 177 |
| `m2` | `kex_generate_pass3_msg_a` | `m2_len_bytes = 1` | 784 / 1,472 / 3,008 bytes | KEM decapsulation called at wrapper line 217 |
| `m3` | `kex_generate_pass4_msg_b` | `m3_len_bytes = 1` | 16 / 32 / 64 bytes | Direct indexed read at wrapper line 264 |

The driver checks that each short input produces an ASan heap-buffer-overflow trace naming its own public function and wrapper source line. Each matching exact-size control must complete without ASan error. An exact-size `m2` control may return an API error because its zeroed state is not a valid transcript; memory safety is the measured relation.

- Exit 0 from either runner: all nine triggers and nine controls passed.
- Direct witness exit 77: pinned source or another dependency unavailable; no finding is claimed from that run.
- Other direct-witness exit: build, setup, or reproduction failure; inspect `reproduction.log` and the retained ASan trace.
- Unified runner exit 1: failed or skipped witness; exit 2: invalid checkout or selection.

This is one proposed GitHub issue covering all three receive-side length checks, with no upstream vulnerability ID assigned.
