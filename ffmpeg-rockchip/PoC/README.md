# ffmpeg-rockchip MOV metadata OOM PoC

This package contains a local verification harness for an uncontrolled allocation in `libavformat/mov.c::mov_read_keys()` in `nyanmisaka/ffmpeg-rockchip` tested at commit `40c412daccf08164493da0de990eb99a8948116b`.

## Files

- `Dockerfile` — source-build Dockerfile. It clones `ffmpeg-rockchip`, checks out the tested commit, builds a minimal MOV demuxer, and compiles `poc.cc`.
- `poc.cc` — custom AVIO harness that feeds the 101-byte MOV-like artifact as non-seekable input to the MOV demuxer.
- `oom-f528a3c32455549e702ad8b2c9f843770d63253b` — raw fuzzer artifact bytes.
- `Dockerfile.fuzzer-prebuilt` — original prebuilt-fuzzer Dockerfile. Use it only if you already have the `ffmpeg_dem_MOV_fuzzer` binary built from the same commit.
- `github_security_report.md`, `cve_request.md`, `vuldb_submission.md` — disclosure drafts.

## Build and run

```sh
docker build -t poc-ffmpeg-rockchip-mov-oom .
docker run --rm --memory=256m --memory-swap=256m poc-ffmpeg-rockchip-mov-oom
```

Expected behavior: the process should fail or be killed under the constrained memory limit while parsing the crafted MOV metadata. This demonstrates that a tiny input can drive a very large allocation request through the `keys` atom path.

## Exact fuzzer reproduction, if you have the fuzzer binary

Place `ffmpeg_dem_MOV_fuzzer` next to `Dockerfile.fuzzer-prebuilt`, then run:

```sh
docker build -f Dockerfile.fuzzer-prebuilt -t poc-ffmpeg-rockchip-mov-oom-fuzzer .
docker run --rm poc-ffmpeg-rockchip-mov-oom-fuzzer
```

That image uses the libFuzzer RSS limit option from the original reproducer: `-rss_limit_mb=256`.
