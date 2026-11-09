<?php

declare(strict_types=1);

namespace Skir\Server\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class SkirServerException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $details
     */
    private function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function invalidRequest(string $message): self
    {
        return new self($message, 'skir_invalid_request', 422);
    }

    public static function methodNotFound(string $method): self
    {
        return new self("Skir method [{$method}] is not registered.", 'skir_method_not_found', 404);
    }

    public static function duplicateMethod(string $method, int $number): self
    {
        return new self("Skir method [{$method}] with number [{$number}] is already registered on this endpoint.", 'skir_duplicate_method', 422);
    }

    public static function ambiguousMethod(string $method): self
    {
        return new self("Skir method name [{$method}] is ambiguous on this endpoint; call it by number instead.", 'skir_invalid_request', 422);
    }

    public static function invalidResponse(string $method, string $message): self
    {
        return new self("Skir method [{$method}] returned a value that cannot be serialized: {$message}", 'skir_invalid_response', 500);
    }

    public static function invalidRouteProvider(mixed $provider): self
    {
        $providerType = is_string($provider) ? $provider : get_debug_type($provider);

        return new self(
            "Skir route provider [{$providerType}] must implement [Skir\\Server\\Routing\\SkirRouteDefinition] or [Skir\\Server\\ProcedureProvider].",
            'skir_invalid_route_provider',
            500,
        );
    }

    /** @param array<string, array<int, string>> $errors */
    public static function validationFailed(array $errors): self
    {
        return new self('The given data was invalid.', 'skir_validation_failed', 422, $errors);
    }

    public static function authorizationFailed(): self
    {
        return new self('This action is unauthorized.', 'skir_authorization_failed', 403);
    }

    public function render(Request $request): JsonResponse
    {
        $error = [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ];

        if ($this->details !== []) {
            $error['details'] = $this->details;
        }

        return response()->json([
            'error' => $error,
        ], $this->status);
    }
}
