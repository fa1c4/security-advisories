numactl / libnuma nodestring CPU DoS PoC (v2, unbuffered output)
=================================================================

Purpose
-------
This package reproduces a deterministic CPU-bound denial of service in libnuma
nodestring parsing. The public API path is numa_parse_nodestring(), which calls
__numa_parse_nodestring().

This v2 package updates the reproducer to disable stdout/stderr buffering and
flush output before the vulnerable parse call. This avoids the confusing case
where Docker shows no output because timeout kills the process before stdio
buffers are flushed.

Tested version
--------------
numactl commit da84fd9e18a50e3933c7cd40c22a72361692b4e8

Files
-----
- Dockerfile: reproducible build/run environment
- poc_reproducer.c: standalone reproducer calling numa_parse_nodestring()
- poc_input: malformed nodestring trigger
- docker_build.log: captured build log from original run
- docker_run.log: captured vulnerable run log from original run
- evidence/: supporting logs and root-cause notes

Build and run
-------------

    docker build -t numactl-nodestring-cpu-dos-poc .
    docker run --rm numactl-nodestring-cpu-dos-poc; echo "exit=$?"

Expected vulnerable result
--------------------------
The program prints the root-cause information, then enters a CPU-bound loop in
numa_parse_nodestring() and is terminated by timeout. Depending on timeout
options, the container may exit with status 124 or 143.

Additional evidence
-------------------
The evidence/ directory contains a GDB backtrace showing execution inside
__numa_parse_nodestring(), along with timing evidence showing timeout with high
CPU and low RSS. This is a CPU hang, not an OOM.
