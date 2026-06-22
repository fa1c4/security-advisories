#include <cstdio>
#include <cstdlib>
#include <cstdint>
#include <vector>

#include "draco/compression/decode.h"
#include "draco/core/decoder_buffer.h"

int main(int argc, char **argv) {
  if (argc < 2) {
    fprintf(stderr, "Usage: %s <crash_file>\n", argv[0]);
    return 1;
  }

  FILE *f = fopen(argv[1], "rb");
  if (!f) { perror("fopen"); return 1; }

  fseek(f, 0, SEEK_END);
  long fsize = ftell(f);
  fseek(f, 0, SEEK_SET);

  std::vector<uint8_t> data(fsize);
  fread(data.data(), 1, fsize, f);
  fclose(f);

  draco::DecoderBuffer buffer;
  buffer.Init(reinterpret_cast<const char*>(data.data()), data.size());

  draco::Decoder decoder;
  auto status = decoder.DecodeMeshFromBuffer(&buffer);
  if (!status.ok()) {
    fprintf(stderr, "Decode failed: %s\n", status.status().error_msg());
  } else {
    fprintf(stderr, "Decode succeeded\n");
  }

  printf("PoC completed\n");
  return 0;
}
