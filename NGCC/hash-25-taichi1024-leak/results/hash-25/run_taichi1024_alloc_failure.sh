#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
make
./taichi1024_leak_harness taichi1024_leak_input.bin fail
