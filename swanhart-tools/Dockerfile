FROM php:8.3-cli
WORKDIR /poc
COPY vulnerable_app/ ./vulnerable_app/
COPY PoC.php ./PoC.php
COPY README.md ./README.md
CMD ["php", "PoC.php"]
