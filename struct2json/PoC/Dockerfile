FROM ubuntu:24.04

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
      ca-certificates git clang libclang-rt-18-dev && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /src
RUN git clone https://github.com/armink/struct2json.git /src/struct2json && \
    cd /src/struct2json && \
    git checkout 4f1fdc9fe928b94cb2e1f23f37d18b4cd2e35bfa

COPY poc.c /src/poc.c

RUN clang -fsanitize=address -g -O1 \
    -I/src/struct2json/struct2json/inc \
    -I/src/struct2json/cJSON \
    /src/poc.c \
    /src/struct2json/struct2json/src/s2j.c \
    /src/struct2json/cJSON/cJSON.c \
    -o /src/poc

CMD ["/src/poc"]
