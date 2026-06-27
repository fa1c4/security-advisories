# laravel-starter-kit CAND-657ddceac24d standalone PoC

This is a dependency-free local reproducer for the admin CSRF bug caused by `AdminController::__construct()` not calling `parent::__construct()`.

## Build and run

```bash
docker build -t poc-laravel-starter-kit-csrf .
docker run --rm poc-laravel-starter-kit-csrf
```

Expected result: the PoC sends a forged `POST /admin/groups/1/edit` as an already-authenticated admin session but without `_token`. The vulnerable controller registers only `admin-auth`, so the group name and permissions are modified. The PoC then sends the same request to a patched control controller that calls `parent::__construct()` and verifies that the request is rejected with HTTP 419.

## Source root cause reproduced

Original source locations reviewed:

- `app/controllers/BaseController.php:17-23` registers `beforeFilter('csrf', array('on' => 'post'))`.
- `app/controllers/AuthorizedController.php:17-24` calls `parent::__construct()`.
- `app/controllers/AdminController.php:10-14` overrides the constructor but does **not** call `parent::__construct()`.
- `app/routes.php:40-46` maps `POST admin/groups/{groupId}/edit` to `GroupsController@postEdit`.
- `app/controllers/admin/GroupsController.php:143-184` updates the group name and permissions.

The missing parent constructor is the single root cause. Individual admin POST endpoints are symptoms of that same source-code bug.
