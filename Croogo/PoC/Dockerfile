FROM php:8.2-cli-alpine

ARG CROOGO_REPO=https://github.com/croogo/croogo.git
ARG CROOGO_COMMIT=fc0648659dcb5790ba8a7429250dae492ca724ec

RUN apk add --no-cache bash git curl

WORKDIR /opt

RUN git clone "$CROOGO_REPO" /opt/croogo \
    && cd /opt/croogo \
    && git checkout "$CROOGO_COMMIT"

COPY poc/verify-source.php /opt/poc/verify-source.php
COPY poc/reproduce-logic.php /opt/poc/reproduce-logic.php
COPY poc/reproduce-local.sh /opt/poc/reproduce-local.sh
COPY scripts/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /opt/poc/reproduce-local.sh /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /opt/evidence

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
