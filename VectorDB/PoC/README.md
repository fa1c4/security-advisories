# VectorDB filter parser unmatched parenthesis PoC

## Build and run

```sh
docker build -t poc-vectordb-filter-parser-crash .
docker run --rm poc-vectordb-filter-parser-crash
```

## Expected vulnerable behavior

The vulnerable commit should terminate under sanitizer/hardened runtime when parsing the malformed filter expression `2)e+`. The parser accepts an unmatched closing parenthesis and `ShuntingYard()` unconditionally pops an empty operator stack.

## Affected commit

`epsilla-cloud/vectordb@df5a5f5afb85a2376a0f2f316c79dea9b2c6ac7a`

## Trigger input

```text
2)e+
```

## Expected correct behavior

The parser should reject the expression with an `INVALID_EXPR` status and should not terminate, corrupt memory, or invoke undefined behavior.
