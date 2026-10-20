#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "CryptHash_AlgorithmInstance.h"
#include "laurus_xof.h"

int main(int argc, char **argv) {
    if (argc != 3) return 2;
    const int bits = atoi(argv[2]);
    if (bits != 512 && bits != 1024 && bits != 2048) return 2;
    const size_t requested = (size_t)bits / 8;
    const int guarded = strcmp(argv[1], "guarded") == 0;
    const int core = strcmp(argv[1], "core_exact") == 0;
    if (!guarded && !core && strcmp(argv[1], "exact") != 0) return 2;
    const size_t capacity = guarded ? 272 : requested;
    uint8_t *out = malloc(capacity);
    if (out == NULL) return 2;
    memset(out, 0xa5, capacity);
    const uint8_t message = 0x80;
    if (core) laurus_xof(out, (uint64_t)bits, &message, 1);
    else if (CryptHash(bits, &message, 1, out) != 0) return 2;
    if (guarded) {
        uint8_t expected[256];
        uint8_t full_expected[256];
        laurus_xof(expected, (uint64_t)bits, &message, 1);
        laurus_xof(full_expected, 2048, &message, 1);
        size_t changed = 0;
        size_t excess = 0;
        for (size_t i = requested; i < 256; ++i) changed += out[i] != 0xa5;
        for (size_t i = 256; i < capacity; ++i) excess += out[i] != 0xa5;
        const int prefix_equal = memcmp(out, expected, requested) == 0;
        const int full_equal = memcmp(out, full_expected, 256) == 0;
        printf("bits=%d requested_bytes=%zu prefix_equal=%d full_2048_equal=%d modified_after_request=%zu modified_after_256=%zu\n",
               bits, requested, prefix_equal, full_equal, changed, excess);
        free(out);
        return prefix_equal && full_equal && excess == 0 ? 0 : 1;
    }
    printf("api=%s bits=%d exact_capacity=%zu call_returned=0\n",
           core ? "laurus_xof" : "CryptHash", bits, capacity);
    free(out);
    return 0;
}
