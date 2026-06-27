# Symphony CMS CAND-3ce301b5e576 standalone PoC

This local-only PoC reproduces an authenticated CSRF / unsafe GET state-changing action in the Symphony CMS publish table sorting flow.

## Run

```bash
docker build -t poc-symphonycms-csrf-sort .
docker run --rm poc-symphonycms-csrf-sort
```

## What it demonstrates

The vulnerable model mirrors these source-code facts:

- `XSRF::validateRequest()` validates only requests with `$_POST` data.
- `Sortable::initialize()` reads `sort` and `order` from `$_REQUEST`, which includes GET query parameters.
- `contentPublish::sort()` persists changed sorting settings via `Section::setSortingField()` and `Section::setSortingOrder()`.

A forged GET request such as:

```text
GET /symphony/publish/articles/?sort=title&order=asc
```

has no `xsrf` token, skips XSRF validation because it is not a POST, and still changes the persisted sorting configuration.

The PoC also includes controls showing that POST without `xsrf` is rejected and that a patched flow should reject GET state changes while allowing POST with a valid token.
