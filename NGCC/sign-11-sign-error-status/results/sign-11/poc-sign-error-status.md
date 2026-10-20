# PoC: FlexTree signed-message API masks signing failure

Case ID: `sign-11-sign-error-status`. [Bug report](bug-report-sign-error-status.md).

## Reproduce from the repository root

```bash
bash results/sign-11/run_sign_failure_probe.sh
```

Trigger: Force hash_message to fail inside crypto_sign_signature.

Expected observation: The outer crypto_sign still reports status 0 and uses an uninitialized signature length.

Control: The real-hash control signs successfully; the probe checks status and message copying, not the unstable garbage length.

The command exits successfully only when its probe's expected checks pass. To retain a consolidated command, status and full output log, run:

```bash
python3 results/ngcc_119_source_audit/reproduce_confirmed.py --case sign-11-sign-error-status
```

That command writes `results/sign-11/poc-logs/sign-11-sign-error-status.log`. One reference variant was run; eight share the exact sign.c hash.
