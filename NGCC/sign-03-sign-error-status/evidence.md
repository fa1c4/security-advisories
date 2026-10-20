# CEDRUS+C signed-message API reports success after inner signing failure

## Run this exported package

After `source/download_targets.sh`, from the NGCC package root:

```sh
tools/reproduce.sh sign-03-sign-error-status
```

The command verifies the submitted source hash, runs this finding's trigger
and controls, and prints its local verdict. Inspect `sign-03-sign-error-status/reproduction.log`
for detailed output. All `results/...` paths below are relative to this bug
directory unless stated otherwise.

**Assessment:** Reproduced incorrect success status in the submitted
CEDRUS+C reference `crypto_sign` implementation when its inner
`crypto_sign_signature` fails. This can leave a partial signature and an
indeterminate signed-message length. The trigger used here forces the
submitted `hash_message` call to fail; ordinary successful signing is the
control. Deployed trigger frequency is unknown.

All eight reference parameter variants have byte-identical `sign.c` files.
The representative source is
`third_party/sign-03/source/Implementations/Reference_Implementation/CEDRUSC-160f/sign.c`,
SHA-256 `3529d1d2fb6a117fa19f413af6c910fb7c130b9a99adc1bcbe505a2027937a3d`.
At lines 132-133, `crypto_sign_signature` returns `-1` when `hash_message`
fails, before writing `*siglen` at line 173. Its caller at lines 285-292
ignores that status, uses the uninitialized local `siglen` to calculate
`*smlen`, and returns `0`.

The verified `run_ngcc.sh` entry point invokes `run.sh`, which calls
`results/sign-03/run_sign_failure_probe.sh`. It builds the original
reference-160f sources with a linker wrapper around `hash_message`. The
control delegates to the real hash and reports `sign_status=0` with
`signed_length=19813`. When the wrapper returns `-1`, the same public API
still reports `sign_status=0`; the observed length was `1`. That length is
undefined behavior and is not a stable expected value. The runner checks
the status and message-copy behavior, not a particular garbage length.

The source-level fix is to check the inner return value before moving the
message or calculating a signed-message length, initialize output length on
failure, and propagate the error.
