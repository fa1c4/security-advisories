# PoC: NIIKE omits successful output lengths

Case ID: `kex-08-output-lengths`. [Bug report](bug-report-output-lengths.md).

## Reproduce from the repository root

```bash
bash results/kex-08/run_output_length_probe.sh
```

Trigger: Initialize public, private, state and shared-key length outputs to zero or a sentinel.

Expected observation: Both sides write key bytes and return success while length outputs retain the caller's initial values.

Control: Prefilled lengths remain 4160, 196 and 64, explaining why the submitted KAT masks the omission.

The command exits successfully only when its probe's expected checks pass. To retain a consolidated command, status and full output log, run:

```bash
python3 results/ngcc_119_source_audit/reproduce_confirmed.py --case kex-08-output-lengths
```

That command writes `results/kex-08/poc-logs/kex-08-output-lengths.log`. Primitive stubs isolate API bookkeeping and do not establish key agreement.
