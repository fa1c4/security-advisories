#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "params.h"
#include "kat_test/KEX_AlgorithmInstance.h"

int main(int argc, char **argv) {
    size_t message_size = argc > 1 && strcmp(argv[1], "valid") == 0
                              ? AKE_M2_BYTES : 1;
    unsigned char *ska = calloc(AKE_SK_BYTES, 1);
    unsigned char *pkb = calloc(AKE_PK_BYTES, 1);
    unsigned char *mb = calloc(message_size, 1);
    unsigned char *sta = calloc(AKE_ST_BYTES, 1);
    unsigned char *ssa = calloc(AKE_KEY_BYTES, 1);
    unsigned long long ssa_len = 0;
    if (!ska || !pkb || !mb || !sta || !ssa) return 2;
    int status = kex_derive_ss_a(ska, AKE_SK_BYTES, pkb, AKE_PK_BYTES,
                                  mb, message_size, sta, AKE_ST_BYTES,
                                  ssa, &ssa_len);
    printf("input_bytes=%zu status=%d derived_bytes=%llu\n",
           message_size, status, ssa_len);
    free(ssa);
    free(sta);
    free(mb);
    free(pkb);
    free(ska);
    return 0;
}
