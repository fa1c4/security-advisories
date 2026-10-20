# Aigis-Enc+ (kem-01): Deterministic encapsulation overflows its kr buffer

## Candidate / Affected

**Candidate:** Aigis-Enc+, `kem-01`.  
**Affected:** Reference Aigis-Enc+-I, -II, and -III exported `mdkem_enc` functions; optimized variants were not tested.

## Bug Description

A valid direct call to deterministic encapsulation writes past a stack buffer in each tested reference parameter set. AddressSanitizer reports `stack-buffer-overflow`; the submitted KAT wrapper does not call `mdkem_enc`, and remote reachability has not been shown.

## Root Cause Analysis / Evidence

**Source:** [`kem-01/Implementations/Reference_Implementation/Aigis-Enc+-I/kem.c:40-50`](https://github.com/ngcc-dev/ngcc-harness/blob/6a13c278a0cf7a6b4bcee9bc2494932898486c70/kem-01/Implementations/Reference_Implementation/Aigis-Enc+-I/kem.c#L40-L50) (SHA-256 `8916ea6ece9aff6399a513c14ccf14a83df3411fa7acc92beea05d2505e69e69`; pinned `ngcc-harness` commit `6a13c278a0cf7a6b4bcee9bc2494932898486c70`).

`mdkem_enc` declares `kr[SEED_BYTES]` at line 42 but `Hash2(kr, buf, 2*SEED_BYTES)` at line 48 writes `2*SEED_BYTES`; the subsequent `kr + SEED_BYTES` coins pointer is also beyond the declared object. A valid key-generation setup triggers the same ASan failure for I, II, and III. Existing issue [#24](https://github.com/ngcc-dev/ngcc-harness/issues/24) reports a deterministic `mdkem_enc` stack overflow for Aigis-Enc+-I; this kit adds the II/III observations and pinpoints the `Hash2` write. It should be treated as supporting scope and evidence for that issue unless maintainers identify a separate root cause.

## PoC

**PoC:** https://github.com/fa1c4/security-advisories/tree/main/NGCC/kem-01-deterministic-encapsulation-oob

From the `NGCC/` directory:

```sh
./source/download_targets.sh
./tools/reproduce.sh kem-01-deterministic-encapsulation-oob
```

Expected final marker: `kem-01-deterministic-encapsulation-oob REPRODUCER PASS`. Full steps and controls: [PoC notes](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kem-01-deterministic-encapsulation-oob/poc.md), [evidence](https://github.com/fa1c4/security-advisories/blob/main/NGCC/kem-01-deterministic-encapsulation-oob/evidence.md).

## Credit

fa1c4, University of Science and Technology of China
