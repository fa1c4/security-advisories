FROM ubuntu:22.04
ARG DEBIAN_FRONTEND=noninteractive
ARG BLUEZ_COMMIT=83ddf46ccc0653fb2aa460b57eb60db92ab37597

RUN apt-get update && apt-get install -y \
    build-essential git autoconf automake libtool pkg-config python3 \
    libglib2.0-dev libdbus-1-dev libreadline-dev libudev-dev libical-dev \
    ca-certificates && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /build
RUN git clone https://github.com/bluez/bluez.git bluez && \
    cd bluez && \
    git fetch --depth 1 origin ${BLUEZ_COMMIT} && \
    git checkout FETCH_HEAD && \
    git rev-parse HEAD | grep -q "^${BLUEZ_COMMIT}$"

WORKDIR /build/bluez
RUN ./bootstrap && \
    CFLAGS="-fsanitize=address -fno-omit-frame-pointer -g -O1" \
    LDFLAGS="-fsanitize=address" \
    ./configure --disable-systemd --disable-manpages --disable-udev \
                --disable-cups --disable-client \
                --disable-a2dp --disable-avrcp --disable-network \
                --disable-hid --disable-hog && \
    make -j$(nproc)

COPY poc.c /build/poc.c
COPY crash.bin /build/crash.bin

WORKDIR /build
RUN GOBEX_OBJS="$(find /build/bluez/gobex -name '*.o' -print | sort | tr '\n' ' ')" && \
    test -n "$GOBEX_OBJS" && \
    gcc -fsanitize=address -fno-omit-frame-pointer -g -O1 -o poc poc.c \
      -I/build/bluez \
      -I/build/bluez/src \
      -I/build/bluez/lib \
      -I/build/bluez/gobex \
      $(pkg-config --cflags glib-2.0) \
      $GOBEX_OBJS \
      /build/bluez/src/.libs/libshared-glib.a \
      /build/bluez/lib/.libs/libbluetooth-internal.a \
      $(pkg-config --libs glib-2.0) \
      -lical -ldbus-1 -lpthread -ldl

ENV ASAN_OPTIONS=abort_on_error=1:symbolize=0:detect_leaks=0
CMD ["/build/poc", "/build/crash.bin"]
