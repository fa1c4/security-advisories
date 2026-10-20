# Bug report: Aigis-Enc+ deterministic encapsulation writes beyond `kr`

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh kem-01-deterministic-encapsulation-oob
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `kem-01-deterministic-encapsulation-oob/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Status:** Reproduced implementation bug; security impact remains a candidate. **Affected scope:** `kem-01` reference Aigis-Enc+-I, -II and -III, exported `mdkem_enc` API. The three reference `kem.c` files are byte-identical (SHA-256 `8916ea6ece9aff6399a513c14ccf14a83df3411fa7acc92beea05d2505e69e69`). Optimized implementations were not tested by this PoC.

## Root cause and observed effect

`third_party/kem-01/source/Implementations/Reference_Implementation/Aigis-Enc+-I/kem.c:42-50` declares `uint8_t kr[SEED_BYTES]`, then calls `Hash2(kr, ...)`, whose output length is `2*SEED_BYTES`. The subsequent `kr + SEED_BYTES` coins pointer also points beyond the declared object. A valid key-generation setup followed by `mdkem_enc` produces an AddressSanitizer `stack-buffer-overflow` in all three parameter sets. The -I trace identifies the write through `Hash2` at `kem.c:48` and the `kr` stack object.

## Lower-level PoC

The verified `run_ngcc.sh` entry point invokes `run.sh`, which calls
`results/kem-01/run_poc_deterministic_encapsulation_oob.sh` from this bug directory.

The lower-level script rebuilds the registered reference sources with Clang 14 AddressSanitizer through [Makefile](results/kem-01/Makefile), uses [input_seed.bin](results/kem-01/input_seed.bin) as the deterministic seed, and executes the [harness](results/kem-01/aigis_harness.c). It succeeds only if all I/II/III runs report the expected sanitizer failure. Inspect `results/kem-01/logs/detenc_I.log`, `results/kem-01/logs/detenc_II.log` and `results/kem-01/logs/detenc_III.log`; the script prints `I/detenc`, `II/detenc`, `III/detenc: reproduced` on success. Input SHA-256: `baf3244892dd2bde05e42420386d4bd261f2af5652ffda0a347b3eaa3f004e9c`.

The harness first generates a valid public key, so a malformed public key is not needed. The submitted KAT wrapper does not call `mdkem_enc`; this report establishes the defect in the exported function, not reachability through that wrapper or remote exploitability. [PoC details](results/kem-01/poc-deterministic-encapsulation-oob.md).
