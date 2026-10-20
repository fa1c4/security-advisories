CC ?= cc
CFLAGS ?= -std=c11 -O0 -g -Wall -Wextra
SOURCE := ../../third_party/hash-25/source/TaiChi/Implementations/Reference_Implementation/TaiChi-1024

.PHONY: all clean
all: taichi1024_leak_harness

taichi1024_leak_harness: taichi1024_leak_harness.c $(SOURCE)/CryptHash_AlgorithmInstance.c
	$(CC) $(CFLAGS) -I$(SOURCE) -Wl,--wrap=malloc -Wl,--wrap=free $^ -o $@

clean:
	rm -f taichi1024_leak_harness
