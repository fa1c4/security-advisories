#!/usr/bin/env bash
set -euo pipefail

mkdir -p /opt/evidence

echo "[*] Croogo FileManager arbitrary file write / path traversal local lab"
echo "[*] Repository: /opt/croogo"
echo "[*] Commit: $(cd /opt/croogo && git rev-parse HEAD)"
echo

/opt/poc/reproduce-local.sh 2>&1 | tee /opt/evidence/reproduction.log
