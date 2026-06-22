FROM ubuntu:noble AS builder
ARG DEBIAN_FRONTEND=noninteractive
ARG KEYSTONE_COMMIT=fb92f32391c6cced868252167509590319eeb58b

RUN apt-get update && apt-get install -y \
    build-essential git cmake make python3 ca-certificates

WORKDIR /build
RUN git clone https://github.com/keystone-engine/keystone.git
WORKDIR /build/keystone
RUN git fetch --depth 1 origin ${KEYSTONE_COMMIT} && \
    git checkout FETCH_HEAD && \
    test "$(git rev-parse HEAD)" = "${KEYSTONE_COMMIT}"

RUN mkdir build && cd build && \
    cmake .. && \
    make -j$(nproc) && \
    make install && \
    ldconfig

COPY poc.c /build/poc.c
COPY crash.bin /build/crash.bin

WORKDIR /build
RUN gcc -g -o poc poc.c \
    -I/usr/local/include \
    -L/usr/local/lib \
    -lkeystone -lstdc++ -lm -lpthread

FROM ubuntu:noble
ARG DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y libstdc++6 && rm -rf /var/lib/apt/lists/*
COPY --from=builder /build/poc /poc
COPY --from=builder /build/crash.bin /crash.bin
COPY --from=builder /usr/local/lib/libkeystone.a /usr/local/lib/libkeystone.a
CMD ["/poc", "/crash.bin"]
