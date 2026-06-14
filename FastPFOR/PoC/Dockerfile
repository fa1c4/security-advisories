FROM ubuntu:24.04

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
      ca-certificates git clang g++ llvm-18 libclang-rt-18-dev && \
    rm -rf /var/lib/apt/lists/*

ENV ASAN_SYMBOLIZER_PATH=/usr/bin/llvm-symbolizer-18
ENV ASAN_OPTIONS=symbolize=1:abort_on_error=1:detect_leaks=0

RUN git clone https://github.com/fast-pack/FastPFOR.git /src/FastPFOR && \
    cd /src/FastPFOR && \
    git checkout 2457e1ed1af35bbf7f4c509c863fa9797e637cb3

COPY poc.cc /src/poc.cc

RUN clang++ -std=c++17 -fsanitize=address,undefined -fno-omit-frame-pointer -g -O1 -msse4.2 \
    -I/src/FastPFOR/headers \
    /src/poc.cc \
    /src/FastPFOR/src/bitpacking.cpp \
    /src/FastPFOR/src/bitpackingaligned.cpp \
    /src/FastPFOR/src/bitpackingunaligned.cpp \
    /src/FastPFOR/src/horizontalbitpacking.cpp \
    /src/FastPFOR/src/simdunalignedbitpacking.cpp \
    /src/FastPFOR/src/simdbitpacking.cpp \
    -o /src/poc

CMD ["/src/poc"]
