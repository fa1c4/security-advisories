# PoC: CreTAKE RNG-error path leaks two allocations

Case ID: `kex-03-rng-error-leak`. [Bug report](bug-report-rng-error-leak.md).

## Reproduce from the repository root

```bash
bash results/kex-03/run_short_pass1_probe.sh rng
```

Trigger: Force randombytes to report failure after pkb and tpk are allocated.

Expected observation: The wrapper returns -1 with two target allocations live.

Control: Successful RNG control returns after both allocations are freed; the harness releases retained blocks after counting.

The command exits successfully only when its probe's expected checks pass. To retain a consolidated command, status and full output log, run:

```bash
python3 results/ngcc_119_source_audit/reproduce_confirmed.py --case kex-03-rng-error-leak
```

That command writes `results/kex-03/poc-logs/kex-03-rng-error-leak.log`. This is an injected error path in one reference K2S instance.
