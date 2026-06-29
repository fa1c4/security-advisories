# PoC: doubleleft/hook OAuth `opauth` insecure deserialization

This standalone PoC reproduces the vulnerable callback behavior from
`doubleleft/hook`:

```php
$opauth = unserialize(base64_decode($_POST['opauth']));
```

The sink is in `src/Controllers/OAuthController.php::auth()` and the source route
is `POST /oauth/callback` in `src/Application/Routes.php`.

## Build and run

```bash
docker build -t poc-hook-opauth-deserialization .
docker run --rm poc-hook-opauth-deserialization
```

Expected vulnerable output includes:

```text
[VULNERABLE] Attacker-controlled opauth POST data reached PHP unserialize() and executed object magic method.
```

## What the PoC does

1. Starts a local PHP HTTP endpoint at `/oauth/callback` containing the same
   vulnerable deserialization statement used by Hook.
2. Sends an `application/x-www-form-urlencoded` POST request with attacker
   controlled `opauth=<base64(serialized object)>`.
3. The object's `__wakeup()` writes a marker file before the controller can
   validate the decoded structure.

The demonstration uses a harmless local marker-file gadget. In a deployed Hook
instance, impact depends on classes available through application/autoloaded
packages and can range from object injection side effects to code execution if a
suitable gadget chain exists.

## Preconditions

- The attacker can send POST requests to `POST /oauth/callback`.
- The attacker knows or can obtain a valid Hook app id/key. Hook exposes browser
  and device keys for front-end/client use, so these are application credentials,
  not user authentication secrets.
- A useful PHP gadget class or application side effect is available for impact
  beyond proof of object magic-method execution.
