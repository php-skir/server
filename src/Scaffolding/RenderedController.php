<?php

declare(strict_types=1);

namespace Skir\Server\Scaffolding;

final readonly class RenderedController
{
    /**
     * @param  list<RouteRegistration>  $registrations
     * @param  list<string>  $warnings
     */
    public function __construct(
        public RenderedFile $file,
        public array $registrations,
        public bool $existing = false,
        public bool $changed = true,
        public array $warnings = [],
        public ?FileSnapshot $originalSnapshot = null,
    ) {}
}
