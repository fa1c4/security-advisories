#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <keystone/keystone.h>

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

    if (fsize < 1) {
        fprintf(stderr, "Input too small\n");
        fclose(f);
        return 1;
    }

    unsigned char *data = (unsigned char *)malloc(fsize);
    if (!data) { perror("malloc"); fclose(f); return 1; }
    fread(data, 1, fsize, f);
    fclose(f);

    ks_engine *ks;
    ks_err err;
    size_t count;
    unsigned char *encode = NULL;
    size_t size;

    err = ks_open(KS_ARCH_EVM, 0, &ks);
    if (err != KS_ERR_OK) {
        fprintf(stderr, "ERROR: ks_open() failed, error = %u\n", err);
        free(data);
        return 1;
    }

    fprintf(stderr, "Setting KS_OPT_SYNTAX to 0x%02x\n", data[fsize - 1]);
    ks_option(ks, KS_OPT_SYNTAX, data[fsize - 1]);

    char *assembler = (char *)malloc(fsize);
    memcpy(assembler, data, fsize - 1);
    assembler[fsize - 1] = 0;

    fprintf(stderr, "Calling ks_asm()...\n");
    if (ks_asm(ks, assembler, 0, &encode, &size, &count) != KS_ERR_OK) {
        fprintf(stderr, "ks_asm() failed, error = %u\n", ks_errno(ks));
    } else {
        fprintf(stderr, "Assembled %zu bytes, %zu statements\n", size, count);
        ks_free(encode);
    }

    free(assembler);
    ks_close(ks);
    free(data);

    printf("PoC completed\n");
    return 0;
}
