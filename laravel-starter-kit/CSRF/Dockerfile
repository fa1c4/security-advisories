FROM php:8.3-cli
WORKDIR /poc
COPY vulnerable_app/ ./vulnerable_app/
COPY PoC.php ./PoC.php
CMD ["php", "PoC.php"]
