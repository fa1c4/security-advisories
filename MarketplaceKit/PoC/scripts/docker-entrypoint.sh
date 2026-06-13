#!/usr/bin/env bash
set -euo pipefail

log() { printf '[*] %s\n' "$*"; }

log "Verifying MarketplaceKit source at the affected commit..."
php /opt/poc/verify-source.php | tee /opt/evidence/source-verification.log

log "Running local IDOR/GET-CSRF logic reproduction..."
php /opt/poc/reproduce-logic.php | tee /opt/evidence/reproduction.log

log "Evidence logs saved in /opt/evidence"
log "PoC HTML template: /opt/poc/csrf.html"
