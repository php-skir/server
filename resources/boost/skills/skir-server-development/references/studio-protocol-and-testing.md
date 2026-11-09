# Studio, Protocol, and Testing

## Studio exposure

Studio is disabled by default. Enable it per route with `studio()` or override a global default with
`studio(false)`. Package configuration uses `studio_enabled` and `studio_query_key`; the query key
only selects the Studio response and is never access control.

```php
Route::skirRpc('/api/skir', [
    Skir::controller(AccountController::class),
])->studio()->middleware('auth:sanctum');
```

Method middleware does not protect Studio. Put authentication and authorization on the outer route
when Studio or every RPC call must share that boundary. Test the Studio GET independently from a
method invocation.

## Protocol

Endpoints speak the standard SkirRPC protocol; there is nothing to configure. A request body is
either a JSON object `{"method": ..., "request": ...}` (answered in readable JSON) or
`name:number:format:request` (answered in dense JSON unless the format is `readable`). `GET` carries
the colon-separated form in the query string. Methods are looked up by number, or by name when the
number is empty.

## Required behavior coverage

Use PHPUnit feature tests at the Laravel boundary and never call a real external service. Cover the
paths applicable to the change:

- valid decoded request, exact encoded response, and persisted/application side effects;
- validation HTTP 422 with `skir_validation_failed` and field details;
- authorization HTTP 403 with `skir_authorization_failed`;
- unauthenticated and unauthorized method calls;
- protected and intentionally public Studio behavior;
- malformed JSON, missing method, unknown method, and invalid payload envelopes;
- duplicate method registration from controllers, providers, or manual handlers;
- both request forms (JSON object and colon-separated), lookup by name and by number;
- controller middleware ordering, short-circuit responses, and the terminable middleware rejection;
- regressions for explicit `null`, boundary request shapes, and any corrected failure contract.

Assert status, Skir error code/details, response content type/body, authorization boundary, and side
effects. For Studio, assert both rendered procedure metadata and protection on the outer route. Use
genuine encoded request/response bodies.

When a test fails, first identify whether the mismatch belongs to route protection, method
registration, Laravel resolution, payload decoding, procedure execution, or response encoding. Fix
the owning boundary instead of adding a second dispatch path.
