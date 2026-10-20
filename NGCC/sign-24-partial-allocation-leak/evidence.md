# Sigurd working-buffer allocation failures retain prior allocations

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh sign-24-partial-allocation-leak
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `sign-24-partial-allocation-leak/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Assessment:** Reproduced conditional allocation leaks in all six submitted
`sign-24` reference/optimized wrapper variants: Sigurd-128, -256 and -512.
The failure path is reachable from `sig_keygen`, `sig_sign` and `sig_verify` when
a later working-buffer allocation fails. A deployment impact has not been
established.

Each `alloc_sign_buffers` and `alloc_verify_buffers` helper initializes its
pointer fields and makes multiple `malloc` calls. If any call fails, it returns
`-1` without freeing the earlier successful allocations. The public callers
return `-7` on that result without calling the corresponding free helper. For
example, the reference Sigurd-128 helpers are in
`third_party/sign-24/source/Implementations/Reference_Implementation/Sigurd-128/SIG_AlgorithmInstance.c:204-259`,
with public call sites at lines 434, 487 and 559. That source file has SHA-256
`facbeb5b03facc60963589077dff376c8a0f6712e6b5e15e53ec72b9a0d3a449`.
The optimized Sigurd-512 file has SHA-256
`390515fa2df0dc2a09957917cb91c198699a4f66f33cdc2e175222099a827a54`.

The verified `run_ngcc.sh` entry point invokes `run.sh`, which calls
`results/sign-24/run_alloc_failure_probe.sh`. The harness compiles
each original wrapper into a separate binary and injects failure from its
second allocation onward. All six signer helpers and all six verifier helpers
return `-1` with one allocation still live. The harness then invokes each
submitted free helper as a control; the live count falls to zero. The three
reference signer variants made 6, 7 and 6 allocation calls respectively; the
optimized signer variants made eight each. The reference verifier variants
made 4, 5 and 4; the optimized verifier variants made six each. Build products
are in ignored `results/sign-24/build/`.

The analyzer's `unix.MallocSizeof` notices on casts to pointers to arrays are
separate from this finding; the explicit allocation products include both
dimensions. The observed defect is missing cleanup on partial allocation
failure. Free all successfully allocated fields before returning an error.
