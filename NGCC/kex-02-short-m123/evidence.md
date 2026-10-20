# AFS-KEX receive-side length checks missing at passes two, three, and four

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh kex-02-short-m123
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `kex-02-short-m123/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Assessment:** AddressSanitizer reproduced source-level out-of-bounds reads for all nine short-message/parameter-set pairs. The PQCFuzz finding remains candidate-only under `designs/architecture.md` DES-01, DES-04, and DES-05.

## Exact source and common cause

The three submitted reference wrappers are byte-identical (SHA-256 `186fdf2d8e1d4914773c3cea3f11bfdd267c9ade80cfbc9f7a6b740407dfcc14`):

- `third_party/kex-02/source/Implementations and Test_Vectors/Implementations/Reference_Implementation/AFS_KEX_C128/KEX_AlgorithmInstance.c`
- `third_party/kex-02/source/Implementations and Test_Vectors/Implementations/Reference_Implementation/AFS_KEX_C256/KEX_AlgorithmInstance.c`
- `third_party/kex-02/source/Implementations and Test_Vectors/Implementations/Reference_Implementation/AFS_KEX_C512/KEX_AlgorithmInstance.c`

Every affected public function discards its incoming message length with `(void)m*_len_bytes` and then reads a fixed number of bytes. The [combined source audit](results/kex-02/bug-report-short-m123.md) records the separate variant builds.

| Message | Ignored length | First unsafe use | Required bytes: C128 / C256 / C512 | ASan observation with one-byte input |
| --- | --- | --- | --- | --- |
| `m1`, pass two | `m1_len_bytes`, line 174 | `crypto_kem_dec` at line 177 | 768 / 1,440 / 2,944 | Heap-buffer-overflow in KEM decapsulation; pass-two wrapper in stack. |
| `m2`, pass three | `m2_len_bytes`, line 214 | `crypto_kem_dec` at line 217 | 784 / 1,472 / 3,008 | Heap-buffer-overflow in KEM decapsulation; pass-three wrapper in stack. |
| `m3`, pass four | `m3_len_bytes`, line 257 | `m3[i]` at line 264 | 16 / 32 / 64 | Heap-buffer-overflow in the pass-four wrapper. |

## Controls and retained evidence

The [combined PoC](poc.md) passes the actual allocation size as the public API length. It runs one-byte inputs and exact-size controls for every table row and variant. Each trigger produces the expected ASan type, function, and source line; each control exits without sanitizer error. The consolidated run writes `reproduction.log` and nine full traces at `results/kex-02/build/short_<level>_<message>_asan.log`.

The probe uses zeroed key/state buffers and a deterministic random stub. It establishes a buffer-boundary failure in the submitted implementation, not an honest key exchange or secret disclosure. In particular, some exact-size `m2` controls return an API error without an ASan failure.

## Repair gate

Reject short `m1`, `m2`, and `m3` before their fixed-size reads in each reference variant. The trigger must stop producing an out-of-bounds read while the exact-size control stays memory-safe. Run the candidate's submitted tests separately; they were not part of this PoC. No `third_party/` source was changed.
