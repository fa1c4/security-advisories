#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "api.h"
#include "drng.h"
#include "owcpa.h"
#include "verify.h"

DRNG_ctx drng_algorithm;

int main(int argc, char **argv)
{
    unsigned char seed[32];
    int reject_mode = argc >= 2 && (strcmp(argv[1], "reject") == 0 ||
                                    strcmp(argv[1], "valid") == 0);
    FILE *input;
    uint8_t *pk = malloc(KEM_PUBLICKEYBYTES);
    uint8_t *sk_base = malloc(KEM_SECRETKEYBYTES + (reject_mode ? KEM_BYTES : 0));
    uint8_t *sk = sk_base == NULL ? NULL : sk_base + (reject_mode ? KEM_BYTES : 0);
    uint8_t *ct = malloc(KEM_CIPHERTEXTBYTES);
    uint8_t ss1[KEM_BYTES], ss2[KEM_BYTES];
    int rc;

    if (argc < 2 || (strcmp(argv[1], "reject") == 0 ? argc != 4 : argc != 3) ||
        pk == NULL || sk == NULL || ct == NULL)
        return 2;
    input = fopen(argv[2], "rb");
    if (input == NULL || fread(seed, 1, sizeof seed, input) != sizeof seed ||
        fgetc(input) != EOF) {
        fprintf(stderr, "expected exactly 32 seed bytes in %s\n", argv[2]);
        if (input != NULL)
            fclose(input);
        return 2;
    }
    fclose(input);
    if (init_random_number(&drng_algorithm, seed, sizeof seed) != 0)
        return 2;
    if (mkem_keygen(pk, sk) != 0)
        return 2;
    if (strcmp(argv[1], "detenc") == 0) {
        fprintf(stderr, "calling mdkem_enc with a valid public key\n");
        rc = mdkem_enc(pk, (uint8_t *)seed, ss1, ct);
    } else if (strcmp(argv[1], "dec") == 0) {
        if (mkem_enc(pk, ss1, ct) != 0)
            return 2;
        fprintf(stderr, "calling mkem_dec with a valid ciphertext and exact-size secret key\n");
        rc = mkem_dec(sk, ct, ss2);
        if (rc == 0)
            fprintf(stderr, "shared secrets equal=%s\n", memcmp(ss1, ss2, KEM_BYTES) == 0 ? "yes" : "no");
    } else if (strcmp(argv[1], "valid") == 0) {
        if (mkem_enc(pk, ss1, ct) != 0)
            return 2;
        rc = mkem_dec(sk, ct, ss2);
        printf("valid decapsulation, shared secrets equal=%s\n",
               rc == 0 && memcmp(ss1, ss2, KEM_BYTES) == 0 ? "yes" : "no");
        if (rc != 0 || memcmp(ss1, ss2, KEM_BYTES) != 0)
            return 1;
    } else if (reject_mode) {
        uint8_t fallback_input[KEM_BYTES + KEM_CIPHERTEXTBYTES];
        uint8_t expected[KEM_BYTES];
        uint8_t recovered[3 * SEED_BYTES];
        uint8_t key_coins[2 * SEED_BYTES];
        uint8_t reencryption[CT_BYTES + 10];
        size_t mutation_offset;
        unsigned int mutation_mask;
        int mismatch;
        input = fopen(argv[3], "r");
        if (input == NULL || fscanf(input, "%zu %x", &mutation_offset, &mutation_mask) != 2 ||
            mutation_offset >= CT_BYTES || mutation_mask == 0 || mutation_mask > 0xff) {
            fprintf(stderr, "expected ciphertext byte offset and nonzero hex mask in %s\n", argv[3]);
            if (input != NULL)
                fclose(input);
            return 2;
        }
        fclose(input);
        if (mkem_enc(pk, ss1, ct) != 0)
            return 2;
        ct[mutation_offset] ^= (uint8_t)mutation_mask;
        owcpa_dec(recovered, ct, sk);
        memcpy(recovered + SEED_BYTES, sk + SK_BYTES - 2 * SEED_BYTES, SEED_BYTES);
        Hash2(key_coins, recovered, 2 * SEED_BYTES);
        owcpa_enc(reencryption, recovered, pk, key_coins + SEED_BYTES);
        mismatch = verify(ct, reencryption, CT_BYTES);
        if (mismatch == 0) {
            fprintf(stderr, "tampering did not trigger re-encryption mismatch\n");
            return 2;
        }
        memcpy(fallback_input, sk + KEM_SECRETKEYBYTES - KEM_BYTES, KEM_BYTES);
        memcpy(fallback_input + KEM_BYTES, ct, KEM_CIPHERTEXTBYTES);
        KDF(expected, KEM_BYTES, fallback_input, sizeof fallback_input);
        rc = mkem_dec(sk, ct, ss1);
        if (rc != 0)
            return 2;
        memset(sk + KEM_SECRETKEYBYTES - KEM_BYTES, 0x5a, KEM_BYTES);
        rc = mkem_dec(sk, ct, ss2);
        printf("re-encryption mismatch=%d, secret equals H3(s,c)=%s, unchanged after s changes=%s\n",
               mismatch,
               memcmp(ss1, expected, KEM_BYTES) == 0 ? "yes" : "no",
               memcmp(ss1, ss2, KEM_BYTES) == 0 ? "yes" : "no");
        if (memcmp(ss1, expected, KEM_BYTES) == 0 || memcmp(ss1, ss2, KEM_BYTES) != 0)
            return 1;
    } else {
        return 2;
    }
    free(pk);
    free(sk_base);
    free(ct);
    return rc;
}
