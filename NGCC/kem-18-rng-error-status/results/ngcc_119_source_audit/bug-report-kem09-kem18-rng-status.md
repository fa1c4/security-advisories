# CheetahKEM and LoongKEM key generation reports success after RNG failure

**Assessment:** Reproduced ignored RNG failure status in all four submitted
reference key-generation variants of each target. This is an error-reporting
and potentially uninitialized-seed defect. The probe deliberately fills the
requested bytes before returning an error to isolate status propagation; it
does not assert what a real failed DRNG leaves in the output buffer.

| Target | Representative exact source identity | SHA-256 |
| --- | --- | --- |
| `kem-09` | `third_party/kem-09/source/Implementations/Reference_Implementation/Cheetah128/KEM_Cheetah.c` | `1f75a39ebe4000aef5abe3d1ee150fa9aeb043f609a45a1189f36a47a65e1ca3` |
| `kem-18` | `third_party/kem-18/source/Implementations/Reference_Implementation/Loong128/KEM_Loong.c` | `7a3610ee965fe041caa72c39f96ac55045eaaef808a34ba80fbc9585835ac38a` |

In Cheetah128, `indcpa_keypair` at line 65 stores the return of
`get_random_number` but uses `buf` regardless; `kem_keygen` at lines 220-224
also discards the DRNG return and reports `0`. Loong128 has the same
pattern at lines 64 and 232-236. Other `pseudoXOF` and hash statuses are
also assigned and overwritten in the same files; their failure handling
needs separate review.

Run `bash results/ngcc_119_source_audit/run_kem_rng_failure_probe.sh`.
It compiles the original reference sources for Cheetah128/256/384/512 and
Loong128/256/384/512 with a deterministic DRNG stub. For every variant,
both the successful-RNG control and a stub that returns `-1` on each RNG
request produced `kem_keygen` status `0` and the advertised public/secret
key lengths. Each invocation made two RNG requests. The output bytes in
the failure probe are deliberate test bytes, so the result proves the API
ignores the error, not that a real failure yields a particular key.

Propagate DRNG failures before consuming the affected buffers, initialize
failure outputs, and review all overwritten helper statuses in the same
public paths.
