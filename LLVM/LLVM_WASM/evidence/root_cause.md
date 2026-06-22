# Root Cause

The malformed input is a 23-byte WebAssembly object (`\0asm`) containing an overlong ULEB128 sequence. Real LLVM tools abort while constructing a `WasmObjectFile`.

- Real CLI 1: `llvm-dwarfdump /build/crash.bin`, exit 139.
- Real CLI 2: `llvm-objdump --file-headers /build/crash.bin`, exit 139.
- Top library path from LLVM stack dump: `llvm::object::WasmObjectFile::WasmObjectFile()` -> `ObjectFile::createWasmObjectFile()` -> `ObjectFile::createObjectFile()` -> `llvm::report_fatal_error("uleb128 too big for uint64")`.
- Source reference: `llvm/Support/LEB128.h` around `decodeULEB128()`, with error text at line 149 in the installed LLVM 18 headers.

`decodeULEB128()` can report `"uleb128 too big for uint64"` through an error pointer, but this tool path reaches `report_fatal_error()` instead of a recoverable malformed-object error.
