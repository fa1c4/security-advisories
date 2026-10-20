ROOT := $(abspath ../..)
SRC := $(ROOT)/third_party/hash-15/source/Litchi/Implementations/Reference_Implementation/Litchi-XOF
CC ?= gcc
CFLAGS := -std=c11 -O1 -g -Wall -Wextra -fno-omit-frame-pointer -fsanitize=address

.PHONY: all clean
all: build/poc

build/poc: harness.c input.txt $(SRC)/CryptHash_AlgorithmInstance.c $(SRC)/litchi_xof.c
	mkdir -p build
	$(CC) $(CFLAGS) -I$(SRC) harness.c $(SRC)/CryptHash_AlgorithmInstance.c $(SRC)/litchi_xof.c -o $@

clean:
	rm -rf build
