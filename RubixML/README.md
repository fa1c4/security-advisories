# RubixML Native Serializer Insecure Deserialization PoC

Build and run locally:

```bash
docker build -t poc-rubixml-native-deserialization .
docker run --rm poc-rubixml-native-deserialization
```

Expected output includes:

```text
[VULNERABLE] Native::deserialize executed attacker-controlled object code before rejecting the object.
```
