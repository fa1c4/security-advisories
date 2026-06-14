FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV COMMIT=40c412daccf08164493da0de990eb99a8948116b

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates git build-essential pkg-config clang make python3 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /work
RUN git clone --depth 1 https://github.com/nyanmisaka/ffmpeg-rockchip.git ffmpeg-rockchip \
    && cd ffmpeg-rockchip \
    && git fetch --depth 1 origin ${COMMIT} \
    && git checkout ${COMMIT}

WORKDIR /work/ffmpeg-rockchip
RUN ./configure \
    --cc=clang \
    --cxx=clang++ \
    --disable-programs \
    --disable-doc \
    --disable-network \
    --disable-autodetect \
    --disable-x86asm \
    --disable-everything \
    --enable-demuxer=mov \
    --enable-protocol=file \
    --extra-cflags='-g -O1 -fno-omit-frame-pointer' \
    --extra-cxxflags='-g -O1 -fno-omit-frame-pointer' \
    && make -j"$(nproc)" libavformat/libavformat.a libavcodec/libavcodec.a libavutil/libavutil.a

COPY poc.cc /work/poc.cc
COPY oom-f528a3c32455549e702ad8b2c9f843770d63253b /work/oom-f528a3c32455549e702ad8b2c9f843770d63253b

RUN clang++ -std=c++17 -g -O1 -fno-omit-frame-pointer \
    -DFFMPEG_DEMUXER=mov \
    -I/work/ffmpeg-rockchip \
    -I/work/ffmpeg-rockchip/libavutil \
    -I/work/ffmpeg-rockchip/libavformat \
    -I/work/ffmpeg-rockchip/libavcodec \
    /work/poc.cc \
    /work/ffmpeg-rockchip/libavformat/libavformat.a \
    /work/ffmpeg-rockchip/libavcodec/libavcodec.a \
    /work/ffmpeg-rockchip/libavutil/libavutil.a \
    -lm -pthread -ldl -o /work/poc

# Run with a memory limit to demonstrate the availability impact safely:
# docker run --rm --memory=256m --memory-swap=256m poc-ffmpeg-rockchip-mov-oom
CMD ["/work/poc"]
