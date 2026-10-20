# CheetahKEM (kem-09): Key generation returns success after DRNG failure

## Candidate / Affected

**Candidate:** CheetahKEM, `kem-09`.  
**Affected:** Cheetah128, Cheetah256, Cheetah384, and Cheetah512 reference key generation.

## Bug Description

Key generation returns status 0 and advertises key lengths even when both injected DRNG requests return `-1`. This demonstrates incorrect error propagation; the stub fills deterministic bytes before returning an error and does not model the output of a real failed DRNG.

## Root Cause Analysis / Evidence

**Source:** [`kem-09/Implementations/Reference_Implementation/Cheetah128/KEM_Cheetah.c:208-224`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/kem-09/Implementations/Reference_Implementation/Cheetah128/KEM_Cheetah.c#L208-L224) (SHA-256 `1f75a39ebe4000aef5abe3d1ee150fa9aeb043f609a45a1189f36a47a65e1ca3`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

`indcpa_keypair` reads DRNG output at line 65 without passing a failure to `kem_keygen`; `kem_keygen` requests more random bytes at line 221, then sets output lengths and returns 0 at lines 222-224 without checking the status. All four tested variants returned 0 with and without forced DRNG errors; the successful-DRNG run is the control.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/kem-09-rng-error-status

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh kem-09-rng-error-status
```

Expected final marker: `kem-09-rng-error-status REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kem-09-rng-error-status/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kem-09-rng-error-status/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
