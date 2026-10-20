#include <stdio.h>
#include <stdlib.h>

static size_t allocation_calls;
static size_t live_allocations;
static size_t denied_allocations;
static int failure_armed;

void *__real_malloc(size_t size);
void __real_free(void *pointer);

void *__wrap_malloc(size_t size) {
    ++allocation_calls;
    if (failure_armed && allocation_calls >= 2) {
        ++denied_allocations;
        return NULL;
    }
    void *pointer = __real_malloc(size);
    if (pointer != NULL) ++live_allocations;
    return pointer;
}

void __wrap_free(void *pointer) {
    if (pointer != NULL) --live_allocations;
    __real_free(pointer);
}

#include "SIG_AlgorithmInstance.c"

static int probe_sign(void) {
    SignBuffers buffers;
    allocation_calls = live_allocations = denied_allocations = 0;
    failure_armed = 1;
    int status = alloc_sign_buffers(&buffers);
    failure_armed = 0;
    printf("sign status=%d calls=%zu denied=%zu live_at_return=%zu\n",
           status, allocation_calls, denied_allocations, live_allocations);
    int reproduced = status != 0 && denied_allocations > 0 && live_allocations > 0;
    free_sign_buffers(&buffers);
    return reproduced && live_allocations == 0 ? 0 : 1;
}

static int probe_verify(void) {
    VerifyBuffers buffers;
    allocation_calls = live_allocations = denied_allocations = 0;
    failure_armed = 1;
    int status = alloc_verify_buffers(&buffers);
    failure_armed = 0;
    printf("verify status=%d calls=%zu denied=%zu live_at_return=%zu\n",
           status, allocation_calls, denied_allocations, live_allocations);
    int reproduced = status != 0 && denied_allocations > 0 && live_allocations > 0;
    free_verify_buffers(&buffers);
    return reproduced && live_allocations == 0 ? 0 : 1;
}

int main(void) {
    if (probe_sign() != 0) return 1;
    if (probe_verify() != 0) return 1;
    return 0;
}
