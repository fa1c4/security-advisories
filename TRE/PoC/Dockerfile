FROM ubuntu:24.04

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
      ca-certificates git clang g++ llvm-18 libclang-rt-18-dev \
      make autoconf automake libtool gettext autopoint pkg-config && \
    rm -rf /var/lib/apt/lists/*

ENV ASAN_SYMBOLIZER_PATH=/usr/bin/llvm-symbolizer-18
ENV ASAN_OPTIONS=symbolize=1:abort_on_error=1:detect_leaks=0

RUN git clone https://github.com/laurikari/tre.git /src/tre && \
    cd /src/tre && \
    git checkout 71bfcaf0af3994384987c6c2679ed7d078ffe189

WORKDIR /src/tre

RUN ./utils/autogen.sh && \
    CC=clang CFLAGS='-fsanitize=address -fno-omit-frame-pointer -g -O1' \
    ./configure --enable-static --disable-shared && \
    make -j"$(nproc)"

COPY poc.cc /src/poc.cc

RUN clang++ -std=c++17 -fsanitize=address -fno-omit-frame-pointer -g -O1 \
    -I/src/tre/include -I/src/tre \
    /src/poc.cc /src/tre/lib/.libs/libtre.a \
    -o /src/poc

CMD ["/src/poc"]
