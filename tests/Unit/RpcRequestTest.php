<?php

declare(strict_types=1);

namespace Skir\Server\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Skir\Server\Exceptions\SkirServerException;
use Skir\Server\Http\RpcRequest;

final class RpcRequestTest extends TestCase
{
    #[Test]
    public function it_parses_a_colon_separated_request_by_number(): void
    {
        $request = RpcRequest::parse('Square:1001::[5,"a:b"]');

        $this->assertSame(1001, $request->method);
        $this->assertSame([5, 'a:b'], $request->payload);
        $this->assertFalse($request->readable);
    }

    #[Test]
    public function it_parses_a_colon_separated_request_by_name_when_the_number_is_empty(): void
    {
        $request = RpcRequest::parse('Square:::5');

        $this->assertSame('Square', $request->method);
        $this->assertSame(5, $request->payload);
    }

    #[Test]
    public function it_answers_in_readable_json_when_the_colon_format_asks_for_it(): void
    {
        $this->assertTrue(RpcRequest::parse('Square:1001:readable:5')->readable);
    }

    #[Test]
    public function it_parses_negative_method_numbers(): void
    {
        $this->assertSame(-7, RpcRequest::parse('Negative:-7::0')->method);
    }

    #[Test]
    public function it_keeps_large_integers_as_strings(): void
    {
        $this->assertSame('18446744073709551615', RpcRequest::parse('Big:1::18446744073709551615')->payload);
    }

    #[Test]
    public function it_parses_a_json_object_request_and_answers_in_readable_json(): void
    {
        $request = RpcRequest::parse('{"method": "Square", "request": {"value": 5}}');

        $this->assertSame('Square', $request->method);
        $this->assertSame(['value' => 5], $request->payload);
        $this->assertTrue($request->readable);
    }

    #[Test]
    public function it_accepts_a_method_number_in_a_json_object_request(): void
    {
        $this->assertSame(1001, RpcRequest::parse(' {"method": 1001, "request": null}')->method);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidRequests(): array
    {
        return [
            'empty body' => ['', 'Skir requests must be a JSON object or [method:number:format:request].'],
            'too few colons' => ['Square:1001:5', 'Skir requests must be a JSON object or [method:number:format:request].'],
            'non-numeric method number' => ['Square:abc::5', 'Invalid Skir method number [abc].'],
            'invalid colon payload JSON' => ['Square:1001::{', 'Invalid JSON: Syntax error'],
            'invalid JSON object' => ['{"method":', 'Invalid JSON: Syntax error'],
            'JSON that is not an object' => [' 5', 'Skir requests must be JSON objects.'],
            'missing method' => ['{"request": 5}', 'Skir requests must include a [method] field.'],
            'invalid method type' => ['{"method": true, "request": 5}', 'The [method] field must be a string or a number.'],
            'missing request' => ['{"method": "Square"}', 'Skir requests must include a [request] field.'],
        ];
    }

    #[Test]
    #[DataProvider('invalidRequests')]
    public function it_rejects_invalid_requests(string $body, string $message): void
    {
        try {
            RpcRequest::parse($body);
            $this->fail('Expected the request to be rejected.');
        } catch (SkirServerException $exception) {
            $this->assertSame('skir_invalid_request', $exception->errorCode);
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
