FROM gcr.io/oss-fuzz-base/base-builder
# Pinned commit: git.ghostscript.com/jbig2dec.git
#   71f2b02f821a4664d0777fcf8845f56614cedc89
#
# Pinned commits:
#   git.ghostscript.com/jbig2dec.git: 71f2b02f821a4664d0777fcf8845f56614cedc89
#
RUN apt-get update && apt-get install -y make libtool pkg-config vim libreadline-dev wget autoconf
RUN git clone git://git.ghostscript.com/jbig2dec.git jbig2dec && \
    cd jbig2dec && git checkout 71f2b02f821a4664d0777fcf8845f56614cedc89
COPY build.sh $SRC/
COPY jbig2_fuzzer.cc $SRC/
WORKDIR $SRC/jbig2dec

COPY timeout-913f58bc349328d7 /tmp/
COPY timeout-c4f782501f1eabe3 /tmp/
COPY timeout-9ca16597ba597ba8 /tmp/

RUN cd ${SRC}/jbig2dec && ./autogen.sh && \
    make -j$(nproc) && \
    $CXX $CXXFLAGS -std=c++11 -I$SRC/jbig2dec -fno-inline-functions \
        $SRC/jbig2_fuzzer.cc -o /out/jbig2_fuzzer \
        -fsanitize=fuzzer ${SRC}/jbig2dec/.libs/libjbig2dec.a

CMD ["bash", "-c", "for f in /tmp/timeout-*; do echo Testing: $f; timeout 10 /out/jbig2_fuzzer $f; echo exit: $?; done"]
