<?php

declare(strict_types=1);

namespace Skir\Server;

use Skir\Runtime\MethodDescriptor;
use Skir\Server\Exceptions\SkirServerException;

final class ProcedureRegistry
{
    /** @var array<int, RegisteredProcedure> */
    private array $procedures = [];

    public function add(MethodDescriptor $descriptor, callable $handler): void
    {
        if (array_key_exists($descriptor->number, $this->procedures)) {
            throw SkirServerException::duplicateMethod($descriptor->name, $descriptor->number);
        }

        $this->procedures[$descriptor->number] = new RegisteredProcedure($descriptor, $handler);
    }

    public function get(string|int $method): RegisteredProcedure
    {
        if (is_int($method)) {
            return $this->procedures[$method] ?? throw SkirServerException::methodNotFound((string) $method);
        }

        $matches = array_values(array_filter(
            $this->procedures,
            fn (RegisteredProcedure $procedure): bool => $procedure->descriptor->name === $method,
        ));

        if (count($matches) > 1) {
            throw SkirServerException::ambiguousMethod($method);
        }

        return $matches[0] ?? throw SkirServerException::methodNotFound($method);
    }

    /**
     * @return array<int, RegisteredProcedure>
     */
    public function all(): array
    {
        return $this->procedures;
    }
}
