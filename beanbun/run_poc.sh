#!/bin/sh
set +e
php PoC.php > /tmp/beanbun_poc.log 2>&1
status=$?
cat /tmp/beanbun_poc.log
if [ "$status" -ne 0 ] && grep -q "Cannot use object of type stdClass as array" /tmp/beanbun_poc.log; then
  echo "[+] Reproduced: unsafe unserialize allows a remote serialized object to crash the worker (exit $status)."
  exit 0
fi
echo "[-] Not reproduced. php exit status=$status"
exit 1
