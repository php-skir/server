# Wire protocol

Endpoints registered with `Route::skirRpc()` speak the standard SkirRPC protocol, so any Skir client can call them: the `php-skir/client` package, or clients generated for TypeScript, Python, Go and the other languages Skir supports.

## Requests

A call is a `POST` whose body is either a JSON object or a colon-separated string. A `GET` carries the same string in its query string.

### JSON object

```bash
curl -X POST -H 'Content-Type: application/json' \
  -d '{"method": "Square", "request": 5.0}' \
  https://example.test/api/skir
```

`method` is the method name or its number. `request` may be dense or readable JSON. The response is **readable** JSON, which makes this form handy for debugging with curl.

### Colon-separated

```text
Square:1001::5
Square:1001:readable:5
```

This is the form upstream Skir clients send: `name:number:format:request`. When the number is present it identifies the method and the name is informational; when it is empty, the method is looked up by name. The response is **dense** JSON unless the format is `readable`.

A `GET` request uses the same string as its query string, for example `/api/skir?Square:1001::5`.

## Responses

A successful call returns `200` with `Content-Type: application/json` and the encoded response value:

```json
25
```

Failures return a JSON error body with a Skir error code:

| Status | Code | When |
| --- | --- | --- |
| 422 | `skir_invalid_request` | The body is not a valid SkirRPC request, the method name is ambiguous, the request value does not match the method's request type, or it cannot be passed to the controller's request parameter. |
| 404 | `skir_method_not_found` | No method with that name or number is registered on the endpoint. |
| 422 | `skir_validation_failed` | A Form Request or a validating generated DTO (Laravel Data, Simple Data Objects) rejected the decoded input. |
| 403 | `skir_authorization_failed` | A Form Request's `authorize()` returned `false`. |
| 500 | `skir_invalid_response` | The method returned a value that cannot be serialized as its response type. |

## Studio

A `GET` without a query string, or with the configured Studio query key (`?studio` by default), renders Studio when it is enabled. See [Studio](routing.md#studio).
