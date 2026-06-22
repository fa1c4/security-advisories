# libgit2 MIDX packfiles unbounded allocation PoC

## Summary

This PoC demonstrates that a tiny malformed Git multi-pack-index (MIDX) payload can make libgit2 request a huge `git_vector` allocation before validating that the file contains enough packfile-name data.

The 185-byte fuzzer input contains four control bytes followed by a 181-byte MIDX payload. In the MIDX header, the attacker-controlled `packfiles` field is `0xcf066a75`, decoded as `3,473,304,181`. GDB confirms this value is passed to:

```text
git_vector_init(initial_size=3473304181)
```

This corresponds to an allocation request of roughly 27.8 GB on 64-bit platforms.

## Tested version

- Repository: https://github.com/libgit2/libgit2
- Tested commit from metadata: `57877524482fe6e46afdbf636f5467e7f9a33fe5`
- The included Dockerfile clones the current repository state unless modified; maintainers should confirm the exact affected range.

## Files

- `poc.c` - standalone reproducer using `git_midx_open()`
- `crash.bin` - fuzzer-derived trigger input
- `evidence/gdb_bt.txt` - GDB stack showing `git_vector_init(initial_size=3473304181)`
- `evidence/input_field_mapping.txt` - mapping from input bytes to the oversized `packfiles` count

## Build and run

```bash
docker build -t libgit2-midx-packfiles-oom-poc .
docker run --rm libgit2-midx-packfiles-oom-poc
```

Expected output:

```text
Parsing 185 bytes as MIDX...
git_midx_open failed: Out of memory
[VULNERABILITY CONFIRMED] oversized packfiles count forced OOM path
PoC completed
```

## Notes

Under the current memory limit the API returns an ENOMEM-style error rather than crashing. The security concern is the uncontrolled allocation attempt from a tiny malicious repository metadata file. Maintainers should confirm whether this is security-relevant and whether `packfiles` should be validated against the PNAM chunk size before vector allocation.
