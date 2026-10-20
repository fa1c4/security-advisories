#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "CryptHash_AlgorithmInstance.h"

static void *tracked[16];
static size_t allocations;
static size_t frees;

void *__real_malloc(size_t size);
void __real_free(void *ptr);

void *__wrap_malloc(size_t size) {
    void *ptr = __real_malloc(size);
    if (ptr != NULL && allocations < 16) tracked[allocations++] = ptr;
    return ptr;
}

void __wrap_free(void *ptr) {
    for (size_t i = 0; i < allocations; ++i) {
        if (tracked[i] == ptr) {
            tracked[i] = NULL;
            ++frees;
            break;
        }
    }
    __real_free(ptr);
}

int main(void) {
    unsigned char message[1] = {0x80};
    unsigned char digest[(DIGEST_BIT_LENGTH + 7) / 8];
    memset(digest, 0xa5, sizeof digest);
    int status = CryptHash(DIGEST_BIT_LENGTH, message, 1, digest);
    size_t outstanding = 0;
    for (size_t i = 0; i < allocations; ++i) outstanding += tracked[i] != NULL;
    printf("status=%d digest_changed=%d allocations=%zu frees=%zu outstanding=%zu\n",
           status, digest[0] != 0xa5, allocations, frees, outstanding);
    for (size_t i = 0; i < allocations; ++i) if (tracked[i] != NULL) __real_free(tracked[i]);
    return status != 0 || digest[0] == 0xa5 || outstanding != 1;
}
