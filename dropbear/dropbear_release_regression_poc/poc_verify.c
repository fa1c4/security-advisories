#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "includes.h"
#include "buffer.h"
#include "signkey.h"
#include "crypto_desc.h"
#include "ed25519.h"

/*
 * Regression PoC for the Dropbear signature-verification issue associated
 * with CVE-2026-3706 / patch commit fdec3c90a15447bd538641d85e5a3e3ac981011d.
 *
 * This is derived from the OSS-Fuzz fuzzer-verify crash input. It does not
 * call abort(). Instead, it reports whether the crafted key/signature blob is
 * accepted by buf_verify().
 *
 * Expected on vulnerable builds:
 *   [!] VULNERABLE/REPRODUCED: crafted key/signature verified successfully
 *   exit 1
 *
 * Expected on the patched fdec3c90 commit or later:
 *   [*] Not reproduced: crafted key/signature was rejected
 *   exit 0
 */

static unsigned char *read_file(const char *path, long *size_out) {
    FILE *f = fopen(path, "rb");
    if (!f) {
        perror("fopen");
        return NULL;
    }

    if (fseek(f, 0, SEEK_END) != 0) {
        perror("fseek");
        fclose(f);
        return NULL;
    }

    long size = ftell(f);
    if (size < 0) {
        perror("ftell");
        fclose(f);
        return NULL;
    }

    if (fseek(f, 0, SEEK_SET) != 0) {
        perror("fseek");
        fclose(f);
        return NULL;
    }

    unsigned char *data = (unsigned char *)malloc((size_t)size);
    if (!data) {
        perror("malloc");
        fclose(f);
        return NULL;
    }

    size_t n = fread(data, 1, (size_t)size, f);
    fclose(f);
    if (n != (size_t)size) {
        fprintf(stderr, "short read: got %zu, expected %ld\n", n, size);
        free(data);
        return NULL;
    }

    *size_out = size;
    return data;
}

int main(int argc, char **argv) {
    const char *path = argc > 1 ? argv[1] : "/crash.bin";
    long fsize = 0;
    unsigned char *data = read_file(path, &fsize);
    if (!data) {
        return 2;
    }

    printf("[*] Dropbear signature verification regression PoC\n");
    printf("[*] Input: %s\n", path);
    printf("[*] Input size: %ld bytes\n", fsize);

    crypto_init();

    buffer *buf = buf_new((unsigned int)fsize);
    buf_putbytes(buf, data, (unsigned int)fsize);
    buf_setpos(buf, 0);

    int verified = 0;
    int parsed_key = 0;
    int boguskey = 0;

    sign_key *key = new_sign_key();
    enum signkey_type keytype = DROPBEAR_SIGNKEY_ANY;

    if (buf_get_pub_key(buf, key, &keytype) == DROPBEAR_SUCCESS) {
        parsed_key = 1;
        printf("[*] Parsed key type: %d\n", (int)keytype);

        buffer *verifydata = buf_new(30);
        buf_putstring(verifydata, "x", 1);

        enum signature_type sigtype;
        if (keytype == DROPBEAR_SIGNKEY_RSA) {
            sigtype = DROPBEAR_SIGNATURE_RSA_SHA256;
        } else {
            sigtype = signature_type_from_signkey(keytype);
        }

        if (buf_verify(buf, key, sigtype, verifydata) == DROPBEAR_SUCCESS) {
            verified = 1;
            printf("[!] buf_verify() returned success for crafted input\n");

            if (keytype == DROPBEAR_SIGNKEY_SK_ED25519 || keytype == DROPBEAR_SIGNKEY_ED25519) {
                dropbear_ed25519_key **eck = (dropbear_ed25519_key**)signkey_key_ptr(key, keytype);
                if (eck && *eck) {
                    boguskey = 1;
                    for (int i = 0; i < CURVE25519_LEN; i++) {
                        if ((*eck)->priv[i] != 0x00 || (*eck)->pub[i] != 0x00) {
                            boguskey = 0;
                        }
                    }
                }
            }
        } else {
            printf("[*] buf_verify() rejected crafted input\n");
        }

        buf_free(verifydata);
    } else {
        printf("[*] Public key parsing rejected crafted input\n");
    }

    sign_key_free(key);
    buf_free(buf);
    free(data);

    if (parsed_key && verified && !boguskey) {
        printf("[!] VULNERABLE/REPRODUCED: crafted key/signature verified successfully\n");
        return 1;
    }

    if (parsed_key && verified && boguskey) {
        printf("[*] Verification succeeded only for a bogus/all-zero key case ignored by the original fuzzer invariant\n");
        return 0;
    }

    printf("[*] Not reproduced: crafted key/signature was rejected\n");
    return 0;
}
