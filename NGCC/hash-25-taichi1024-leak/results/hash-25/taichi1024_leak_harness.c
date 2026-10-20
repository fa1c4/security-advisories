#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "CryptHash_AlgorithmInstance.h"

static size_t allocations;
static size_t frees;
static int fail_next_allocation;

void *__real_malloc(size_t size);
void __real_free(void *ptr);

void *__wrap_malloc(size_t size)
{
    if (fail_next_allocation) {
        fail_next_allocation = 0;
        return NULL;
    }
    void *ptr = __real_malloc(size);
    if (ptr != NULL)
        allocations++;
    return ptr;
}

void __wrap_free(void *ptr)
{
    if (ptr != NULL)
        frees++;
    __real_free(ptr);
}

int main(int argc, char **argv)
{
    unsigned char input[4096];
    unsigned char digest[DIGEST_BIT_LENGTH / 8];
    FILE *file;
    size_t length;
    int status;

    if ((argc != 2 && argc != 3) || (argc == 3 && strcmp(argv[2], "fail") != 0) ||
        (file = fopen(argv[1], "rb")) == NULL)
        return 2;
    length = fread(input, 1, sizeof input, file);
    if (ferror(file) || (!feof(file) && length == sizeof input)) {
        fclose(file);
        return 2;
    }
    fclose(file);

    memset(digest, 0xa5, sizeof digest);
    fail_next_allocation = argc == 3;
    status = CryptHash(DIGEST_BIT_LENGTH, input, (unsigned long long)length * 8, digest);
    fail_next_allocation = 0;
    printf("CryptHash status=%d, allocations=%zu, frees=%zu, outstanding=%zu\n",
           status, allocations, frees, allocations - frees);
    if (argc == 3) {
        unsigned char untouched[sizeof digest];
        memset(untouched, 0xa5, sizeof untouched);
        printf("forced allocation failure, digest unchanged=%s\n",
               memcmp(digest, untouched, sizeof digest) == 0 ? "yes" : "no");
        return status == 0 && allocations == frees &&
               memcmp(digest, untouched, sizeof digest) == 0 ? 0 : 1;
    }
    {
        unsigned char untouched[sizeof digest];
        memset(untouched, 0xa5, sizeof untouched);
        return status == 0 && allocations == frees + 1 &&
               memcmp(digest, untouched, sizeof digest) != 0 ? 0 : 1;
    }
}
