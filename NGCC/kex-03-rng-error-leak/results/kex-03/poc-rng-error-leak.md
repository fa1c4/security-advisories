# PoC: CreTAKE RNG-error path leaks two allocations

Case ID: `kex-03-rng-error-leak`. [Bug report](bug-report-rng-error-leak.md).

## Reproduce from the NGCC package root

```sh
source/download_targets.sh
tools/reproduce.sh kex-03-rng-error-leak
```

Trigger: Force randombytes to report failure after pkb and tpk are allocated.

Expected observation: The wrapper returns -1 with two target allocations live.

Control: Successful RNG control returns after both allocations are freed; the harness releases retained blocks after counting.

The unified runner exits successfully only when the trigger and control checks pass. Its output is recorded in the Git-ignored `kex-03-rng-error-leak/reproduction.log`. This is an injected error path in one reference K2S instance.
