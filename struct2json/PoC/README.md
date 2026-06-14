# struct2json NULL valuestring PoC

## Build and run

```sh
docker build -t poc-struct2json-null-valuestring .
docker run --rm poc-struct2json-null-valuestring
```

## Expected vulnerable behavior

The vulnerable commit should crash under AddressSanitizer with a NULL pointer dereference in/near `strncpy`, because `S2J_STRUCT_GET_string_ELEMENT` copies `json_temp->valuestring` without checking that the cJSON node is actually a string.

## Affected commit

`armink/struct2json@4f1fdc9fe928b94cb2e1f23f37d18b4cd2e35bfa`

## Trigger input

```json
{"nAMe":2}
```
