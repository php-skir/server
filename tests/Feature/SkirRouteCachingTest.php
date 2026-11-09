<?php

declare(strict_types=1);

namespace Skir\Server\Tests\Feature;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Skir\Runtime\MethodDescriptor;
use Skir\Runtime\Type;
use Skir\Server\Attributes\SkirMethod;
use Skir\Server\Contracts\SkirMethodReference;
use Skir\Server\Facades\Skir;
use Skir\Server\ProcedureProvider;
use Skir\Server\Routing\SkirEndpointRegistry;
use Skir\Server\SkirServer;
use Skir\Server\Tests\TestCase;

final class SkirRouteCachingTest extends TestCase
{
    #[Test]
    public function class_string_providers_and_controller_definitions_survive_route_caching(): void
    {
        Route::skirRpc('/cached-provider', [CachedRouteProvider::class]);
        Route::skirRpc('/cached-controller', [Skir::controller(CachedRouteController::class)]);
        Route::skirRpc('/cached-method', [Skir::method(CachedRouteMethod::Triple, CachedRouteInvokableController::class)]);

        $this->cacheRoutes();

        $this->postJson('/cached-provider', ['method' => 'CachedSquare', 'request' => 5.0])
            ->assertOk()
            ->assertContent('25');
        $this->postJson('/cached-controller', ['method' => 'CachedDouble', 'request' => 5.0])
            ->assertOk()
            ->assertContent('10');
        $this->postJson('/cached-method', ['method' => 'CachedTriple', 'request' => 5.0])
            ->assertOk()
            ->assertContent('15');
    }

    #[Test]
    public function provider_instances_explain_that_they_cannot_be_restored_from_the_route_cache(): void
    {
        Route::skirRpc('/cached-instance', [new CachedRouteProvider]);

        $this->cacheRoutes();

        $this->withoutExceptionHandling();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Skir endpoint [cached-instance] registers provider instances, which cannot be restored from the route cache.');

        $this->postJson('/cached-instance', ['method' => 'CachedSquare', 'request' => 5.0]);
    }

    private function cacheRoutes(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $routes = $router->getRoutes();

        foreach ($routes as $route) {
            $route->prepareForSerialization();
        }

        $compiledRoutes = var_export($routes->compile(), true);

        $this->app->forgetInstance(SkirEndpointRegistry::class);
        $router->setCompiledRoutes(eval("return {$compiledRoutes};"));
    }
}

final class CachedRouteProvider implements ProcedureProvider
{
    public function register(SkirServer $server): void
    {
        $server->addMethod(
            new MethodDescriptor('CachedSquare', 7001, Type::float32(), Type::float32()),
            fn (float $value): float => $value * $value,
        );
    }
}

enum CachedRouteMethod implements SkirMethodReference
{
    case Double;
    case Triple;

    public function descriptor(): MethodDescriptor
    {
        return match ($this) {
            self::Double => new MethodDescriptor('CachedDouble', 7002, Type::float32(), Type::float32()),
            self::Triple => new MethodDescriptor('CachedTriple', 7003, Type::float32(), Type::float32()),
        };
    }
}

final class CachedRouteController
{
    #[SkirMethod(CachedRouteMethod::Double)]
    public function double(float $value): float
    {
        return $value * 2;
    }
}

final class CachedRouteInvokableController
{
    public function __invoke(float $value): float
    {
        return $value * 3;
    }
}
