# PoC: Tencent/Biny CSRF Cookie Deserialization

Run with Docker:

```bash
docker build -t poc-biny-csrf-cookie-deserialization .
docker run --rm poc-biny-csrf-cookie-deserialization
```

Expected vulnerable output contains `wakeup marker: TRIGGERED`.
