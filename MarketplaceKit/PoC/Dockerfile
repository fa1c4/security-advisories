FROM php:8.2-cli-alpine

ARG MARKETPLACEKIT_COMMIT=534aa0eb9981a42115bb139f79ae5433b4483a05

RUN apk add --no-cache git bash sqlite

WORKDIR /opt
RUN git clone https://github.com/marketplacekit/marketplacekit.git /opt/marketplacekit \
    && cd /opt/marketplacekit \
    && git checkout "$MARKETPLACEKIT_COMMIT"

COPY poc/reproduce-local.sh /opt/poc/reproduce-local.sh
COPY poc/verify-source.php /opt/poc/verify-source.php
COPY poc/reproduce-logic.php /opt/poc/reproduce-logic.php
COPY poc/csrf.html /opt/poc/csrf.html
COPY scripts/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /opt/poc/reproduce-local.sh /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /opt/evidence

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
