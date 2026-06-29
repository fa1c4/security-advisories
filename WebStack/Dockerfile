FROM php:8.3-cli
WORKDIR /poc
COPY PoC.php /poc/PoC.php
CMD ["sh", "-c", "php /poc/PoC.php delete && php /poc/PoC.php upload"]
