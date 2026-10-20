# PoC: NIIKE omits successful output lengths

Case ID: `kex-08-output-lengths`. [Bug report](bug-report-output-lengths.md).

## Reproduce from the NGCC package root

```sh
source/download_targets.sh
tools/reproduce.sh kex-08-output-lengths
```

Trigger: Initialize public, private, state and shared-key length outputs to zero or a sentinel.

Expected observation: Both sides write key bytes and return success while length outputs retain the caller's initial values.

Control: Prefilled lengths remain 4160, 196 and 64, explaining why the submitted KAT masks the omission.

The unified runner exits successfully only when the trigger and control checks pass. Its output is recorded in the Git-ignored `kex-08-output-lengths/reproduction.log`. Primitive stubs isolate API bookkeeping and do not establish key agreement.
