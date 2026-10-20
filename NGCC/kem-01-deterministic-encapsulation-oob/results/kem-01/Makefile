.PHONY: all clean FORCE
all: aigis_harness_I aigis_harness_II aigis_harness_III

aigis_harness_%: aigis_harness.c build_aigis_harness.py FORCE
	python3 build_aigis_harness.py $*

FORCE:

clean:
	rm -f aigis_harness_I aigis_harness_II aigis_harness_III
