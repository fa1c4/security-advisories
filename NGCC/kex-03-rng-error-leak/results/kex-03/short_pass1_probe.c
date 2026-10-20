#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

static int rng_failure;
static volatile int count_target_allocations;
static void *target_allocations[4];
static size_t target_allocation_count;

void *__real_calloc(size_t count, size_t size);
void __real_free(void *pointer);

void *__wrap_calloc(size_t count, size_t size) {
    void *pointer = __real_calloc(count, size);
    if (count_target_allocations && pointer != NULL && target_allocation_count < 4)
        target_allocations[target_allocation_count++] = pointer;
    return pointer;
}

void __wrap_free(void *pointer) {
    for (size_t i = 0; i < target_allocation_count; ++i)
        if (target_allocations[i] == pointer) target_allocations[i] = NULL;
    __real_free(pointer);
}

#include "KEX_AlgorithmInstance.c"

DRNG_ctx drng_algorithm;

int get_random_number(DRNG_ctx *context, unsigned char *out, unsigned long long bits) {
    (void)context;
    if (rng_failure) return -1;
    memset(out, 0x42, (size_t)((bits + 7) / 8));
    return 0;
}

int pseudoXOF(unsigned long long output_bits, const unsigned char *message,
              unsigned long long message_bits, unsigned char *out) {
    (void)message;
    (void)message_bits;
    memset(out, 0x24, (size_t)((output_bits + 7) / 8));
    return 0;
}

void pke_enc(unsigned char *pk, unsigned char *message, unsigned char *seed,
             unsigned char *ct) {
    (void)pk;
    (void)message;
    (void)seed;
    memset(ct, 0, C_TILDE_LEN);
}

int kem_enc(unsigned char *pk, unsigned long long pk_len, unsigned char *ss,
            unsigned long long *ss_len, unsigned char *ct,
            unsigned long long *ct_len) {
    (void)pk;
    (void)pk_len;
    memset(ss, 0, KEM_K_LEN);
    memset(ct, 0, C_I_LEN);
    *ss_len = KEM_K_LEN;
    *ct_len = C_I_LEN;
    return 0;
}

int sig_sign(unsigned char *sk, unsigned long long sk_len, unsigned char *message,
             unsigned long long message_len, unsigned char *signature,
             unsigned long long *signature_len) {
    (void)sk;
    (void)sk_len;
    (void)message;
    (void)message_len;
    (void)signature;
    *signature_len = 0;
    return 0;
}

int sig_verify(unsigned char *pk, unsigned long long pk_len,
               unsigned char *signature, unsigned long long signature_len,
               unsigned char *message, unsigned long long message_len) {
    (void)pk;
    (void)pk_len;
    (void)signature;
    (void)signature_len;
    (void)message;
    (void)message_len;
    return -1;
}

int main(int argc, char **argv) {
    if (argc > 1 && (strcmp(argv[1], "derive_valid") == 0 ||
                     strcmp(argv[1], "derive_short") == 0)) {
        size_t mb_size = strcmp(argv[1], "derive_valid") == 0
                             ? C_TILDE_LEN + C_I_LEN : 1;
        unsigned char *ska = calloc(TSK_LEN + PKI_LEN, 1);
        unsigned char *pkb = calloc(PKR_LEN, 1);
        unsigned char *mb = calloc(mb_size, 1);
        unsigned char *sta = calloc(TPK_LEN + TSK_LEN, 1);
        unsigned char *ssa = calloc(K_LEN, 1);
        unsigned long long ssa_len = 0;
        if (!ska || !pkb || !mb || !sta || !ssa) return 2;
        int status = kex_derive_ss_a(ska, TSK_LEN + PKI_LEN, pkb, PKR_LEN,
                                      mb, mb_size, sta, TPK_LEN + TSK_LEN,
                                      ssa, &ssa_len);
        printf("derive_input_bytes=%zu status=%d\n", mb_size, status);
        free(ssa);
        free(sta);
        free(mb);
        free(pkb);
        free(ska);
        return status == -1 ? 0 : 1;
    }
    size_t m1_size = argc > 1 && strcmp(argv[1], "valid") == 0 ? TPK_LEN : 1;
    if (argc > 1 && strcmp(argv[1], "rng_fail") == 0) {
        m1_size = TPK_LEN;
        rng_failure = 1;
    }
    unsigned char *skb = calloc(SKR_LEN, 1);
    unsigned char *pka = calloc(PKI_LEN, 1);
    unsigned char *m1 = malloc(m1_size);
    unsigned char *stb = calloc(K_LEN, 1);
    unsigned char *m2 = calloc(TPK_LEN + C_TILDE_LEN + C_I_LEN + SIG_LEN, 1);
    unsigned long long stb_len = 0, m2_len = 0;
    if (!skb || !pka || !m1 || !stb || !m2) return 2;
    memset(m1, 1, m1_size);
    count_target_allocations = 1;
    int status = kex_generate_pass2_msg_b(skb, SKR_LEN, pka, PKI_LEN,
                                           m1, m1_size, stb, &stb_len, m2, &m2_len);
    count_target_allocations = 0;
    size_t live = 0;
    for (size_t i = 0; i < target_allocation_count; ++i)
        live += target_allocations[i] != NULL;
    printf("input_bytes=%zu status=%d target_allocations=%zu live=%zu\n",
           m1_size, status, target_allocation_count, live);
    for (size_t i = 0; i < target_allocation_count; ++i)
        if (target_allocations[i] != NULL) __real_free(target_allocations[i]);
    free(m2);
    free(stb);
    free(m1);
    free(pka);
    free(skb);
    if (rng_failure) return status == -1 && live == 2 ? 0 : 1;
    return live == 0 ? 0 : 1;
}
