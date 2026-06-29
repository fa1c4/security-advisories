# WebStack Unauthenticated AJAX Media Actions PoC

Build and run locally:

```bash
docker build -t poc-webstack-ajax-media-unauth .
docker run --rm poc-webstack-ajax-media-unauth
```

Expected output includes both markers:

```text
[VULNERABLE] Unauthenticated nopriv img_remove deleted attacker-selected attachment id 4242 without nonce/auth check.
[VULNERABLE] Unauthenticated nopriv img_upload accepted and stored a .php payload path: ...
```
