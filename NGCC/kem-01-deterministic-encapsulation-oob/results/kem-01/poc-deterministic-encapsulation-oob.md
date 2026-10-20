# PoC: Aigis-Enc+ deterministic encapsulation stack overflow

Run `./results/kem-01/run_poc_deterministic_encapsulation_oob.sh` from the repository root. The target [Makefile](Makefile) compiles the exact registered reference sources for Aigis-Enc+-I, -II and -III with AddressSanitizer. [input_seed.bin](input_seed.bin) contains the fixed 32-byte DRNG seed; the same bytes are passed as the deterministic `rnd` argument. The [harness](aigis_harness.c) first generates a valid public key, then calls the exported `mdkem_enc` function.

Expected result for each parameter set: the script exits zero after finding `ERROR: AddressSanitizer: stack-buffer-overflow` and `mdkem_enc` in `logs/detenc_{I,II,III}.log`. `mdkem_enc` in `third_party/kem-01/source/Implementations/Reference_Implementation/Aigis-Enc+-I/kem.c:42-50` allocates `kr[SEED_BYTES]` but `Hash2` writes `2*SEED_BYTES` into it. The three submitted reference `kem.c` files are byte-identical. A nonzero script exit means the expected defect was not reproduced.

This PoC establishes an out-of-bounds write on this exported function and valid public-key setup; it does not demonstrate remote exploitability. `mdkem_enc` is not called by the submitted KAT wrapper.
