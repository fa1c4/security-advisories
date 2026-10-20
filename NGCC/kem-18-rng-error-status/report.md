# LoongKEM (kem-18): Key generation returns success after DRNG failure

## Candidate / Affected

**Candidate:** LoongKEM, `kem-18`.  
**Affected:** Loong128, Loong256, Loong384, and Loong512 reference key generation.

## Bug Description

Key generation returns status 0 and advertises key lengths even when both injected DRNG requests return `-1`. This demonstrates incorrect error propagation; the stub fills deterministic bytes before returning an error and does not model the output of a real failed DRNG.

## Root Cause Analysis / Evidence

**Source:** [`kem-18/Implementations/Reference_Implementation/Loong128/KEM_Loong.c:220-236`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/kem-18/Implementations/Reference_Implementation/Loong128/KEM_Loong.c#L220-L236) (SHA-256 `7a3610ee965fe041caa72c39f96ac55045eaaef808a34ba80fbc9585835ac38a`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

`indcpa_keypair` reads DRNG output at line 64 without passing a failure to `kem_keygen`; `kem_keygen` requests more random bytes at line 233, then sets output lengths and returns 0 at lines 234-236 without checking the status. All four tested variants returned 0 with and without forced DRNG errors; the successful-DRNG run is the control.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/kem-18-rng-error-status

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh kem-18-rng-error-status
```

Expected final marker: `kem-18-rng-error-status REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kem-18-rng-error-status/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kem-18-rng-error-status/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
