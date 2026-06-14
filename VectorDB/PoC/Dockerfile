FROM ubuntu:24.04

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
      ca-certificates git clang g++ llvm-18 libclang-rt-18-dev && \
    rm -rf /var/lib/apt/lists/*

ENV ASAN_SYMBOLIZER_PATH=/usr/bin/llvm-symbolizer-18
ENV ASAN_OPTIONS=symbolize=1:abort_on_error=1:detect_leaks=0
ENV UBSAN_OPTIONS=print_stacktrace=1:halt_on_error=1

WORKDIR /src

RUN git clone https://github.com/epsilla-cloud/vectordb.git /src/vectordb && \
    cd /src/vectordb && \
    git checkout df5a5f5afb85a2376a0f2f316c79dea9b2c6ac7a

COPY poc.cc /src/poc.cc

RUN clang++ -std=c++17 -fsanitize=address,undefined -fno-omit-frame-pointer -g -O1 \
    -I/src/vectordb/engine \
    /src/poc.cc \
    /src/vectordb/engine/query/expr/expr.cpp \
    /src/vectordb/engine/utils/status.cpp \
    /src/vectordb/engine/utils/json.cpp \
    /src/vectordb/engine/logger/logger.cpp \
    -o /src/poc

CMD ["/src/poc"]
