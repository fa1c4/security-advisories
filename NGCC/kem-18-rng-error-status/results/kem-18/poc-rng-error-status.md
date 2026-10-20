# PoC: LoongKEM keygen ignores DRNG failure

Case ID: `kem-18-rng-error-status`. [Bug report](bug-report-rng-error-status.md).

## Reproduce from the repository root

```bash
bash results/ngcc_119_source_audit/run_kem_rng_failure_probe.sh kem-18
```

Trigger: Return -1 from both DRNG requests after filling deterministic test bytes.

Expected observation: All four reference variants still report keygen status 0 and advertised key lengths.

Control: The successful-RNG control has the same requested lengths; the stub isolates status propagation.

The command exits successfully only when its probe's expected checks pass. To retain a consolidated command, status and full output log, run:

```bash
python3 results/ngcc_119_source_audit/reproduce_confirmed.py --case kem-18-rng-error-status
```

That command writes `results/kem-18/poc-logs/kem-18-rng-error-status.log`. The stub fills bytes before error; it does not model the contents of a real failed DRNG.
