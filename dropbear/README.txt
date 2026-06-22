# Dropbear signature verification regression PoC

This package tests an OSS-Fuzz-derived Dropbear key/signature verification input against a selected Dropbear Git ref.

Important note:

  CVE databases currently mention fdec3c90a15447bd538641d85e5a3e3ac981011d as a "patch name" for CVE-2026-3706. That value is not a reachable commit in https://github.com/mkj/dropbear, so this PoC does not try to checkout that hash by default.

Recommended regression refs:

  - DROPBEAR_2026.91  latest release tag at the time this PoC was prepared
  - DROPBEAR_2026.90  first 2026 release after DROPBEAR_2025.89
  - master            current upstream master
  - DROPBEAR_2025.89  old release reported by CVE feeds as affected, useful for comparison

The original fuzzer PoC called abort() after detecting that a crafted key/signature pair verified successfully. This regression PoC does not call abort(). It reports whether buf_verify() accepts the crafted input and returns an explicit exit code.

## Build and run against the latest release tag

  docker build -t dropbear-release-regression-poc .
  docker run --rm dropbear-release-regression-poc

Expected result on a non-affected or patched ref:

  [*] buf_verify() rejected crafted input
  [*] Not reproduced: crafted key/signature was rejected
  [*] exit: 0

## Build and run against another ref

  docker build --build-arg DROPBEAR_REF=DROPBEAR_2025.89 -t dropbear-2025.89-regression-poc .
  docker run --rm dropbear-2025.89-regression-poc

  docker build --build-arg DROPBEAR_REF=master -t dropbear-master-regression-poc .
  docker run --rm dropbear-master-regression-poc

If the crafted input is accepted:

  [!] buf_verify() returned success for crafted input
  [!] VULNERABLE/REPRODUCED: crafted key/signature verified successfully
  [*] exit: 1

## Files

- Dockerfile: clones and builds Dropbear at the selected ref/tag
- poc_verify.c: standalone regression PoC using Dropbear verification APIs
- crash.bin: OSS-Fuzz-derived crafted key/signature input
- run.sh: wrapper that prints the selected ref and result
