<?php

declare(strict_types=1);

namespace Skir\Server\Tests\Feature;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Skir\Server\ProcedureProvider;
use Skir\Server\Routing\SkirEndpointRegistry;
use Skir\Server\SkirServer;
use Skir\Server\SkirServerServiceProvider;
use Skir\Server\Tests\TestCase;

final class SkirServerConfigurationTest extends TestCase
{
    #[Test]
    public function studio_is_disabled_by_default(): void
    {
        $this->assertFalse(config('skir-server.studio_enabled'));
    }

    #[Test]
    public function studio_uses_the_studio_query_key_by_default(): void
    {
        $this->assertSame('studio', config('skir-server.studio_query_key'));
    }

    #[Test]
    public function studio_can_be_enabled_by_configuration(): void
    {
        config(['skir-server.studio_enabled' => true]);

        Route::skirRpc('/configured-studio', [
            new ConfigurationProcedureProvider,
        ]);

        $this
            ->get('/configured-studio?studio')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Skir RPC Studio');
    }

    #[Test]
    public function studio_can_be_disabled_on_a_route_when_enabled_by_configuration(): void
    {
        config(['skir-server.studio_enabled' => true]);

        Route::skirRpc('/disabled-configured-studio', [
            new ConfigurationProcedureProvider,
        ])->studio(false);

        $this
            ->get('/disabled-configured-studio?studio')
            ->assertNotFound();
    }

    #[Test]
    public function studio_query_key_can_be_configured(): void
    {
        config([
            'skir-server.studio_enabled' => true,
            'skir-server.studio_query_key' => 'skir-studio',
        ]);

        Route::skirRpc('/configured-studio-query-key', [
            new ConfigurationProcedureProvider,
        ]);

        $this
            ->get('/configured-studio-query-key?studio')
            ->assertUnprocessable()
            ->assertDontSee('Skir RPC Studio');

        $this
            ->get('/configured-studio-query-key?skir-studio')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Skir RPC Studio');
    }

    #[Test]
    public function studio_configuration_is_snapshotted_when_the_route_is_registered(): void
    {
        config([
            'skir-server.studio_enabled' => true,
            'skir-server.studio_query_key' => 'registered-studio',
        ]);

        Route::skirRpc('/snapshotted-studio', [
            new ConfigurationProcedureProvider,
        ]);

        config([
            'skir-server.studio_enabled' => false,
            'skir-server.studio_query_key' => 'changed-studio',
        ]);

        $this
            ->get('/snapshotted-studio?registered-studio')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Skir RPC Studio');

        $this
            ->get('/snapshotted-studio?changed-studio')
            ->assertUnprocessable()
            ->assertDontSee('Skir RPC Studio');
    }

    #[Test]
    public function package_configuration_is_publishable(): void
    {
        $publishablePaths = ServiceProvider::pathsToPublish(
            SkirServerServiceProvider::class,
            'skir-server-config',
        );

        $this->assertCount(1, $publishablePaths);
        $this->assertSame([config_path('skir-server.php')], array_values($publishablePaths));

        $sourcePath = array_key_first($publishablePaths);

        $this->assertIsString($sourcePath);
        $this->assertFileExists($sourcePath);
    }

    private function serverFromRoute(LaravelRoute $route): SkirServer
    {
        return app(SkirEndpointRegistry::class)->server($route);
    }
}

final class ConfigurationProcedureProvider implements ProcedureProvider
{
    public function register(SkirServer $server): void {}
}
