<?php

declare(strict_types=1);

namespace Skir\Server\Routing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Route;
use LogicException;
use Skir\Server\Exceptions\SkirServerException;
use Skir\Server\ProcedureProvider;
use Skir\Server\ProcedureRegistry;
use Skir\Server\SkirServer;
use UnitEnum;

/** @internal */
final class SkirEndpointRegistry
{
    /** @var array<string, SkirServer> */
    private array $servers = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /** @param list<mixed> $providers */
    public function register(Route $route, array $providers): void
    {
        $server = $this->build($providers);
        $cacheable = $this->isCacheable($providers);
        $endpoint = $cacheable
            ? hash('xxh128', serialize($providers))
            : spl_object_hash($server);

        $this->servers[$endpoint] = $server;
        $route->defaults('skirEndpoint', $endpoint);

        if ($cacheable) {
            $route->defaults('skirProviders', $providers);
        }
    }

    public function server(Route $route): SkirServer
    {
        $endpoint = $route->defaults['skirEndpoint'] ?? null;

        if (! is_string($endpoint)) {
            return $this->container->make(SkirServer::class);
        }

        if (array_key_exists($endpoint, $this->servers)) {
            return $this->servers[$endpoint];
        }

        $providers = $route->defaults['skirProviders'] ?? null;

        if (! is_array($providers)) {
            throw new LogicException(
                "Skir endpoint [{$route->uri()}] registers provider instances, which cannot be restored from the route cache. "
                .'Register class-string providers or Skir::controller() / Skir::method() definitions, or run `php artisan route:clear`.',
            );
        }

        return $this->servers[$endpoint] = $this->build($providers);
    }

    /** @param list<mixed> $providers */
    private function build(array $providers): SkirServer
    {
        $server = new SkirServer(new ProcedureRegistry);

        foreach ($providers as $provider) {
            $this->resolveProvider($provider)->register($server);
        }

        return $server;
    }

    private function resolveProvider(mixed $provider): ProcedureProvider|SkirRouteDefinition
    {
        if (is_string($provider)) {
            $provider = $this->resolveProviderClass($provider);
        }

        if ($provider instanceof ProcedureProvider) {
            return $provider;
        }

        if ($provider instanceof SkirRouteDefinition) {
            return $provider;
        }

        throw SkirServerException::invalidRouteProvider($provider);
    }

    private function resolveProviderClass(string $provider): mixed
    {
        $isResolvable = $this->container->bound($provider)
            || class_exists($provider)
            || interface_exists($provider);

        if (! $isResolvable) {
            throw SkirServerException::invalidRouteProvider($provider);
        }

        return $this->container->make($provider);
    }

    /** @param list<mixed> $providers */
    private function isCacheable(array $providers): bool
    {
        return array_all($providers, static function (mixed $provider): bool {
            if (is_string($provider)) {
                return true;
            }

            if ($provider instanceof SkirControllerRouteDefinition) {
                return true;
            }

            if ($provider instanceof SkirMethodRouteDefinition) {
                return $provider->method instanceof UnitEnum;
            }

            return false;
        });
    }
}
