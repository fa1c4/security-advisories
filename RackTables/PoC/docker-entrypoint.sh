#!/usr/bin/env bash
set -euo pipefail

export RACKTABLES_DB_NAME="${RACKTABLES_DB_NAME:-racktables_lab}"
export RACKTABLES_DB_USER="${RACKTABLES_DB_USER:-racktables}"
export RACKTABLES_DB_PASS="${RACKTABLES_DB_PASS:-racktables}"
export RACKTABLES_ADMIN_PASSWORD="${RACKTABLES_ADMIN_PASSWORD:-admin}"
export LAB_PORT="${LAB_PORT:-8080}"

log() { printf '[*] %s\n' "$*"; }
fail() { printf '[!] %s\n' "$*" >&2; exit 1; }

log "Starting MariaDB..."
service mariadb start >/dev/null
for i in $(seq 1 30); do
  if mysqladmin ping --silent; then break; fi
  sleep 1
done
mysqladmin ping --silent

log "Creating RackTables database and user..."
mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${RACKTABLES_DB_NAME}\` CHARACTER SET utf8 COLLATE utf8_unicode_ci;
CREATE USER IF NOT EXISTS '${RACKTABLES_DB_USER}'@'localhost' IDENTIFIED BY '${RACKTABLES_DB_PASS}';
GRANT ALL PRIVILEGES ON \`${RACKTABLES_DB_NAME}\`.* TO '${RACKTABLES_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

log "Writing RackTables secret.php for local lab..."
cat > /opt/racktables/wwwroot/inc/secret.php <<PHP
<?php
\$pdo_dsn = 'mysql:host=localhost;dbname=${RACKTABLES_DB_NAME}';
\$db_username = '${RACKTABLES_DB_USER}';
\$db_password = '${RACKTABLES_DB_PASS}';

// Use Apache Basic Auth in this lab. The browser/curl is an authenticated victim.
\$user_auth_src = 'httpd';
\$require_local_account = TRUE;
PHP
chown root:www-data /opt/racktables/wwwroot/inc/secret.php
chmod 440 /opt/racktables/wwwroot/inc/secret.php

log "Starting Apache on 0.0.0.0:${LAB_PORT}..."
apache2ctl -k start
for i in $(seq 1 30); do
  if curl -fsS -u admin:admin "http://127.0.0.1:${LAB_PORT}/index.php?module=installer" >/dev/null; then break; fi
  sleep 1
done
curl -fsS -u admin:admin "http://127.0.0.1:${LAB_PORT}/index.php?module=installer" >/dev/null || fail "Apache/RackTables installer did not become ready"

log "Initializing RackTables database through the RackTables web installer, step 5..."
curl -fsS -u admin:admin "http://127.0.0.1:${LAB_PORT}/index.php?module=installer&step=5" -o /opt/evidence/installer-step5.html
TABLE_COUNT="$(mysql -N -B -u"${RACKTABLES_DB_USER}" -p"${RACKTABLES_DB_PASS}" "${RACKTABLES_DB_NAME}" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${RACKTABLES_DB_NAME}'")"
printf '[*] RackTables DB table count after installer step 5: %s\n' "$TABLE_COUNT" | tee /opt/evidence/setup-db.log
if [[ "$TABLE_COUNT" -lt 50 ]]; then
  tail -n 80 /opt/evidence/installer-step5.html >&2 || true
  fail "RackTables installer step 5 did not create the expected tables"
fi

log "Setting RackTables admin password through installer step 6..."
curl -fsS -u admin:admin \
  -X POST \
  --data-urlencode "password=${RACKTABLES_ADMIN_PASSWORD}" \
  "http://127.0.0.1:${LAB_PORT}/index.php?module=installer&step=6" \
  -o /opt/evidence/installer-step6.html
ADMIN_COUNT="$(mysql -N -B -u"${RACKTABLES_DB_USER}" -p"${RACKTABLES_DB_PASS}" "${RACKTABLES_DB_NAME}" -e "SELECT COUNT(*) FROM UserAccount WHERE user_id=1 AND user_name='admin'")"
printf '[*] RackTables admin row count after installer step 6: %s\n' "$ADMIN_COUNT" | tee -a /opt/evidence/setup-db.log
if [[ "$ADMIN_COUNT" -ne 1 ]]; then
  tail -n 80 /opt/evidence/installer-step6.html >&2 || true
  fail "RackTables installer step 6 did not create the admin account"
fi

log "Creating local fixture object and unlinked port..."
php /opt/scripts/create-fixture.php | tee /opt/evidence/fixture.log

log "Checking authenticated RackTables page..."
curl -fsS -u admin:admin "http://127.0.0.1:${LAB_PORT}/index.php" >/opt/evidence/index-after-install.html

log "Running authenticated CSRF reproduction..."
/opt/poc/reproduce-local.sh "http://127.0.0.1:${LAB_PORT}" | tee /opt/evidence/reproduction.log

log "Lab is running. Host URL: http://127.0.0.1:${LAB_PORT}/index.php"
log "HTTP Basic Auth: admin / admin"
log "Generated browser PoC HTML inside container: /opt/poc/csrf-generated.html"
log "Evidence logs: /opt/evidence"
log "Stop with Ctrl+C or docker stop <container>."

tail -F /var/log/apache2/racktables-access.log /var/log/apache2/racktables-error.log
