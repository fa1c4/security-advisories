# Attack Surface

The attacker-controlled input is an object file passed to LLVM object/debug-info tooling or applications using LLVM object parsing APIs.

The new evidence uses real tools, not only the fuzzer: `llvm-dwarfdump` and `llvm-objdump --file-headers` both abort on the same malformed WASM file. This can matter for services, IDEs, build systems, package analyzers, or CI jobs that inspect untrusted object files.
