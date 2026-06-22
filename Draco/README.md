# Draco mesh decoder bad_alloc / unbounded allocation PoC

## Summary

A 50-byte Draco-like input causes Draco's mesh decoder API to throw `std::bad_alloc` and abort the process when run under a 1 GiB virtual-memory limit.

The PoC uses the decoder path that Draco's security model describes as intended to be robust against malicious or malformed input:

```cpp
draco::DecoderBuffer buffer;
buffer.Init(reinterpret_cast<const char*>(data.data()), data.size());

draco::Decoder decoder;
auto status = decoder.DecodeMeshFromBuffer(&buffer);
```

## Files

- `poc.cc` - standalone API-level reproducer
- `crash.bin` - 50-byte malformed input beginning with `DRACO`
- `Dockerfile` - reproducible build/run container

## Build and run

```bash
docker build --no-cache -t draco-mesh-decoder-badalloc-poc .
docker run --rm draco-mesh-decoder-badalloc-poc
```

To test a specific ref:

```bash
docker build --no-cache \
  --build-arg DRACO_REF=<commit-or-tag> \
  -t draco-mesh-decoder-badalloc-poc .
docker run --rm draco-mesh-decoder-badalloc-poc
```

## Expected vulnerable output

```text
terminate called after throwing an instance of 'std::bad_alloc'
  what():  std::bad_alloc
Aborted (core dumped)
```

## Notes

The confirmed behavior is process abort / denial of service caused by uncontrolled allocation or missing bounds checks while decoding a tiny malformed input. Code execution, privilege escalation, and information disclosure are not claimed.
