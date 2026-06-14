FROM ubuntu:24.04

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
      ca-certificates git clang g++ llvm-18 libclang-rt-18-dev \
      make autoconf automake autoconf-archive libtool pkg-config patch \
      libnetcdf-dev && \
    rm -rf /var/lib/apt/lists/*

ENV ASAN_SYMBOLIZER_PATH=/usr/bin/llvm-symbolizer-18
ENV ASAN_OPTIONS=symbolize=1:allocator_may_return_null=0:abort_on_error=1:detect_leaks=0

RUN git clone https://github.com/libMesh/libmesh.git /src/libmesh && \
    cd /src/libmesh && \
    git checkout cc84387bbd96e12d2d12986402ec8bba282fc0c4 && \
    git submodule update --init --recursive

WORKDIR /src/libmesh

RUN ACLOCAL_PATH=/usr/share/aclocal ./bootstrap && \
    ./configure --disable-mpi --enable-static --disable-shared \
      CXX=clang++ CC=clang \
      CXXFLAGS='-fsanitize=address -fno-omit-frame-pointer -g -O1' \
      CFLAGS='-fsanitize=address -fno-omit-frame-pointer -g -O1' \
      LDFLAGS='-fsanitize=address' && \
    make -j"$(nproc)" && \
    make install

COPY poc.cc /src/poc.cc

RUN clang++ -std=c++17 -fsanitize=address -fno-omit-frame-pointer -g -O1 \
    /src/poc.cc $(libmesh-config --cxxflags --libs) -lnetcdf -o /src/poc

CMD ["/src/poc"]
