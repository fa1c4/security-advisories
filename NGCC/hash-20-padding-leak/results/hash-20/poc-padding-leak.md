# PoC: MOZI successful hash leaks padding

Case ID: `hash-20-padding-leak`. [Bug report](bug-report-padding-leak.md).

## Reproduce from the NGCC package root

```sh
source/download_targets.sh
tools/reproduce.sh hash-20-padding-leak
```

Trigger: Hash a one-bit message through each of six reference instances.

Expected observation: Each successful call leaves one tracked padding allocation live.

Control: The harness verifies changed digest bytes and releases the retained allocation after counting.

The unified runner exits successfully only when the trigger and control checks pass. Its output is recorded in the Git-ignored `hash-20-padding-leak/reproduction.log`. Only six reference instances were run; no deployed denial-of-service claim is made.
