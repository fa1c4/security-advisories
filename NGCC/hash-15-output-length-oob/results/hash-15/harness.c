#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "CryptHash_AlgorithmInstance.h"
#include "litchi_xof.h"

static int load_input(const char *path, unsigned long long *message_bits, uint8_t *message) {
    FILE *file = fopen(path, "r");
    unsigned value = 0;
    if (file == NULL) return -1;
    char comment[128];
    if (fgets(comment, sizeof(comment), file) == NULL ||
        fscanf(file, "%llu %2x", message_bits, &value) != 2) {
        fclose(file);
        return -1;
    }
    fclose(file);
    if (*message_bits != 1 || value > 255) return -1;
    *message = (uint8_t)value;
    return 0;
}

int main(int argc, char **argv) {
    if (argc != 4 || (strcmp(argv[1], "guarded") && strcmp(argv[1], "exact") &&
                      strcmp(argv[1], "core_exact"))) {
        fprintf(stderr, "usage: %s guarded|exact|core_exact 512|768|1024 input.txt\n", argv[0]);
        return 2;
    }
    const int bits = atoi(argv[2]);
    if (bits != 512 && bits != 768 && bits != 1024) return 2;
    unsigned long long message_bits = 0;
    uint8_t message = 0;
    if (load_input(argv[3], &message_bits, &message) != 0) return 2;

    const size_t requested = (size_t)bits / 8;
    const int guarded = strcmp(argv[1], "guarded") == 0;
    const size_t capacity = guarded ? 144 : requested;
    uint8_t *output = malloc(capacity);
    if (output == NULL) return 2;
    memset(output, 0xa5, capacity);
    const int core = strcmp(argv[1], "core_exact") == 0;
    int rc = 0;
    if (core) litchi_xof(output, (uint64_t)bits, &message, message_bits);
    else rc = CryptHash(bits, &message, message_bits, output);
    if (rc != 0) return 2;

    if (guarded) {
        uint8_t expected[144];
        memset(expected, 0xa5, sizeof(expected));
        litchi_xof(expected, (uint64_t)bits, &message, message_bits);
        size_t changed = 0, after_128 = 0;
        for (size_t i = requested; i < 128; ++i) changed += output[i] != 0xa5;
        for (size_t i = 128; i < capacity; ++i) after_128 += output[i] != 0xa5;
        int prefix_equal = memcmp(output, expected, requested) == 0;
        printf("bits=%d requested_bytes=%zu prefix_equal=%d modified_after_request=%zu modified_after_128=%zu\n",
               bits, requested, prefix_equal, changed, after_128);
        free(output);
        return prefix_equal && changed == 128 - requested && after_128 == 0 ? 0 : 1;
    }

    printf("api=%s bits=%d exact_capacity=%zu call_returned=0\n",
           core ? "litchi_xof" : "CryptHash", bits, capacity);
    free(output);
    return 0;
}
