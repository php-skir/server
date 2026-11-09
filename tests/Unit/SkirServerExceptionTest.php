<?php

declare(strict_types=1);

namespace Skir\Server\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Skir\Server\Exceptions\SkirServerException;

final class SkirServerExceptionTest extends TestCase
{
    #[Test]
    #[DataProvider('packageExceptions')]
    public function it_exposes_stable_error_contracts(
        SkirServerException $exception,
        string $errorCode,
        int $status,
        string $message,
    ): void {
        $this->assertSame($errorCode, $exception->errorCode);
        $this->assertSame($status, $exception->status);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * @return array<string, array{SkirServerException, string, int, string}>
     */
    public static function packageExceptions(): array
    {
        return [
            'invalid request' => [
                SkirServerException::invalidRequest('Invalid payload.'),
                'skir_invalid_request',
                422,
                'Invalid payload.',
            ],
            'method not found' => [
                SkirServerException::methodNotFound('Missing'),
                'skir_method_not_found',
                404,
                'Skir method [Missing] is not registered.',
            ],
            'duplicate method' => [
                SkirServerException::duplicateMethod('Duplicate', 7),
                'skir_duplicate_method',
                422,
                'Skir method [Duplicate] with number [7] is already registered on this endpoint.',
            ],
            'invalid route provider' => [
                SkirServerException::invalidRouteProvider(new InvalidRouteProvider),
                'skir_invalid_route_provider',
                500,
                'Skir route provider ['.InvalidRouteProvider::class.'] must implement '
                .'[Skir\\Server\\Routing\\SkirRouteDefinition] or [Skir\\Server\\ProcedureProvider].',
            ],
            'missing route provider class' => [
                SkirServerException::invalidRouteProvider('App\\Skir\\MissingProvider'),
                'skir_invalid_route_provider',
                500,
                'Skir route provider [App\\Skir\\MissingProvider] must implement '
                .'[Skir\\Server\\Routing\\SkirRouteDefinition] or [Skir\\Server\\ProcedureProvider].',
            ],
        ];
    }
}

final class InvalidRouteProvider {}
