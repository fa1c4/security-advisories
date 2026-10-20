#!/usr/bin/env python3
"""Build exact NIIKE lv128 translation units with ASan and test key length."""

import json
import os
import pathlib
import shlex
import shutil
import subprocess


root = pathlib.Path(__file__).resolve().parents[2]
build = root / "results/kex-08/build-reference"
source = root / "third_party/kex-08/source/Implementations and Test_Vectors/Implementations/Reference_Implementation"
cache = build / "CMakeCache.txt"
if cache.is_file() and f"CMAKE_CACHEFILE_DIR:INTERNAL={build}" not in cache.read_text().splitlines():
    shutil.rmtree(build)
build.mkdir(parents=True, exist_ok=True)
subprocess.run(
    ["cmake", "-S", str(source), "-B", str(build),
     "-DCMAKE_EXPORT_COMPILE_COMMANDS=ON", "-DCMAKE_BUILD_TYPE=Debug"],
    check=True, stdout=subprocess.DEVNULL,
)
entries = json.loads((build / "compile_commands.json").read_text())
names = {
    "ngccapi/KEX_AlgorithmInstance.c": "api",
    "NIIKE-lv128/protocols/bundleprotocols_internal.c": "decode",
    "NIIKE-lv128/gf/fp2.c": "fp2",
    "NIIKE-lv128/gf/fp.c": "fp",
}
objects = []
with (build / "short_public_key_compile.log").open("w") as log:
    for suffix, label in names.items():
        matches = [entry for entry in entries if entry["file"].endswith(suffix)
                   and "NIIKE-lv128" in entry["command"]]
        if len(matches) != 1:
            raise RuntimeError(f"Expected one lv128 compile command for {suffix}: {len(matches)}")
        entry = matches[0]
        command = shlex.split(entry["command"])
        output_index = command.index("-o") + 1
        obj = build / f"asan_{label}.o"
        command[output_index] = str(obj)
        command.extend(["-O1", "-fsanitize=address", "-fno-omit-frame-pointer",
                        "-ffunction-sections", "-fdata-sections"])
        subprocess.run(command, cwd=entry["directory"], check=True,
                       stdout=log, stderr=log)
        objects.append(str(obj))

probe = root / "results/kex-08/short_public_key_probe.c"
binary = build / "short_public_key_probe"
includes = [source / "NIIKE-lv128/ngccapi/include",
            source / "common/include", source / "NIIKE-lv128/precomp/include"]
link = ["cc", "-std=c99", "-g", "-fsanitize=address",
        "-fno-omit-frame-pointer", "-Wl,--gc-sections",
        "-Wl,--wrap=niike_SecretAgreement", "-Wl,--wrap=fp2_encode"]
link.extend(f"-I{path}" for path in includes)
link.extend([str(probe), *objects, "-o", str(binary)])
with (build / "short_public_key_link.log").open("w") as log:
    subprocess.run(link, check=True, stdout=log, stderr=log)

asan_env = dict(os.environ, ASAN_OPTIONS="detect_leaks=0")
for side in ("a", "b"):
    control = subprocess.run([str(binary), f"{side}_exact"],
                             capture_output=True, text=True, env=asan_env)
    (build / f"short_public_key_{side}_exact.log").write_text(
        control.stdout + control.stderr)
    if control.returncode != 0 or "ERROR: AddressSanitizer" in control.stderr:
        raise RuntimeError(f"Exact-size {side} control failed; inspect retained log")
    print(control.stdout.strip())

    short = subprocess.run([str(binary), f"{side}_short"],
                           capture_output=True, text=True, env=asan_env)
    (build / f"short_public_key_{side}_asan.log").write_text(
        short.stdout + short.stderr)
    if short.returncode == 0 or "ERROR: AddressSanitizer: heap-buffer-overflow" not in short.stderr:
        raise RuntimeError(f"Expected short {side} ASan read; inspect retained log")
    print(f"side={side}: heap-buffer-overflow through original fp_decode")
