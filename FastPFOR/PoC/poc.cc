#include <cstdint>
#include <cstring>
#include <iostream>
#include <vector>
#include "fastpfor.h"

int main() {
    using namespace FastPForLib;
    uint8_t raw[4] = {1, 0, 0, 0};
    std::vector<uint32_t> comp(1);
    std::memcpy(comp.data(), raw, sizeof(raw));
    std::vector<uint32_t> out(1 << 16);
    FastPFor<> fp;
    size_t out_n = out.size();
    fp.decodeArray(comp.data(), comp.size(), out.data(), out_n);
    std::cout << "decode returned without sanitizer failure\n";
    return 0;
}
