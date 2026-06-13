#!/usr/bin/env bash
set -euo pipefail

echo "[*] Step 1: verifying vulnerable source patterns..."
php /opt/poc/verify-source.php

echo
echo "[*] Step 2: reproducing the vulnerable write logic in an isolated local lab..."
php /opt/poc/reproduce-logic.php
