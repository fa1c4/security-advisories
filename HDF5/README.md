# HDF5 H5Fopen unbounded allocation PoC

## Summary

This PoC demonstrates a large allocation request while opening a malformed HDF5 file with `H5Fopen()`.

A 2,567-byte input beginning with the HDF5 signature triggers AddressSanitizer OOM:

```text
ERROR: AddressSanitizer: out of memory: allocator is trying to allocate 0xff00000060 bytes
```

The confirmed impact is excessive memory allocation / process abort under ASan, i.e. denial of service. It does not demonstrate code execution, privilege escalation, or information disclosure.

## Tested version

- Repository: https://github.com/HDFGroup/hdf5
- Commit: `c478c6e35fae83ddb9ae82b74b2ef30ad353b8a7`

## Files

- `poc.c` - standalone reproducer using `H5Fopen()`
- `crash.bin` - 2,567-byte malformed HDF5 input
- `crash_offset1.bin` - alternate extracted variant from original package
- `Dockerfile` - reproducible build/run environment with ASan enabled

## Build and run

```bash
docker build -t hdf5-h5fopen-unbounded-allocation-poc .
docker run --rm hdf5-h5fopen-unbounded-allocation-poc
```

## Expected vulnerable output

```text
Opening HDF5 file: /tmp/hdf5_crash_1.h5 (size=2567)
ERROR: AddressSanitizer: out of memory: allocator is trying to allocate 0xff00000060 bytes
SUMMARY: AddressSanitizer: out-of-memory
```

## Notes

This is intended for HDF Group maintainer/security triage. DoS caused by excessive RAM consumption via malformed HDF5 files is listed as usually in scope in the HDF5 security policy.
