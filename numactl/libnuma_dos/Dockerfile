FROM ubuntu:22.04 AS builder
# Pinned commit: numactl/numactl.git
#   da84fd9e18a50e3933c7cd40c22a72361692b4e8
#
# Pinned commits:
#   numactl/numactl.git: da84fd9e18a50e3933c7cd40c22a72361692b4e8
#
ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    build-essential git autoconf automake libtool ca-certificates

RUN git clone https://github.com/numactl/numactl.git /src/numactl && \
    cd /src/numactl && \
    git fetch --depth 1 origin da84fd9e18a50e3933c7cd40c22a72361692b4e8 && \
    git checkout FETCH_HEAD && \
    git rev-parse HEAD | grep -q "^da84fd9e18a50e3933c7cd40c22a72361692b4e8$"
WORKDIR /src/numactl
RUN ./autogen.sh && \
    ./configure && \
    make -j$(nproc)

COPY poc_reproducer.c /src/
COPY poc_input /src/
RUN gcc -g -O1 \
    -I/src/numactl \
    -o /src/poc_reproducer \
    /src/poc_reproducer.c \
    /src/numactl/.libs/libnuma.a \
    -lpthread

WORKDIR /src
CMD ["timeout", "10", "/src/poc_reproducer"]
