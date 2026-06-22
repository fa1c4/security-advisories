FROM ubuntu:24.04 AS builder
# Pinned commit: libgit2/libgit2.git
#   44c05e5d12f2b8b86b9730bb50f27daf74143782
#
ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    build-essential git cmake pkg-config \
    libssl-dev zlib1g-dev libpcre2-dev libhttp-parser-dev ca-certificates

WORKDIR /build

RUN git clone --depth 1 https://github.com/libgit2/libgit2.git

WORKDIR /build/libgit2

RUN mkdir -p build && cd build && \
    cmake .. \
    -DCMAKE_BUILD_TYPE=Debug \
    -DCMAKE_INSTALL_PREFIX=/usr/local \
    -DBUILD_SHARED_LIBS=OFF \
    -DBUILD_TESTS=OFF \
    -DBUILD_CLI=OFF \
    -DBUILD_CLAR=OFF \
    -DUSE_HTTPS=OFF \
    -DUSE_AUTH_NTLM=OFF \
    -DUSE_SSH=OFF \
    -DUSE_BUNDLED_ZLIB=ON && \
    make -j$(nproc) && \
    make install

COPY poc.c /build/poc.c
COPY crash.bin /build/crash.bin

WORKDIR /build
RUN gcc -g -o poc poc.c \
    $(PKG_CONFIG_PATH=/usr/local/lib/pkgconfig pkg-config --cflags --static libgit2) \
    -I/build/libgit2/src/libgit2 \
    -I/build/libgit2/src/util \
    -I/build/libgit2/build/include \
    -I/build/libgit2/build/gen_headers \
    -I/build/libgit2/build/src/libgit2 \
    -I/build/libgit2/build/src/util \
    $(PKG_CONFIG_PATH=/usr/local/lib/pkgconfig pkg-config --libs --static libgit2) \
    -lpthread -ldl -lrt -lm

FROM ubuntu:24.04
ARG DEBIAN_FRONTEND=noninteractive
COPY --from=builder /build/poc /poc
COPY --from=builder /build/crash.bin /crash.bin
CMD ["/bin/sh", "-c", "ulimit -v 1048576 && /poc /crash.bin"]
