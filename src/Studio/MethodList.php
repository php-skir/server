<?php

declare(strict_types=1);

namespace Skir\Server\Studio;

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
     *         response: array<string, mixed>
     *     }>
     * }
     */
    public function toArray(SkirServer $server): array
    {
        return [
            'methods' => array_values(array_map(
                fn (RegisteredProcedure $procedure): array => [
                    'method' => $procedure->descriptor->name,
                    'number' => $procedure->descriptor->number,
                    'request' => (new TypeDescriptor)->describe(
                        $procedure->descriptor->requestType,
                        "{$procedure->descriptor->name}Request",
                    ),
                    'response' => (new TypeDescriptor)->describe(
                        $procedure->descriptor->responseType,
                        "{$procedure->descriptor->name}Response",
                    ),
                ],
                $server->procedures(),
            )),
        ];
    }
}
