# PoC: FlexTree signed-message API masks signing failure

Case ID: `sign-11-sign-error-status`. [Bug report](bug-report-sign-error-status.md).

## Reproduce from the NGCC package root

```sh
source/download_targets.sh
tools/reproduce.sh sign-11-sign-error-status
```

Trigger: Force hash_message to fail inside crypto_sign_signature.

Expected observation: The outer crypto_sign still reports status 0 and uses an uninitialized signature length.

Control: The real-hash control signs successfully; the probe checks status and message copying, not the unstable garbage length.

The unified runner exits successfully only when the trigger and control checks pass. Its output is recorded in the Git-ignored `sign-11-sign-error-status/reproduction.log`. One reference variant was run; eight share the exact sign.c hash.
