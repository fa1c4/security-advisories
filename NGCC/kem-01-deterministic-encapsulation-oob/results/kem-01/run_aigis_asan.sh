#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
bash run_poc_deterministic_encapsulation_oob.sh
bash run_poc_decapsulation_oob.sh
bash run_poc_rejected_ciphertext_fallback.sh
