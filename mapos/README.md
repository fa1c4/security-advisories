# MAPOS OS product deletion authorization bypass PoC

This PoC reproduces a missing function-level authorization check in `application/controllers/Os.php::excluirProduto()`.
The application-level controller constructor requires a logged-in session, but the vulnerable method does not call `Permission::checkPermission()` before deleting a product row from a service order.

## Build

```bash
docker build -t poc-mapos-os-product-delete-authz-bypass .
```

## Run

```bash
docker run --rm poc-mapos-os-product-delete-authz-bypass
```

## Expected vulnerable output

```text
low-privileged authenticated user: yes
permission checker called: no
product row exists after request: no
[VULNERABLE] Authenticated low-privilege user deleted an OS product row without function-level authorization.
```
