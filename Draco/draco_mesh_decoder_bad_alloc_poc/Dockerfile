FROM ubuntu:22.04 AS builder
ARG DEBIAN_FRONTEND=noninteractive
# Default reproduces against current main. Override with --build-arg DRACO_REF=<commit-or-tag>
ARG DRACO_REF=main

RUN apt-get update && apt-get install -y \
    build-essential git cmake make ca-certificates && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /build
RUN git clone https://github.com/google/draco.git
WORKDIR /build/draco
RUN git fetch --tags --force && \
    git checkout ${DRACO_REF} && \
    git rev-parse HEAD > /build/draco_commit.txt

RUN mkdir build && cd build && \
    cmake .. \
      -DCMAKE_BUILD_TYPE=Debug \
      -DDRACO_POINT_CLOUD_COMPRESSION=OFF \
      -DBUILD_SHARED_LIBS=OFF && \
    make -j$(nproc)

COPY poc.cc /build/poc.cc
COPY crash.bin /build/crash.bin
WORKDIR /build
RUN g++ -g -std=c++11 -o poc poc.cc \
    -I/build/draco/src \
    -I/build/draco/build \
    /build/draco/build/libdraco.a

FROM ubuntu:22.04
ARG DEBIAN_FRONTEND=noninteractive
COPY --from=builder /build/poc /poc
COPY --from=builder /build/crash.bin /crash.bin
COPY --from=builder /build/draco_commit.txt /draco_commit.txt
CMD ["/bin/sh", "-c", "echo 'Draco commit:' $(cat /draco_commit.txt); echo 'PoC input size:' $(wc -c < /crash.bin) bytes; ulimit -v 1048576; /poc /crash.bin"]
