#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdint.h>
#include <unistd.h>
#include "hdf5.h"

int main(int argc, char **argv) {
  if (argc < 2) {
    fprintf(stderr, "Usage: %s <crash_file>\n", argv[0]);
    return 1;
  }

  /* H5Fopen opens by filename, so copy crash file to temp location */
  /* The h5_read_fuzzer also skips first byte - the crash data may have the
     "decider byte" prepended, so we pass the raw crash file directly */

  char filename[256];
  snprintf(filename, sizeof(filename), "/tmp/hdf5_crash_%d.h5", getpid());

  FILE *f = fopen(argv[1], "rb");
  if (!f) { perror("fopen"); return 1; }

  fseek(f, 0, SEEK_END);
  long fsize = ftell(f);
  fseek(f, 0, SEEK_SET);

  uint8_t *data = (uint8_t *)malloc(fsize);
  if (!data) { perror("malloc"); fclose(f); return 1; }

  fread(data, 1, fsize, f);
  fclose(f);

  /* Write directly to temp file */
  FILE *ftmp = fopen(filename, "wb");
  if (!ftmp) { perror("fopen tmp"); free(data); return 1; }

  fwrite(data, 1, fsize, ftmp);
  fclose(ftmp);

  /* Open the file - this triggers reading of superblock and OOM */
  fprintf(stderr, "Opening HDF5 file: %s (size=%ld)\n", filename, fsize);
  hid_t file_id = H5Fopen(filename, H5F_ACC_RDWR, H5P_DEFAULT);
  if (file_id >= 0) {
    H5Fclose(file_id);
    printf("File opened and closed\n");
  } else {
    fprintf(stderr, "H5Fopen returned error\n");
  }

  remove(filename);
  free(data);
  printf("PoC completed\n");
  return 0;
}
