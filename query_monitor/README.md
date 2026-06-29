# PoC: Query Monitor QM_COOKIE Missing HttpOnly

Run with Docker:

```bash
docker build -t poc-query-monitor-cookie-flags .
docker run --rm poc-query-monitor-cookie-flags
```

Expected vulnerable output contains a `Set-Cookie` header for `wp-query_monitor_demo_hash` without `HttpOnly` and without `SameSite`.
