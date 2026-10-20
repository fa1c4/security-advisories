ROOT := $(abspath ../..)
SRC := $(ROOT)/third_party/hash-14/source/Laurus/Implementations/Reference_Implementation/Laurus-XOF
CC ?= gcc
CFLAGS := -std=c11 -O1 -g -Wall -Wextra -fno-omit-frame-pointer -fsanitize=address

.PHONY: all
all: build/poc

build/poc: harness.c $(SRC)/CryptHash_AlgorithmInstance.c $(SRC)/laurus_xof.c
	mkdir -p build
	$(CC) $(CFLAGS) -I$(SRC) harness.c $(SRC)/CryptHash_AlgorithmInstance.c $(SRC)/laurus_xof.c -o $@
