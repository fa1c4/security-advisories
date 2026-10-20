#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "drng.h"

unsigned long long kem_get_pk_len_bytes(void);
unsigned long long kem_get_sk_len_bytes(void);
int kem_keygen(unsigned char *pk, unsigned long long *pk_len_bytes,
               unsigned char *sk, unsigned long long *sk_len_bytes);

DRNG_ctx drng_algorithm;
static int failure_mode;
static int rng_calls;

int get_random_number(DRNG_ctx *drng, unsigned char *out,
                      unsigned long long bits) {
    (void)drng;
    rng_calls++;
    memset(out, 0x42, (size_t)((bits + 7) / 8));
    return failure_mode ? -1 : 0;
}

int main(int argc, char **argv) {
    failure_mode = argc > 1 && strcmp(argv[1], "fail") == 0;
    unsigned long long pk_capacity = kem_get_pk_len_bytes();
    unsigned long long sk_capacity = kem_get_sk_len_bytes();
    unsigned char *pk = calloc((size_t)pk_capacity, 1);
    unsigned char *sk = calloc((size_t)sk_capacity, 1);
    unsigned long long pk_len = 0, sk_len = 0;
    if (!pk || !sk) return 2;
    int status = kem_keygen(pk, &pk_len, sk, &sk_len);
    printf("forced_rng_failure=%d rng_calls=%d status=%d pk_len=%llu sk_len=%llu\n",
           failure_mode, rng_calls, status, pk_len, sk_len);
    int valid = rng_calls > 0 && status == 0 && pk_len == pk_capacity &&
                sk_len == sk_capacity;
    free(sk);
    free(pk);
    return valid ? 0 : 1;
}
