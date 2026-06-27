# swanhart-tools CAND-ec6432b667a9 standalone PoC

This local-only PoC reproduces the Shard-Query `awsconfig/configure2.php` issue in `greenlion/swanhart-tools`.

InvAudit labelled the finding as CSRF. The source-level issue is stronger: `configure2.php` is a directly reachable setup/configuration endpoint that performs database and file-system state changes from `$_REQUEST` without authentication, an install/setup secret, a setup lock, CSRF validation, or request-method restriction.

## Run

```bash
docker build -t poc-swanhart-tools-configure2 .
docker run --rm poc-swanhart-tools-configure2
```

## What it demonstrates

A forged unauthenticated GET request with `force=1` is enough to trigger simulated equivalents of:

- `grant all on *.* ... with grant option`
- `drop database if exists <schema>`
- `create database <schema>`
- writing `shard-query/include/config.inc` with attacker-controlled host/user/password/schema settings

The PoC does not connect to MySQL and does not write outside its local simulation. It records the operations that the vulnerable source path would issue.
