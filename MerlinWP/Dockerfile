FROM php:8.2-cli
WORKDIR /poc
COPY . /poc
EXPOSE 8000
CMD ["sh", "-c", "php -S 127.0.0.1:8000 server.php > /tmp/poc-server.log 2>&1 & pid=$!; sleep 0.8; php PoC.php; status=$?; echo '--- server log ---'; cat /tmp/poc-server.log; kill $pid 2>/dev/null || true; exit $status"]
