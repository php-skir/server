<?php

declare(strict_types=1);

namespace Skir\Server;

use Skir\Runtime\MethodDescriptor;

final readonly class SkirServer
{
    public function __construct(
        private ProcedureRegistry $procedures,
    ) {}

    public function addMethod(MethodDescriptor $descriptor, callable $handler): void
    {
        $this->procedures->add($descriptor, $handler);
    }

    public function procedure(string|int $method): RegisteredProcedure
    {
        return $this->procedures->get($method);
    }

    /**
     * @return array<int, RegisteredProcedure>
     */
    public function procedures(): array
    {
        return $this->procedures->all();
    }
}
