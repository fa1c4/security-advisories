# SilverStripe CMS CAND-98c9146b58cd standalone PoC

This is a dependency-free local reproducer for the object-level authorization bug in `CMSMain::updatetreenodes()`.

## Build and run

```bash
docker build -t poc-silverstripe-idor .
docker run --rm poc-silverstripe-idor
```

Expected result: the PoC logs a `PASS` showing that a limited CMS user can request `ids=42` and receive a tree-node HTML fragment for a page they cannot view. It then calls a patched control endpoint that performs `canView()` and confirms the same title is not leaked.

## Source root cause reproduced

Original source location reviewed:

- `code/Controllers/CMSMain.php::updatetreenodes()` reads `ids` from the request, calls `getRecord($id)`, renders tree-node HTML, and returns `ParentID`, `NextID`, `PrevID`.
- `code/Controllers/CMSMain.php::getRecord()` fetches by object ID and does not perform page-level `canView()`.

This PoC keeps the same security boundary: the simulated user has section-level CMS access but lacks page-level view permission for the confidential page.
