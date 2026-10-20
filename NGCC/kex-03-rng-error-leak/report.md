# CreTAKE (kex-03): Pass-two RNG failure leaks two allocations

## Candidate / Affected

**Candidate:** CreTAKE, `kex-03`.  
**Affected:** Reference CreTAKE-K2S-ZEN128-BiT128 responder, `kex_generate_pass2_msg_b`.

## Bug Description

A forced RNG failure after two responder allocations causes the function to return an error while retaining both allocations. The PoC demonstrates an exceptional-path leak in one reference instance; repeated failure and deployed impact have not been measured.

## Root Cause Analysis / Evidence

**Source:** [`kex-03/Implementations/Reference_Implementation/CreTAKE128/CreTAKE-K2S-ZEN128-BiT128/KEX_AlgorithmInstance.c:121-167`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/kex-03/Implementations/Reference_Implementation/CreTAKE128/CreTAKE-K2S-ZEN128-BiT128/KEX_AlgorithmInstance.c#L121-L167) (SHA-256 `486a2c42be4d9b62c6820da679212c52340083313694d1c10657b6bc84e675ed`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

The function allocates `pkb` and `tpk` at lines 126-127, then returns `-1` at lines 142-144 if `get_random_number` fails; the frees occur only at lines 166-167. The injected error path returned `-1` with two live allocations. The successful-RNG control freed both; the probe releases retained blocks after counting.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/kex-03-rng-error-leak

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh kex-03-rng-error-leak
```

Expected final marker: `kex-03-rng-error-leak REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kex-03-rng-error-leak/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kex-03-rng-error-leak/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
