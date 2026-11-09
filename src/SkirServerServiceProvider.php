<?php

declare(strict_types=1);

namespace Skir\Server;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Skir\Server\Commands\GeneratorRunner;
use Skir\Server\Commands\MakeSkirCommand;
use Skir\Server\Commands\MakeSkirRequestCommand;
use Skir\Server\Commands\SymfonyGeneratorRunner;
use Skir\Server\Http\Controllers\SkirRpcController;
use Skir\Server\Routing\SkirControllerDispatcher;
use Skir\Server\Routing\SkirEndpointRegistry;
use Skir\Server\Scaffolding\ControllerScaffolder;
use Skir\Server\Scaffolding\ControllerScaffolding;
use Skir\Server\Scaffolding\Manifest\ManifestRepository;

final class SkirServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $configurationPath = __DIR__.'/../config/skir-server.php';

        $this->mergeConfigFrom($configurationPath, 'skir-server');

        $defaults = require $configurationPath;
        $configuredScaffolding = config('skir-server.scaffolding', []);
        $configuredScaffolding = is_array($configuredScaffolding) ? $configuredScaffolding : [];

        config()->set('skir-server.scaffolding', array_replace(
            $defaults['scaffolding'],
            $configuredScaffolding,
        ));

        $this->app->singleton(ProcedureRegistry::class);
        $this->app->singleton(SkirServer::class);
        $this->app->singleton(SkirEndpointRegistry::class);
        $this->app->singleton(ManifestRepository::class);
        $this->app->bind(GeneratorRunner::class, SymfonyGeneratorRunner::class);
        $this->app->bind(ControllerScaffolding::class, ControllerScaffolder::class);
        $this->app->bind(SkirControllerDispatcher::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/skir-server.php' => config_path('skir-server.php'),
        ], 'skir-server-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeSkirCommand::class,
                MakeSkirRequestCommand::class,
            ]);
        }

        LaravelRoute::macro('studio', function (bool $enabled = true) {
            /** @var LaravelRoute $this */
            return $this->defaults('skirStudioEnabled', $enabled);
        });

        /** @var Router $router */
        $router = $this->app['router'];
        $application = $this->app;

        $router->macro('skirRpc', function (string $uri, array $providers = []) use ($application) {
            /** @var Router $this */
            $route = $this
                ->match(['GET', 'POST'], $uri, SkirRpcController::class)
                ->defaults('skirStudioEnabled', (bool) config('skir-server.studio_enabled', false))
                ->defaults('skirStudioQueryKey', (string) config('skir-server.studio_query_key', 'studio'));

            if ($providers === []) {
                return $route;
            }

            $application->make(SkirEndpointRegistry::class)->register($route, $providers);

            return $route;
        });
    }
}
