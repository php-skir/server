![Laravel Skir Server](art/banner.png)

# Laravel Skir Server

[![Tests](https://github.com/php-skir/server/actions/workflows/tests.yml/badge.svg)](https://github.com/php-skir/server/actions/workflows/tests.yml)
[![Coverage](https://raw.githubusercontent.com/php-skir/server/badges/coverage.svg)](https://github.com/php-skir/server/actions/workflows/tests.yml)
[![Composer](https://img.shields.io/packagist/v/php-skir/server?label=composer&logo=composer)](https://packagist.org/packages/php-skir/server)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4?logo=php&logoColor=white)](https://packagist.org/packages/php-skir/server)
[![License](https://img.shields.io/github/license/php-skir/server)](LICENSE)

Laravel package for exposing SkirRPC methods from a Laravel application.

Requires PHP 8.4+, Laravel 13, and Node.js for the Skir compiler.

## Why Skir?

[Skir](https://skir.build/) is a schema language: you define structs, enums, and RPC methods once and generate typed code for PHP, TypeScript, Python, Go, and other languages. This package serves those methods over SkirRPC from ordinary Laravel controllers.

## Features

- Generate typed server contracts with Simple Data Objects, Laravel Data, or standard PHP objects. See [Generated procedures](docs/generated-procedures.md).
- Scaffold application-owned controllers and Form Requests from generated manifests. See [Scaffolding](docs/scaffolding.md).
- Route procedures to attributed or invokable Laravel controllers with native dependency injection, middleware, authorization, and Form Requests. See [Routing](docs/routing.md).
- Keep generated providers and manual handlers available for non-controller layouts. See [Routing](docs/routing.md#generated-procedure-providers).
- Inspect an endpoint's procedures through its opt-in Studio. See [Studio](docs/routing.md#studio).
- Speak the standard SkirRPC protocol, so clients generated for any Skir language can call your endpoints. See [Wire protocol](docs/protocol.md).

## Quick start

Install the server package and one PHP generator. This example uses Simple Data Objects; see [Generated procedures](docs/generated-procedures.md) for Laravel Data and standard PHP objects:

```bash
composer require php-skir/server std-out/simple-data-objects
npm install --save-dev skir skir-simple-data-objects-generator
```

The package defaults match the paths used below. To customize manifest locations, generator execution, controller layout, Form Request generation, or Studio, publish `config/skir-server.php`:

```bash
php artisan vendor:publish --tag=skir-server-config
```

See [Scaffolding configuration](docs/scaffolding.md#configure-manifests-and-destinations) for the current keys and defaults. Calling `studio()` or `studio(false)` on a route overrides the Studio default.

Define a Skir method:

```skir
// skir-src/admin/users.skir
struct GetUserRequest {
  user_id: int32;
}

struct User {
  user_id: int32;
  name: string;
}

method GetUser(GetUserRequest): User = 3180856469;
```

Configure the generator in `skir.yml`:

```yaml
generators:
  - mod: skir-simple-data-objects-generator
    outDir: app/Skir/skirout
    config:
      namespace: Skir
```

Skir owns the `/skirout` directory and may replace files inside it, so keep handwritten code elsewhere.

Generate the PHP contracts, configure Composer, and refresh the autoloader:

```bash
npx skir gen
npx skir-simple-data-objects-generator configure-composer
composer dump-autoload
```

The `configure-composer` command reads `skir.yml` and adds this PSR-4 mapping to `composer.json` when it is missing:

```json
{
  "autoload": {
    "psr-4": {
      "Skir\\": "app/Skir/skirout/"
    }
  }
}
```

Besides the PHP contracts, the generator writes `skir-server-manifest.json` into its `outDir`. `php artisan skir:make` reads the manifests listed in the `skir-server.manifests` config key, which defaults to `app/Skir/skirout/skir-server-manifest.json`, so this layout needs no configuration. Rerun `npx skir gen` (or pass `--generate`) whenever the schema changes.

Scaffold every generated method and its Form Request into the application-owned `App\Skir` namespace with:

```bash
php artisan skir:make --all
```

Generated Form Requests deny access by default; define their `authorize()` and `rules()` methods before exposing the procedure. Scaffolded controller methods throw a `LogicException` until you implement them. The command prints the route registrations to add. After implementation, a module controller looks like this:

```php
<?php

declare(strict_types=1);

namespace App\Skir\Admin;

use App\Http\Requests\Skir\Admin\GetUserFormRequest;
use App\Models\User as UserModel;
use Skir\Admin\AdminSkirMethod;
use Skir\Admin\UserData;
use Skir\Server\Attributes\SkirMethod;

final class AdminController
{
    #[SkirMethod(AdminSkirMethod::GetUser)]
    public function getUser(GetUserFormRequest $request): UserData
    {
        $input = $request->skir();
        $user = UserModel::query()->findOrFail($input->userId);

        return new UserData(
            userId: $user->id,
            name: $user->name,
        );
    }
}
```

Register the controller on a SkirRPC endpoint in `routes/api.php` (create it with `php artisan install:api` on Laravel 13):

```php
use App\Skir\Admin\AdminController;
use Illuminate\Support\Facades\Route;
use Skir\Server\Facades\Skir;

Route::skirRpc('/skir', [
    Skir::controller(AdminController::class),
])->studio();
```

The API routes file adds the `/api` prefix, so this endpoint serves `/api/skir`. A POST endpoint in `routes/web.php` fails CSRF verification with HTTP 419 unless its URI is excluded. Class strings, `Skir::controller()`, and `Skir::method()` registrations survive `php artisan route:cache`; provider instances do not. See [Routing](docs/routing.md#route-caching).

Only public controller methods carrying a `SkirMethod` attribute are registered. The generated enum case identifies the Skir method; the PHP method name does not.

The `studio()` call explicitly enables the endpoint-scoped Studio regardless of the configured default. With the default query key, Studio is available at `/api/skir?studio`. See [Routing and Studio](docs/routing.md#studio) for middleware, authorization, and alternative routing layouts.

## Optional client package

The [`php-skir/client`](https://github.com/php-skir/client) package is not required to host a SkirRPC server. It normally belongs in the application consuming this endpoint.
