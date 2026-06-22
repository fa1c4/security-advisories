FROM ubuntu:24.04
ARG DEBIAN_FRONTEND=noninteractive
ARG HDF5_COMMIT=c478c6e35fae83ddb9ae82b74b2ef30ad353b8a7

RUN apt-get update && apt-get install -y \
    build-essential cmake git ca-certificates zlib1g-dev pkg-config && \
    rm -rf /var/lib/apt/lists/*

RUN git clone https://github.com/HDFGroup/hdf5.git /src/hdf5 && \
    cd /src/hdf5 && \
    git fetch --depth 1 origin ${HDF5_COMMIT} && \
    git checkout FETCH_HEAD && \
    git rev-parse HEAD | grep -q "^${HDF5_COMMIT}$"

WORKDIR /src/hdf5
RUN cmake -S . -B build-dir -G "Unix Makefiles" \
    -DCMAKE_BUILD_TYPE=Release \
    -DBUILD_SHARED_LIBS=OFF \
    -DBUILD_TESTING=OFF \
    -DHDF5_BUILD_EXAMPLES=OFF \
    -DHDF5_BUILD_TOOLS=OFF \
    -DHDF5_ENABLE_Z_LIB_SUPPORT=ON \
    -DHDF5_ENABLE_SANITIZERS=ON \
    -DCMAKE_C_FLAGS="-fsanitize=address -fno-omit-frame-pointer -g -O1" \
    -DCMAKE_CXX_FLAGS="-fsanitize=address -fno-omit-frame-pointer -g -O1" && \
    cmake --build build-dir --config Release -j$(nproc)

COPY poc.c /src/poc.c
COPY crash.bin /src/crash.bin
RUN gcc -fsanitize=address -fno-omit-frame-pointer -g -O1 -std=c99 \
    -I/src/hdf5/src \
    -I/src/hdf5/build-dir/src \
    -I/src/hdf5/src/H5FDsubfiling \
    /src/poc.c /src/hdf5/build-dir/bin/libhdf5.a \
    -lz -ldl -lm -lpthread -o /src/poc

ENV ASAN_OPTIONS=abort_on_error=1:symbolize=0:detect_leaks=0
CMD ["/src/poc", "/src/crash.bin"]
