FROM ubuntu:22.04 AS builder
# Pinned commits:
#   the-tcpdump-group/libpcap.git: 6b5de1e5f07a4fea6672caa2d34935c3da24a8f2
#   seladb/PcapPlusPlus.git: af83983b079d8225d5cf3d2b4ed3d3a40e194557
#
# Pinned commits:
#   the-tcpdump-group/libpcap.git: 6b5de1e5f07a4fea6672caa2d34935c3da24a8f2
#   seladb/PcapPlusPlus.git: af83983b079d8225d5cf3d2b4ed3d3a40e194557
#
ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    build-essential cmake git autoconf flex bison ca-certificates libpcap-dev

# Build static libpcap
RUN git clone https://github.com/the-tcpdump-group/libpcap.git /src/libpcap && \
    cd /src/libpcap && \
    git fetch --depth 1 origin 6b5de1e5f07a4fea6672caa2d34935c3da24a8f2 && \
    git checkout FETCH_HEAD && \
    git rev-parse HEAD | grep -q "^6b5de1e5f07a4fea6672caa2d34935c3da24a8f2$"
WORKDIR /src/libpcap
RUN ./autogen.sh && ./configure --enable-shared=no && make -j$(nproc)

# Build PcapPlusPlus
RUN git clone https://github.com/seladb/PcapPlusPlus.git /src/PcapPlusPlus && \
    cd /src/PcapPlusPlus && \
    git fetch --depth 1 origin af83983b079d8225d5cf3d2b4ed3d3a40e194557 && \
    git checkout FETCH_HEAD && \
    git rev-parse HEAD | grep -q "^af83983b079d8225d5cf3d2b4ed3d3a40e194557$"
WORKDIR /src/PcapPlusPlus
RUN cmake -S . -B build \
    -DCMAKE_CXX_FLAGS="-fsanitize=address -g -O1" \
    -DCMAKE_C_FLAGS="-fsanitize=address -g -O1" \
    -DCMAKE_EXE_LINKER_FLAGS="-fsanitize=address" \
    -DPCAPPP_BUILD_TESTS=OFF \
    -DPCAPPP_BUILD_EXAMPLES=OFF \
    -DPCAP_INCLUDE_DIR=/src/libpcap/ \
    -DPCAP_LIBRARY=/src/libpcap/libpcap.a && \
    cmake --build build -j$(nproc)

COPY poc_reproducer.cpp /src/
COPY poc_input /src/
RUN g++ -fsanitize=address -g -O1 \
    -I/src/PcapPlusPlus/Packet++/header \
    -I/src/PcapPlusPlus/Common++/header \
    -I/src/PcapPlusPlus/Pcap++/header \
    -o /src/poc_reproducer \
    /src/poc_reproducer.cpp \
    -L/src/PcapPlusPlus/build/Packet++ -lPacket++ \
    -L/src/PcapPlusPlus/build/Common++ -lCommon++ \
    -L/src/PcapPlusPlus/build/Pcap++ -lPcap++ \
    /src/libpcap/libpcap.a -lpthread -ldbus-1 -static-libasan

WORKDIR /src
CMD ["/src/poc_reproducer"]
