#!/usr/bin/env bash
set -eu

IMAGE="${IMAGE:-jbig2dec-timeout-poc}"

docker build -t "$IMAGE" .

for f in timeout-913f58bc349328d7 timeout-9ca16597ba597ba8 timeout-c4f782501f1eabe3; do
  echo "== fuzzer: $f =="
  docker run --rm "$IMAGE" bash -c "timeout 30 /out/jbig2_fuzzer /tmp/$f; echo exit=\$?"

  echo "== cli: $f =="
  docker run --rm "$IMAGE" bash -c "timeout --preserve-status 30 /src/jbig2dec/jbig2dec -q -o /tmp/out.pbm /tmp/$f; echo exit=\$?"
done
