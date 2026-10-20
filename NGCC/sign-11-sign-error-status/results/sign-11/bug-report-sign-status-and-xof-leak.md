# FlexTree signing status and pseudoXOF allocation failures

**Assessment:** Two independent failure-path defects were reproduced in the
submitted FlexTree reference implementation. The same `sign.c` source is
present in all eight reference parameter variants; the same `auxfunc.c`
source is present in sixteen submitted reference instance paths. These are
source-identity extensions of the representative reproductions.

`third_party/sign-11/source/Implementations/Reference_Implementation/Flextree-160f/sign.c`
has SHA-256 `e372574d999c97994e8aecdf983471a617755b8bb9989dc8ed5d17c90c00afe2`.
`crypto_sign_signature` returns `-1` when `hash_message` fails at lines
133-134, before writing `*siglen` at line 173. `crypto_sign` ignores the
return value at line 283, calculates `*smlen` from its uninitialized local
`siglen` at line 286, and returns `0`. Run
`bash results/sign-11/run_sign_failure_probe.sh`: the successful hash control
reports `sign_status=0 signed_length=18673`; the forced hash-error case also
reports `sign_status=0`, with observed `signed_length=1`. The latter length is
undefined behavior and is not a stable expected value. The runner verifies
status and message copying, not a specific garbage length.

`third_party/sign-11/source/Implementations/Reference_Implementation/Flextree-160f/auxfunc.c`
has SHA-256 `f6880183e6977479c9249b1dac0b62848317ec290d537de0a566c2ea4211b482`.
Its `pseudoXOF` allocates `cascade_msg_ct`, then returns `-2` without freeing
that block when the `K` allocation fails. Run
`bash results/sign-11/run_alloc_failure_probe.sh`: the success control has
two allocations and zero live blocks; failing the second allocation leaves
one live block. The harness releases it after counting.

The first defect needs error propagation and an initialized output length;
the second needs cleanup of the first allocation. The exact impact of
resource exhaustion in a deployed caller is unknown.
