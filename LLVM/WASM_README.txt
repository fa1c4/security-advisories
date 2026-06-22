LLVM WASM overlong ULEB128 fatal abort PoC
=========================================

Purpose
-------
This package demonstrates that a 23-byte malformed WebAssembly object can cause
LLVM object/debug-info tooling and LLVM object parsing APIs to abort with:

    LLVM ERROR: uleb128 too big for uint64

This is submitted for LLVM Security Response Group scope review. The report does
not claim a confirmed CVE. LLVM maintainers should decide whether this object
file parser abort is security-sensitive under LLVM's security model.

Tested versions / contexts
--------------------------
- Real tools: LLVM 18.1.3 from Ubuntu noble packages
- Original OSS-Fuzz context: LLVM 23.0.0git, OSS-Fuzz build 2026-05-15
- Exact affected version range: unknown; maintainer confirmation required

Files
-----
- Dockerfile: reproducible environment using LLVM 18 tools and libraries
- crash.bin: malformed WASM object trigger
- api_poc.cpp: standalone API-level reproducer using ObjectFile/DWARFContext
- evidence/: previously captured real-tool and API logs

Build and run
-------------

    docker build -t llvm-wasm-uleb128-fatal-poc .
    docker run --rm llvm-wasm-uleb128-fatal-poc

Expected vulnerable behavior
----------------------------
The container runs three reproductions:

1. llvm-dwarfdump /build/crash.bin
2. llvm-objdump --file-headers /build/crash.bin
3. API-level ObjectFile/DWARFContext PoC

The expected vulnerable behavior is an abort/fatal error containing:

    LLVM ERROR: uleb128 too big for uint64

The real-tool stack reaches:

    llvm::report_fatal_error(...)
    llvm::object::WasmObjectFile::WasmObjectFile(...)
    llvm::object::ObjectFile::createWasmObjectFile(...)
    llvm::object::ObjectFile::createObjectFile(...)

Trigger format
--------------
The input begins with a valid WebAssembly magic/version header (`00 61 73 6d
01 00 00 00`) followed by an overlong ULEB128-like byte sequence.

Suggested maintainer question
-----------------------------
Should malformed object input that contains an overlong ULEB128 sequence be
reported as a recoverable malformed-object error instead of reaching
report_fatal_error()/abort() in the LLVM object parsing path?
