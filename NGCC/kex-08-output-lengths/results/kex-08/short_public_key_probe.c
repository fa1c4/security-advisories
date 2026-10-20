#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "KEX_AlgorithmInstance.h"
#include "drng.h"
#include "encode_sizes.h"

DRNG_ctx drng_algorithm;

void __wrap_niike_SecretAgreement(void *shared_key, const void *public_key,
                                  void *secret_key) {
    (void)public_key;
    (void)secret_key;
    memset(shared_key, 0, 1);
}

void __wrap_fp2_encode(void *out, const void *shared_key) {
    (void)shared_key;
    memset(out, 0x44, SHAREDKEY_BYTES);
}

int main(int argc, char **argv) {
    int responder = argc > 1 && argv[1][0] == 'b';
    int exact = argc > 1 && strstr(argv[1], "exact") != NULL;
    size_t input_size = exact ? PUBLICKEY_BYTES : 1;
    unsigned char *public_key = calloc(input_size, 1);
    unsigned char secret_key[SECRETKEY_BYTES] = {0};
    unsigned char shared_key[SHAREDKEY_BYTES] = {0};
    unsigned long long shared_length = 0;
    if (!public_key) return 2;
    int status = responder
        ? kex_derive_ss_b(secret_key, sizeof(secret_key), public_key,
                          input_size, NULL, 0, NULL, 0,
                          shared_key, &shared_length)
        : kex_derive_ss_a(secret_key, sizeof(secret_key), public_key,
                          input_size, NULL, 0, NULL, 0,
                          shared_key, &shared_length);
    printf("side=%c input_bytes=%zu status=%d shared_written=%d\n",
           responder ? 'b' : 'a', input_size, status,
           shared_key[0] == 0x44);
    free(public_key);
    return status != 0 || shared_key[0] != 0x44;
}
