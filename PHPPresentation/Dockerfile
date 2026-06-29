FROM php:8.3-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev zlib1g-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /poc
COPY Serialized.php /poc/Serialized.php
COPY PoC.php /poc/PoC.php
CMD ["php", "/poc/PoC.php"]
