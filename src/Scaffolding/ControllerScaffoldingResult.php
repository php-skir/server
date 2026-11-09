<?php

declare(strict_types=1);

namespace Skir\Server\Scaffolding;

final readonly class ControllerScaffoldingResult
{
    /**
     * @param  list<string>  $createdPaths
     * @param  list<string>  $unchangedPaths
     * @param  list<RouteRegistration>  $registrations
     * @param  list<string>  $updatedPaths
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $createdPaths,
        public array $unchangedPaths,
        public array $registrations,
        public array $updatedPaths = [],
        public array $warnings = [],
    ) {}
}
