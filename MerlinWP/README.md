# V-0803 MerlinWP child-theme AJAX CSRF/AuthZ PoC

Build and run:

```bash
docker build -t poc-0803 .
docker run --rm poc-0803
```

The harness models WordPress `wp_ajax_merlin_child_theme`: a logged-in cookie is required, but `generate_child()` does not validate `wpnonce` or capabilities. `PoC.php` sends `action=merlin_child_theme` with no nonce and no capability marker. Success is proven by generated child-theme files and changed options.
