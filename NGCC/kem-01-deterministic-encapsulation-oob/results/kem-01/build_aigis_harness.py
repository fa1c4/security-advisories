#!/usr/bin/env python3
"""Build the submitted Aigis-Enc+-I sources with AddressSanitizer."""
import json
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
SOURCE = ROOT / "third_party/kem-01/source"
variant = sys.argv[1] if len(sys.argv) == 2 else "I"
if variant not in ("I", "II", "III"):
    raise SystemExit("variant must be I, II or III")
config = json.loads((ROOT / "oracles/kem-01/data/instances.json").read_text())[f"Aigis-Enc+-{variant}"]
sources = [SOURCE / name for name in config["sources"] if not name.endswith("/KAT_KEM.c")]
command = ["clang", "-std=c11", "-O0", "-g", "-fno-omit-frame-pointer", "-fsanitize=address",
           *config["flags"], *(arg for name in config["includes"] for arg in ("-I", str(SOURCE / name))),
           str(HERE / "aigis_harness.c"), *(str(path) for path in sources),
           "-o", str(HERE / f"aigis_harness_{variant}")]
subprocess.run(command, check=True, cwd=ROOT)
