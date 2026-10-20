# NEV-AKE initiator reads beyond a short pass-two message

**Assessment:** Reproduced out-of-bounds read in the submitted reference
`kex_derive_ss_a` path when the responder's second message is shorter than
the fixed protocol length. This is a malformed-transcript memory-safety
finding. The reference wrapper and core `ake.c` are byte-identical across
the nine submitted reference parameter directories, but the ASan run below
exercises only `NEV-AKE-C1-c` with `PARAMS=1`.

The representative wrapper is
`third_party/kex-07/source/Implementations and Test_Vectors/Implementations/Reference_Implementation/NEV-AKE-C1-c/kat_test/KEX_AlgorithmInstance.c`,
SHA-256 `563d9027b58c44d5ce987e7b70802766105646bd6e563a59f735fbfe764da40c`.
At lines 158-173, `kex_derive_ss_a` explicitly discards `mb_len_bytes`,
passes `mb` to `ake_der_init` and reports success. The core `ake.c`, SHA-256
`a1eb3666bc6fd602df7c809a4aa33290ad1869097802b488c6ff6286f585c3e9`,
uses `M_prime` as a fixed `AKE_M2_BYTES` message and passes its start to
`ake_pke_decrypt_verify` at line 205. That function reads a fixed-length
ciphertext from it before a length check can reject the message.

Run `bash results/kex-07/run_short_pass2_probe.sh`. It builds the submitted
source set with `PARAMS=1`, `USE_ICCS` and AddressSanitizer. A buffer of
the advertised `AKE_M2_BYTES` length (1,127 bytes) returns without an ASan
error. A one-byte allocation reports `heap-buffer-overflow` through
`ake_pke_decrypt_verify` at `ake.c:103`, called by `ake_der_init` at line
205 and `kex_derive_ss_a` at wrapper line 172. Both controls use zeroed
key and state buffers of the declared sizes. Their values do not assert a
successful honest exchange; they isolate the transcript length boundary.

Validate `mb_len_bytes` against `AKE_M2_BYTES` before passing the message
to the fixed-length core. Public caller reachability in a deployed system
and exploitability are not established.
