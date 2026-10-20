#include <stdio.h>
#include <string.h>

#include "KEX_AlgorithmInstance.h"
#include "drng.h"
#include "encode_sizes.h"

DRNG_ctx drng_algorithm;

void __wrap_make_SecretKey(void *sk, void *rng) {
    (void)rng;
    memset(sk, 0x42, SECRETKEY_BYTES);
}

void __wrap_niike_PublicKey(void *public_key, void *secret_key) {
    (void)secret_key;
    memset(public_key, 0, 1);
}

void __wrap_encode_bundlevertex(void *out, const void *public_key) {
    (void)public_key;
    memset(out, 0x43, PUBLICKEY_BYTES);
}

void __wrap_decode_bundlevertex(void *public_key, void *in) {
    (void)in;
    memset(public_key, 0, 1);
}

void __wrap_niike_SecretAgreement(void *shared_key,
                                  const void *public_key, void *secret_key) {
    (void)public_key;
    (void)secret_key;
    memset(shared_key, 0, 1);
}

void __wrap_fp2_encode(void *out, const void *shared_key) {
    (void)shared_key;
    memset(out, 0x44, SHAREDKEY_BYTES);
}

static int run_case(int responder, int prefilled) {
    unsigned char public_key[PUBLICKEY_BYTES] = {0};
    unsigned char secret_key[SECRETKEY_BYTES] = {0};
    unsigned char shared_key[SHAREDKEY_BYTES] = {0};
    unsigned char state[1] = {0};
    unsigned long long pk_len = prefilled ? kex_get_pk_len_bytes() : 0;
    unsigned long long sk_len = prefilled ? kex_get_sk_len_bytes() : 0;
    unsigned long long ss_len = prefilled ? kex_get_ss_len_bytes() : 0;
    unsigned long long state_len = 7;

    int init_status = responder
        ? kex_init_b(public_key, &pk_len, secret_key, &sk_len,
                     state, &state_len)
        : kex_init_a(public_key, &pk_len, secret_key, &sk_len,
                     state, &state_len);
    int derive_status = responder
        ? kex_derive_ss_b(secret_key, sizeof(secret_key),
                          public_key, sizeof(public_key), NULL, 0,
                          state, 0, shared_key, &ss_len)
        : kex_derive_ss_a(secret_key, sizeof(secret_key),
                          public_key, sizeof(public_key), NULL, 0,
                          state, 0, shared_key, &ss_len);
    printf("side=%c prefilled=%d init_status=%d pk_len=%llu sk_len=%llu state_len=%llu "
           "pk_written=%d sk_written=%d\n",
           responder ? 'b' : 'a', prefilled, init_status, pk_len, sk_len,
           state_len,
           public_key[0] == 0x43, secret_key[0] == 0x42);
    printf("derive_status=%d ss_len=%llu ss_written=%d\n",
           derive_status, ss_len, shared_key[0] == 0x44);
    return init_status != 0 || derive_status != 0 ||
           pk_len != (prefilled ? PUBLICKEY_BYTES : 0) ||
           sk_len != (prefilled ? SECRETKEY_BYTES : 0) ||
           state_len != 7 ||
           ss_len != (prefilled ? SHAREDKEY_BYTES : 0) ||
           public_key[0] != 0x43 || secret_key[0] != 0x42 ||
           shared_key[0] != 0x44;
}

int main(void) {
    int failed = 0;
    for (int responder = 0; responder < 2; responder++) {
        for (int prefilled = 0; prefilled < 2; prefilled++) {
            failed |= run_case(responder, prefilled);
        }
    }
    return failed;
}
