# TRE approximate matching heap out-of-bounds read PoC

## Build and run

```sh
docker build -t poc-tre-approx-heap-oob .
docker run --rm poc-tre-approx-heap-oob
```

## Expected vulnerable behavior

The vulnerable commit should report an AddressSanitizer heap-buffer-overflow read in the TRE approximate matching path when running the byte-mode approximate matcher on a short input.

## Affected commit

`laurikari/tre@71bfcaf0af3994384987c6c2679ed7d078ffe189`

## Trigger path

`tre_regncompb()` compiles a byte-mode regular expression, and `tre_regaexecb()` executes approximate matching with attacker-controlled pattern/text and approximate matching parameters.

## Expected correct behavior

The matcher should either return no match / an error or complete normally. It should not read outside heap allocations.
