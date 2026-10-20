# PoC: TaiChi-1024 leaks one allocation per successful hash

Run `./results/hash-25/run_taichi1024_leak.sh` from the repository root. The [Makefile](Makefile) builds the submitted TaiChi-1024 reference source with a [harness](taichi1024_leak_harness.c) that counts direct `malloc` and `free` calls. [taichi1024_leak_input.bin](taichi1024_leak_input.bin) is a short valid byte-aligned message.

Expected result: `CryptHash status=0, allocations=1, frees=0, outstanding=1`. The harness also verifies that the digest buffer changed, so this is a completed successful hash. `TaiChi_Hash` allocates its padding buffer at `CryptHash_AlgorithmInstance.c:246` and returns without freeing it. The 512- and 768-bit sibling implementations do free their corresponding buffers.

The PoC demonstrates a per-call leak on the tested message, not a deployment-specific denial of service.
