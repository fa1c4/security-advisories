FROM ubuntu:22.04 AS builder
ARG DEBIAN_FRONTEND=noninteractive
# CVE feeds mention fdec3c90a15447bd538641d85e5a3e3ac981011d as a "patch name",
# but that hash is not a reachable Git commit in mkj/dropbear. Use a real ref/tag.
# Recommended patched refs: DROPBEAR_2026.90 or DROPBEAR_2026.91.
ARG DROPBEAR_REF=DROPBEAR_2026.91

RUN apt-get update && apt-get install -y \
    build-essential git autoconf automake libtool ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /build
RUN git clone https://github.com/mkj/dropbear.git
WORKDIR /build/dropbear
RUN git fetch --tags --force && git checkout ${DROPBEAR_REF}

# Build object files with fuzzing support. Some fuzzer links may fail depending
# on the build environment; the object files are still produced, which is enough
# for the standalone regression PoC.
RUN autoreconf -i && \
    ./configure --enable-fuzz --disable-harden --disable-zlib && \
    (make -j$(nproc) || true)

COPY poc_verify.c /build/poc_verify.c
COPY crash.bin /build/crash.bin

WORKDIR /build
RUN gcc -g -O1 -o poc_verify poc_verify.c \
    -I/build/dropbear \
    -I/build/dropbear/src \
    -I/build/dropbear/libtomcrypt/src/headers \
    -I/build/dropbear/libtommath \
    $(find /build/dropbear -name "*.o" | grep -v -E "fuzzer|svr-main|cli-main|dropbearkey|dropbearconvert|scp-main") \
    -lpthread -ldl -lcrypt

FROM ubuntu:22.04
ARG DROPBEAR_REF=DROPBEAR_2026.91
COPY --from=builder /build/poc_verify /poc_verify
COPY --from=builder /build/crash.bin /crash.bin
COPY run.sh /run.sh
RUN chmod +x /run.sh
ENV DROPBEAR_REF=${DROPBEAR_REF}
CMD ["/run.sh"]
