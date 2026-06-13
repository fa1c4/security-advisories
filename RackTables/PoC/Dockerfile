FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ARG RACKTABLES_COMMIT=f14f64e65ac8d806b1f64a981acb3c6870eeae37

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       ca-certificates git curl jq \
       apache2 apache2-utils libapache2-mod-php \
       php-cli php-gd php-mysql php-mbstring php-bcmath php-snmp \
       mariadb-server mariadb-client \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt
RUN git clone https://github.com/RackTables/racktables.git /opt/racktables \
    && cd /opt/racktables \
    && git checkout "$RACKTABLES_COMMIT"

COPY scripts/setup-db.php /opt/scripts/setup-db.php
COPY scripts/create-fixture.php /opt/scripts/create-fixture.php
COPY poc/reproduce-local.sh /opt/poc/reproduce-local.sh
COPY poc/csrf.html /opt/poc/csrf.html
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /opt/poc/reproduce-local.sh /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /opt/evidence \
    && htpasswd -bc /etc/apache2/racktables.htpasswd admin admin \
    && printf 'ServerName 127.0.0.1\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername >/dev/null \
    && printf 'Listen 8080\n' > /etc/apache2/ports.conf \
    && cat > /etc/apache2/sites-available/000-default.conf <<'APACHECONF'
<VirtualHost *:8080>
    DocumentRoot /opt/racktables/wwwroot

    <Directory /opt/racktables/wwwroot>
        Options FollowSymLinks
        AllowOverride None
        DirectoryIndex index.php
        AuthType Basic
        AuthName "RackTables CSRF Lab"
        AuthUserFile /etc/apache2/racktables.htpasswd
        Require valid-user
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/racktables-error.log
    CustomLog ${APACHE_LOG_DIR}/racktables-access.log combined
</VirtualHost>
APACHECONF

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
