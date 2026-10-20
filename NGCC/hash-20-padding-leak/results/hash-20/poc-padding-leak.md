# PoC: MOZI successful hash leaks padding

Case ID: `hash-20-padding-leak`. [Bug report](bug-report-padding-leak.md).

## Reproduce from the repository root

```bash
bash results/ngcc_119_source_audit/run_hash_padding_leak.sh hash-20
```

Trigger: Hash a one-bit message through each of six reference instances.

Expected observation: Each successful call leaves one tracked padding allocation live.

Control: The harness verifies changed digest bytes and releases the retained allocation after counting.

The command exits successfully only when its probe's expected checks pass. To retain a consolidated command, status and full output log, run:

```bash
python3 results/ngcc_119_source_audit/reproduce_confirmed.py --case hash-20-padding-leak
```

That command writes `results/hash-20/poc-logs/hash-20-padding-leak.log`. Only six reference instances were run; no deployed denial-of-service claim is made.
