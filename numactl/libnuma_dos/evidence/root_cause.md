# Root Cause

The issue is a deterministic CPU-bound denial of service in libnuma nodestring parsing.

- Source file: `libnuma.c`.
- Function: `__numa_parse_nodestring()`.
- Hot source line: `libnuma.c:2174` in the range-fill loop.
- Public wrapper: `numa_parse_nodestring()`.
- Input: `evidence/inputs/poc_input`.

`gdb` stopped the process during the hang with:

```text
arg2 = 18446744065119617025
arg = 8073321089
__numa_parse_nodestring() at libnuma.c:2174
numa_parse_nodestring() at libnuma.c:2208
```

The parser accepts a malformed range whose upper bound becomes a huge `unsigned long`, but membership checks truncate through the bitmask API. The loop then increments `arg` toward `arg2`, producing a near-infinite CPU loop.

`evidence/logs/time.txt` records a 30-second timeout with 99% CPU and low RSS, so this is a CPU hang rather than OOM.
