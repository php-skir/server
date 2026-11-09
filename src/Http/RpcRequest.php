<?php

declare(strict_types=1);

namespace Skir\Server\Http;

use JsonException;
use Skir\Server\Exceptions\SkirServerException;

/**
 * A SkirRPC call parsed from a request body or query string, following the upstream Skir service:
 * either a JSON object `{"method": ..., "request": ...}` (answered in readable JSON) or
 * `name:number:format:payload` (answered in dense JSON unless the format is `readable`).
 */
final readonly class RpcRequest
{
    private function __construct(
        public string|int $method,
        public mixed $payload,
        public bool $readable,
    ) {}

    public static function parse(string $body): self
    {
        $firstCharacter = substr($body, 0, 1);

        if ($firstCharacter === '{' || ctype_space($firstCharacter)) {
            return self::fromJsonObject($body);
        }

        return self::fromColonSeparated($body);
    }

    private static function fromJsonObject(string $body): self
    {
        $call = self::decodeJson($body);

        if (! is_array($call)) {
            throw SkirServerException::invalidRequest('Skir requests must be JSON objects.');
        }

        if (! array_key_exists('method', $call)) {
            throw SkirServerException::invalidRequest('Skir requests must include a [method] field.');
        }

        if (! is_string($call['method']) && ! is_int($call['method'])) {
            throw SkirServerException::invalidRequest('The [method] field must be a string or a number.');
        }

        if (! array_key_exists('request', $call)) {
            throw SkirServerException::invalidRequest('Skir requests must include a [request] field.');
        }

        return new self($call['method'], $call['request'], readable: true);
    }

    private static function fromColonSeparated(string $body): self
    {
        if (preg_match('/^([^:]*):([^:]*):([^:]*):(.*)$/s', $body, $matches) !== 1) {
            throw SkirServerException::invalidRequest('Skir requests must be a JSON object or [method:number:format:request].');
        }

        [, $methodName, $methodNumber, $format, $payload] = $matches;

        if ($methodNumber === '') {
            return new self($methodName, self::decodeJson($payload), $format === 'readable');
        }

        if (preg_match('/^-?[0-9]+$/', $methodNumber) !== 1) {
            throw SkirServerException::invalidRequest("Invalid Skir method number [{$methodNumber}].");
        }

        return new self((int) $methodNumber, self::decodeJson($payload), $format === 'readable');
    }

    private static function decodeJson(string $json): mixed
    {
        try {
            return json_decode($json, true, flags: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw SkirServerException::invalidRequest("Invalid JSON: {$exception->getMessage()}");
        }
    }
}
