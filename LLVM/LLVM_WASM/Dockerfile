FROM ubuntu:noble
ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends     g++ llvm-18 llvm-18-dev ca-certificates     && rm -rf /var/lib/apt/lists/*

COPY api_poc.cpp crash.bin /build/
WORKDIR /build

RUN g++ $(llvm-config-18 --cxxflags) -g -o api_poc api_poc.cpp     $(llvm-config-18 --ldflags) -lLLVM-18

CMD ["/bin/sh", "-c", "set +e;   echo '[*] Running llvm-dwarfdump on crafted WASM object...';   timeout 10 /usr/lib/llvm-18/bin/llvm-dwarfdump /build/crash.bin;   echo llvm-dwarfdump_exit:$?;   echo '[*] Running llvm-objdump --file-headers on crafted WASM object...';   timeout 10 /usr/lib/llvm-18/bin/llvm-objdump --file-headers /build/crash.bin;   echo llvm-objdump_exit:$?;   echo '[*] Running API-level ObjectFile/DWARFContext PoC...';   timeout 10 /build/api_poc /build/crash.bin;   echo api_poc_exit:$?" ]
