# MEGASCON and MOZI reference hashes retain their padding buffers

**Assessment:** Reproduced successful-call memory leaks in all six reference
instances of `hash-18` MEGASCON and all six reference instances of `hash-20`
MOZI. The submitted optimized profiles were not run by this reproducer.

## Source and effect

Each tested `CryptHash_AlgorithmInstance.c` allocates `padded_input` in its
`hash_sponge` or `hash_Fsponge` function, computes the digest, and returns `0`
without freeing that allocation. In the 384-XOF reference source, the
allocation is at `hash-18/.../MEGASCON-384-XOF/CryptHash_AlgorithmInstance.c:144`
and the return at line 184; the analogous MOZI allocation is at
`hash-20/.../MOZI-384-XOF/CryptHash_AlgorithmInstance.c:157` and return at
line 197. The two representative source file SHA-256 digests are
`57a4a46df1f3b2f54bba4f87f7d87ed610f602bfa8ada00630f3d5993594f727`
and `3f494705e35bd5bfd47f60d7fba57cc213913dbb84bb6ad3992568439ec2d96c`,
respectively. `target-inventory.csv` records the targets' downloaded source
receipt digests.

## Reproduction

Run `bash results/ngcc_119_source_audit/run_hash_padding_leak.sh` from the
repository root. The script compiles all twelve original reference wrapper
sources separately and uses GNU linker `--wrap=malloc,--wrap=free` to count
calls made by the compiled target code. The harness gives each instance a
one-bit message, uses its declared `DIGEST_BIT_LENGTH`, and checks successful
status and changed digest bytes. For every instance the observed result was
`status=0 digest_changed=1 allocations=1 frees=0 outstanding=1`. The harness
releases the retained allocation after recording the count so its own process
does not leak. Outputs are built under ignored
`results/ngcc_119_source_audit/build/`.

The twelve instances are MEGASCON-384, -512, -768, -1024, -384-XOF,
-512-XOF, and MOZI-384, -512, -768, -1024, -384-XOF, -512-XOF. This establishes
one retained padding allocation per observed call. Long-running callers that
hash repeatedly can accumulate memory usage; no remote denial-of-service or
deployment exposure is established. A repair should free the padding buffer
on each return path and propagate allocation failure rather than dereferencing
an unchecked `malloc` result.
