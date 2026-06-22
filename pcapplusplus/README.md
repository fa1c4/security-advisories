# PcapPlusPlus IPv4 IHL OOB Read PoC

This PoC reproduces a heap-buffer-overflow in PcapPlusPlus IPv4 parsing / field recomputation.

## Summary

A crafted pcap file contains an IPv4 packet whose IHL field is 15, claiming a 60-byte IPv4 header, while the actual IPv4 data available to the layer is shorter. `IPv4Layer::isDataValid()` accepts the packet because it checks the minimum IPv4 header size and IHL >= 5, but does not verify that `IHL * 4 <= dataLen`.

When `Packet::computeCalculateFields()` / `IPv4Layer::computeCalculateFields()` later computes the IPv4 header checksum using the untrusted IHL-derived length, PcapPlusPlus reads past the end of the allocated packet buffer.

## Build and run

```bash
docker build -t pcapplusplus-ipv4-ihl-oob-poc .
docker run --rm pcapplusplus-ipv4-ihl-oob-poc
```

## Expected vulnerable result

AddressSanitizer should report a heap-buffer-overflow similar to:

```text
ERROR: AddressSanitizer: heap-buffer-overflow
READ of size 2
    #0 pcpp::computeChecksum(...)
    #1 pcpp::IPv4Layer::computeCalculateFields()
    #2 pcpp::Packet::computeCalculateFields()
```

## Files

- `Dockerfile` - reproducible build environment
- `poc_reproducer.cpp` - minimal reproducer
- `poc_input` - crafted pcap input
- `docker_run.log` - observed ASan output
