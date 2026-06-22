# Verdict

READY_FOR_CVE

Evidence supports a deterministic CPU DoS:

- 30-second timeout: yes, exit 143.
- CPU near 100%: yes, 99% in `evidence/logs/time.txt`.
- RSS growth: no, max RSS 2088 KB.
- Source-level hang stack: yes, `evidence/logs/gdb_bt.txt` stops in `__numa_parse_nodestring()`.

Recommended next step: submit to numactl/libnuma maintainers as CWE-835 / integer-width validation mismatch in nodestring range parsing.
