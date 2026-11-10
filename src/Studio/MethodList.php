<?php

declare(strict_types=1);

namespace Skir\Server\Studio;

use Skir\Runtime\MethodDescriptor;
use Skir\Server\RegisteredProcedure;
use Skir\Server\SkirServer;

/** What an endpoint answers to `?list`: its methods, in the format of the upstream Skir services. */
final class MethodList
{
    /**
     * @return array{
     *     methods: list<array{
     *         method: string,
     *         number: int,
     *         request: array<string, mixed>,
     *         response: array<string, mixed>,
     *         doc?: string
     *     }>
     * }
     */
    public function toArray(SkirServer $server): array
    {
        return [
            'methods' => array_values(array_map(
                fn (RegisteredProcedure $procedure): array => $this->method($procedure->descriptor),
                $server->procedures(),
            )),
        ];
    }

    /**
     * @return array{
     *     method: string,
     *     number: int,
     *     request: array<string, mixed>,
     *     response: array<string, mixed>,
     *     doc?: string
     * }
     */
    private function method(MethodDescriptor $descriptor): array
    {
        $method = [
            'method' => $descriptor->name,
            'number' => $descriptor->number,
            'request' => (new TypeDescriptor)->describe($descriptor->requestType, "{$descriptor->name}Request"),
            'response' => (new TypeDescriptor)->describe($descriptor->responseType, "{$descriptor->name}Response"),
        ];

        if ($descriptor->doc === null) {
            return $method;
        }

        if ($descriptor->doc === '') {
            return $method;
        }

        return [...$method, 'doc' => $descriptor->doc];
    }
}
