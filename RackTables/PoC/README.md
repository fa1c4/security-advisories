# RackTables CSRF local Docker lab

This local lab reproduces missing CSRF-token validation in RackTables state-changing AJAX operations.
It is designed for localhost-only testing.

## Build

```bash
docker build -t racktables-csrf-poc .
```

## Run

```bash
docker run --rm -p 127.0.0.1:8080:8080 racktables-csrf-poc
```

The container will:

1. clone `RackTables/racktables`;
2. checkout commit `f14f64e65ac8d806b1f64a981acb3c6870eeae37`;
3. start MariaDB and Apache;
4. initialize RackTables through its normal web installer steps 5 and 6;
5. enable Apache Basic Auth for the local lab (`admin:admin`);
6. create a fixture object and an unlinked port;
7. POST to `index.php?module=ajax&ac=upd-reservation-port` without any CSRF token;
8. verify that the `Port.reservation_comment` value changed.

Expected success marker:

```text
[+] CSRF condition reproduced: the state-changing AJAX operation accepted a request with no CSRF token.
```

## Manual browser proof

After the container starts, open RackTables first and authenticate with `admin:admin`:

```text
http://127.0.0.1:8080/index.php
```

A generated HTML proof is available inside the container at:

```text
/opt/poc/csrf-generated.html
```

## Notes

- This lab uses HTTP Basic Auth to model an already-authenticated victim.
- The proof does not bypass authentication or authorization.
- The demonstrated issue is that a state-changing request is accepted without an anti-CSRF token once the victim is authenticated and authorized.
- Do not expose the container port to a public interface.
