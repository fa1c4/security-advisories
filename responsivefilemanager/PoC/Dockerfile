FROM php:8.1-cli

ARG RFM_REPO=https://github.com/trippo/ResponsiveFilemanager.git
ARG RFM_COMMIT=51eddae5190cfc4408ade40575ee63404fead0b9

RUN apt-get update \
    && apt-get install -y --no-install-recommends git curl jq ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt

RUN git clone "$RFM_REPO" /opt/ResponsiveFilemanager \
    && cd /opt/ResponsiveFilemanager \
    && git checkout "$RFM_COMMIT" \
    && mkdir -p source thumbs \
    && chmod -R 0777 source thumbs

COPY poc/reproduce-local.sh /opt/poc/reproduce-local.sh
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /opt/poc/reproduce-local.sh /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /opt/evidence

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
