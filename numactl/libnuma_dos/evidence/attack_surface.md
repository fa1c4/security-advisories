# Attack Surface

The reachable public API is `numa_parse_nodestring()`, used by libnuma consumers and numactl-style tooling that accepts user-controlled NUMA node masks or node ranges.

An attacker who can supply a nodestring to a privileged service, management wrapper, or CLI invocation using libnuma can force CPU consumption during parsing. The reproduction uses a minimal program calling the public libnuma parser, not an OSS-Fuzz-only harness path.
