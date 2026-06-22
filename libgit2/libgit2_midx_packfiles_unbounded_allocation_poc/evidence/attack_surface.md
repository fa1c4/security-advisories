# Attack Surface

The exercised entry point is `git_midx_open()`, a libgit2 API that opens Git multi-pack-index files from a repository.

MIDX files can be attacker-controlled when applications use libgit2 to inspect, clone, index, or otherwise process untrusted Git repositories. The current PoC writes the MIDX bytes to disk and calls `git_midx_open()` directly, which is closer to a product API path than the OSS-Fuzz fuzzer alone.
