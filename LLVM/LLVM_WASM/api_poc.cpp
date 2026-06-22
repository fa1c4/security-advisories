#include <cstdio>
#include <cstdlib>
#include <cstring>

#include "llvm/DebugInfo/DWARF/DWARFContext.h"
#include "llvm/Object/ObjectFile.h"
#include "llvm/Support/MemoryBuffer.h"
#include "llvm/Support/Error.h"
#include "llvm/Support/TargetSelect.h"
#include "llvm/Support/raw_ostream.h"

int main(int argc, char **argv) {
  if (argc < 2) {
    fprintf(stderr, "Usage: %s <crash_file>\n", argv[0]);
    return 1;
  }

  llvm::InitializeAllTargetInfos();
  llvm::InitializeAllTargetMCs();

  auto BufOrErr = llvm::MemoryBuffer::getFile(argv[1]);
  if (!BufOrErr) {
    fprintf(stderr, "Error reading file: %s\n", argv[1]);
    return 1;
  }

  llvm::StringRef Data = BufOrErr.get()->getBuffer();
  std::unique_ptr<llvm::MemoryBuffer> Buf =
      llvm::MemoryBuffer::getMemBuffer(Data, "", false);

  auto ObjOrErr = llvm::object::ObjectFile::createObjectFile(
      Buf->getMemBufferRef());
  if (!ObjOrErr) {
    llvm::Error Err = ObjOrErr.takeError();
    llvm::handleAllErrors(std::move(Err), [](const llvm::ErrorInfoBase &EIB) {
      fprintf(stderr, "Object parse error: %s\n", EIB.message().c_str());
    });
    return 1;
  }

  auto DCtx = llvm::DWARFContext::create(**ObjOrErr);
  if (!DCtx) {
    fprintf(stderr, "Failed to create DWARF context\n");
    return 1;
  }

  DCtx->dump(llvm::outs(), llvm::DIDumpOptions());

  printf("PoC completed\n");
  return 0;
}
