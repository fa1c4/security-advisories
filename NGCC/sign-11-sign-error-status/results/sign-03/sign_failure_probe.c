#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "api.h"
#include "hash.h"

static int force_failure = 1;

void randombytes(unsigned char *output, unsigned long long length) {
    memset(output, 0x42, (size_t)length);
}

int __real_hash_message(unsigned char *digest, uint64_t *tree,
                        uint32_t *leaf_idx, const unsigned char *R,
                        const unsigned char *pk, const unsigned char *m,
                        unsigned long long mlen, const spx_ctx *ctx
#ifndef FLEXTREE
                        , uint32_t *counter
#endif
                        );

int __wrap_hash_message(unsigned char *digest, uint64_t *tree,
                        uint32_t *leaf_idx, const unsigned char *R,
                        const unsigned char *pk, const unsigned char *m,
                        unsigned long long mlen, const spx_ctx *ctx
#ifndef FLEXTREE
                        , uint32_t *counter
#endif
                        ) {
    if (!force_failure)
        return __real_hash_message(digest, tree, leaf_idx, R, pk, m, mlen,
                                   ctx
#ifndef FLEXTREE
                                   , counter
#endif
                                   );
    return -1;
}

int main(int argc, char **argv) {
    force_failure = !(argc > 1 && strcmp(argv[1], "control") == 0);
    const unsigned char message[1] = {0x42};
    unsigned char *secret_key = calloc(CRYPTO_SECRETKEYBYTES, 1);
    unsigned char *signed_message = malloc(CRYPTO_BYTES + sizeof(message));
    unsigned long long signed_length = 0;
    if (!secret_key || !signed_message) return 2;
    memset(signed_message, 0xA5, CRYPTO_BYTES + sizeof(message));
    int status = crypto_sign(signed_message, &signed_length, message,
                             sizeof(message), secret_key);
    printf("forced_hash_failure=%d sign_status=%d signed_length=%llu tail_byte=%02x\n",
           force_failure, status, signed_length, signed_message[CRYPTO_BYTES]);
    int reproduced = status == 0 && signed_message[CRYPTO_BYTES] == message[0] &&
                     (force_failure || signed_length == CRYPTO_BYTES + sizeof(message));
    free(signed_message);
    free(secret_key);
    return reproduced ? 0 : 1;
}
