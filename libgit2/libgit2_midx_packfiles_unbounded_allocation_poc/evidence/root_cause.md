# Root Cause

The MIDX parser trusts the attacker-controlled packfile count before validating that the file contains enough packfile-name data.

- Source file: `src/libgit2/midx.c`.
- Function: `midx_parse_packfile_names()`.
- Allocation site: `git_vector_init(&idx->packfile_names, packfiles, git__strcmp_cb)` at `midx.c:66`.
- Caller: `git_midx_parse()` passes `ntohl(hdr->packfiles)` at `midx.c:267`.
- Allocation helper: `git_vector_init()` at `src/util/vector.c:108`.

The 185-byte fuzz input has four fuzzer-control bytes followed by a 181-byte MIDX payload. The MIDX header begins at offset `0x04`, and the packfiles field at offsets `0x0c-0x0f` is `cf 06 6a 75`, decoded as `3473304181`.

`gdb` captured the allocation request:

```text
git_vector_init(initial_size=3473304181)
midx_parse_packfile_names(packfiles=3473304181) at midx.c:66
git_midx_parse(... size=181) at midx.c:267
git_midx_open(...)
```

On 64-bit platforms this requests roughly `3473304181 * sizeof(void *)`, about 27.8 GB, from a 181-byte MIDX payload before validating the PNAM chunk contents.
