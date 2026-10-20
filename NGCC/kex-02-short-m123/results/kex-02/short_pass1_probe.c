#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "KEX_AlgorithmInstance.h"
#include "params.h"
#include "drng.h"

DRNG_ctx drng_algorithm;

int get_random_number(DRNG_ctx *context, unsigned char *out,
                      unsigned long long bits) {
    (void)context;
    memset(out, 0x42, (size_t)((bits + 7) / 8));
    return 0;
}

int main(int argc, char **argv) {
    const char *mode = argc > 1 ? argv[1] : "m1_short";
    int pass = mode[1] == '2' ? 3 : mode[1] == '3' ? 4 : 2;
    int valid = strstr(mode, "valid") != NULL;
    size_t expected_size = pass == 2 ? KYBER_CIPHERTEXTBYTES
                           : pass == 3 ? KYBER_CIPHERTEXTBYTES + KYBER_SYMBYTES
                                       : KYBER_SYMBYTES;
    size_t input_size = valid ? expected_size : 1;
    unsigned char *secret_key = calloc(kex_get_sk_len_bytes(), 1);
    unsigned char *public_key = calloc(kex_get_pk_len_bytes(), 1);
    unsigned char *input = calloc(input_size, 1);
    unsigned char *state = calloc(kex_get_sta_len_bytes(), 1);
    unsigned char *output = calloc(KYBER_CIPHERTEXTBYTES + KYBER_SYMBYTES, 1);
    unsigned long long state_len = 0, output_len = 0;
    if (!secret_key || !public_key || !input || !state || !output) return 2;
    int status;
    if (pass == 2)
        status = kex_generate_pass2_msg_b(secret_key, kex_get_sk_len_bytes(),
                                           public_key, kex_get_pk_len_bytes(),
                                           input, input_size, state, &state_len,
                                           output, &output_len);
    else if (pass == 3)
        status = kex_generate_pass3_msg_a(secret_key, kex_get_sk_len_bytes(),
                                           public_key, kex_get_pk_len_bytes(),
                                           input, input_size, state, &state_len,
                                           output, &output_len);
    else
        status = kex_generate_pass4_msg_b(secret_key, kex_get_sk_len_bytes(),
                                           public_key, kex_get_pk_len_bytes(),
                                           input, input_size, state, &state_len,
                                           output, &output_len);
    printf("pass=%d input_bytes=%zu status=%d output_bytes=%llu\n",
           pass, input_size, status, output_len);
    free(output);
    free(state);
    free(input);
    free(public_key);
    free(secret_key);
    return 0;
}
