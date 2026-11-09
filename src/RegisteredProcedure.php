<?php

declare(strict_types=1);

namespace Skir\Server;

use Closure;
use Skir\Runtime\MethodDescriptor;
use Skir\Server\Contracts\PreparesProcedure;

final readonly class RegisteredProcedure
{
    private Closure|PreparesProcedure $handler;

    public function __construct(
        public MethodDescriptor $descriptor,
        callable|PreparesProcedure $handler,
    ) {
        $this->handler = $handler instanceof PreparesProcedure
            ? $handler
            : Closure::fromCallable($handler);
    }

    public function prepare(mixed $request, SkirContext $context): PreparedProcedure
    {
        if ($this->handler instanceof PreparesProcedure) {
            return $this->handler->prepare($request, $context);
        }

        return new PreparedProcedure(
            [],
            fn (): mixed => ($this->handler)($request, $context),
        );
    }
}
