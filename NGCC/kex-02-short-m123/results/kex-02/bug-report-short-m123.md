# AFS-KEX reads beyond three short protocol messages

**Assessment:** AddressSanitizer reproduced malformed-transcript out-of-bounds
reads at passes two, three and four in all three submitted reference AFS-KEX
variants: C128, C256 and C512. Each public pass function discards the length
of its incoming message. This finding concerns caller-supplied short buffers;
it does not assert an honest key exchange or deployed exploitability.

The reference wrappers share exact source SHA-256
`186fdf2d8e1d4914773c3cea3f11bfdd267c9ade80cfbc9f7a6b740407dfcc14`.
The representative file is
`third_party/kex-02/source/Implementations and Test_Vectors/Implementations/Reference_Implementation/AFS_KEX_C128/KEX_AlgorithmInstance.c`.
The C128 core `kem.c` has SHA-256
`ea2f3a07578a946c030189f35501f65e1a792a9f521d28f47c42292cbfa424cc`;
the C256 and C512 cores are distinct source identities and were built and
probed separately.

| Pass and input | C128 exact bytes | C256 exact bytes | C512 exact bytes | First unsafe use |
| --- | ---: | ---: | ---: | --- |
| `kex_generate_pass2_msg_b`, `m1` | 768 | 1,440 | 2,944 | Wrapper line 177 passes `m1` to fixed-size KEM decapsulation |
| `kex_generate_pass3_msg_a`, `m2` | 784 | 1,472 | 3,008 | Wrapper line 217 passes `m2` to fixed-size KEM decapsulation |
| `kex_generate_pass4_msg_b`, `m3` | 16 | 32 | 64 | Wrapper line 264 directly reads `m3[i]` |

Run `bash results/kex-02/run_short_pass1_probe.sh`. It builds the original
reference source set for each variant with its submitted compatibility flags
and AddressSanitizer. All nine one-byte allocations trigger
`heap-buffer-overflow`; their nine exact-size controls finish without ASan
errors. Some exact-size controls can return an API error because the test
deliberately uses zeroed keys and state. A deterministic DRNG stub isolates
the buffer boundary; these controls do not establish agreement of
cryptographic secrets. The retained logs are under `results/kex-02/build/`.

Each pass must reject a message shorter than its fixed input size before
decapsulation or indexed reads. Review all other discarded input lengths in
this wrapper family under the same boundary rule.
