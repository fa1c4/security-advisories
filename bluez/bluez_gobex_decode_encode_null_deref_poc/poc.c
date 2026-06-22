#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "gobex/gobex.h"
#include "gobex/gobex-packet.h"

int main(int argc, char **argv) {
  if (argc < 2) {
    fprintf(stderr, "Usage: %s <crash_file>\n", argv[0]);
    return 1;
  }

  FILE *f = fopen(argv[1], "rb");
  if (!f) {
    perror("fopen");
    return 1;
  }

  fseek(f, 0, SEEK_END);
  long fsize = ftell(f);
  fseek(f, 0, SEEK_SET);

  uint8_t *data = (uint8_t *)malloc(fsize);
  if (!data) {
    perror("malloc");
    fclose(f);
    return 1;
  }

  fread(data, 1, fsize, f);
  fclose(f);

  uint8_t buf[256];
  GObexPacket *pkt;
  GError *err = NULL;

  pkt = g_obex_packet_decode(data, fsize, 0, G_OBEX_DATA_REF, &err);
  if (pkt != NULL) {
    g_obex_packet_encode(pkt, buf, sizeof(buf));
    g_obex_packet_free(pkt);
  } else if (err != NULL) {
    fprintf(stderr, "decode error: %s\n", err->message);
    g_error_free(err);
  }

  free(data);
  printf("PoC completed\n");
  return 0;
}
