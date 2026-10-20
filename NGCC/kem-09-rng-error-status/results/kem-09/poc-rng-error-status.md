# PoC: CheetahKEM keygen ignores DRNG failure

Case ID: `kem-09-rng-error-status`. [Bug report](bug-report-rng-error-status.md).

## Reproduce from the NGCC package root

```sh
source/download_targets.sh
tools/reproduce.sh kem-09-rng-error-status
```

Trigger: Return -1 from both DRNG requests after filling deterministic test bytes.

Expected observation: All four reference variants still report keygen status 0 and advertised key lengths.

Control: The successful-RNG control has the same requested lengths; the stub isolates status propagation.

The unified runner exits successfully only when the trigger and control checks pass. Its output is recorded in the Git-ignored `kem-09-rng-error-status/reproduction.log`. The stub fills bytes before error; it does not model the contents of a real failed DRNG.
