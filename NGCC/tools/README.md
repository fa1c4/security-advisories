# Reproduction tools

`reproduce.sh` is the PQCFuzz dispatcher for the 15 sibling `<target-bug>/`
directories. It follows the upstream runner's per-witness status handling and
allows a candidate or bug ID as a filter. Run `./reproduce.sh --list` to inspect
the selection, and see `../README.md` for source checkout setup.

`ngcc_attack.c` and `Makefile` are exact copies from the pinned ngcc-harness
commit recorded in `../UPSTREAM_SNAPSHOT.md`. The upstream source needs the
four exact `../api/link_*.h` headers and builds with `make -C tools` from this
workspace root. The 15 new bugs require focused witnesses absent from the
upstream `ngcc_attack` command table, so building this tool is optional for
their aggregate run.

`upstream/reproduce.sh` and `upstream/README.md` are unchanged references.
They describe the upstream repository's published findings and cannot run
these 15 candidate findings from this standalone workspace.
