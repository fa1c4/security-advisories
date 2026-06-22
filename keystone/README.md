# Keystone EVM KS_OPT_SYNTAX null dereference PoC

This PoC reproduces a crash in Keystone Engine when using the public C API with the EVM backend.

## Summary

The reproducer opens a Keystone engine with `KS_ARCH_EVM`, then calls `ks_option(ks, KS_OPT_SYNTAX, value)` with a fuzzer-derived syntax value. The process crashes before `ks_asm()` is reached.

This appears to be a null pointer dereference in `ks_option()` for an architecture that does not support syntax selection.

## Build and run

```bash
docker build -t keystone-evm-syntax-null-deref-poc .
docker run --rm keystone-evm-syntax-null-deref-poc; echo "exit=$?"
```

## Expected vulnerable behavior

The container prints a line similar to:

```text
Setting KS_OPT_SYNTAX to 0x78
```

and exits with code 139 / SIGSEGV.

## Files

- `Dockerfile` - reproducible build environment pinned to commit `fb92f32391c6cced868252167509590319eeb58b`
- `poc.c` - minimal C API reproducer
- `crash.bin` - fuzzer-derived input
- `docker_run.log` - observed run output
