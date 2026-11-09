<?php

declare(strict_types=1);

namespace Skir\Server\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Skir\Runtime\Field;
use Skir\Runtime\MethodDescriptor;
use Skir\Runtime\Type;
use Skir\Server\Attributes\SkirMethod;
use Skir\Server\Contracts\SkirMethodReference;
use Skir\Server\Exceptions\SkirServerException;
use Skir\Server\Facades\Skir;
use Skir\Server\ProcedureProvider;
use Skir\Server\RequestContext;
use Skir\Server\Routing\SkirEndpointRegistry;
use Skir\Server\SkirContext;
use Skir\Server\SkirServer;
use Skir\Server\Tests\TestCase;

final class SkirRpcControllerTest extends TestCase
{
    #[Test]
    public function it_answers_colon_separated_requests_in_dense_json(): void
    {
        Route::skirRpc('/colon-rpc', [RenameUserProcedureProvider::class]);

        $this
            ->call('POST', '/colon-rpc', content: 'RenameUser:1003::[42,"Ruben"]')
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertContent('[42,"Ruben updated"]');
    }

    #[Test]
    public function it_answers_colon_separated_requests_in_readable_json_when_asked(): void
    {
        Route::skirRpc('/colon-readable-rpc', [RenameUserProcedureProvider::class]);

        $this
            ->call('POST', '/colon-readable-rpc', content: 'RenameUser:1003:readable:[42,"Ruben"]')
            ->assertOk()
            ->assertExactJson([
                'id' => 42,
                'name' => 'Ruben updated',
            ]);
    }

    #[Test]
    public function it_looks_up_methods_by_name_when_the_number_is_empty(): void
    {
        Route::skirRpc('/by-name-rpc', [SquareProcedureProvider::class]);

        $this
            ->call('POST', '/by-name-rpc', content: 'Square:::5')
            ->assertOk()
            ->assertContent('25');
    }

    #[Test]
    public function the_method_number_wins_over_the_name(): void
    {
        Route::skirRpc('/by-number-rpc', [SquareProcedureProvider::class]);

        $this
            ->call('POST', '/by-number-rpc', content: 'Anything:1001::5')
            ->assertOk()
            ->assertContent('25');
    }

    #[Test]
    public function it_accepts_a_method_number_in_json_object_requests(): void
    {
        Route::skirRpc('/json-number-rpc', [SquareProcedureProvider::class]);

        $this
            ->postJson('/json-number-rpc', [
                'method' => 1001,
                'request' => 5,
            ])
            ->assertOk()
            ->assertContent('25');
    }

    #[Test]
    public function it_reads_get_requests_from_the_query_string(): void
    {
        Route::skirRpc('/get-rpc', [RenameUserProcedureProvider::class]);

        $this
            ->get('/get-rpc?'.rawurlencode('RenameUser:1003::[42,"Ruben"]'))
            ->assertOk()
            ->assertContent('[42,"Ruben updated"]');
    }

    #[Test]
    public function it_rejects_ambiguous_method_names(): void
    {
        $server = app(SkirServer::class);
        $server->addMethod(new MethodDescriptor('Same', 2001, Type::int32(), Type::int32()), fn (int $value): int => $value);
        $server->addMethod(new MethodDescriptor('Same', 2002, Type::int32(), Type::int32()), fn (int $value): int => $value);

        $this
            ->call('POST', '/skir', content: 'Same:::1')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'skir_invalid_request');

        $this
            ->call('POST', '/skir', content: 'Same:2002::1')
            ->assertOk()
            ->assertContent('1');
    }

    #[Test]
    public function it_reports_responses_that_do_not_match_the_response_type(): void
    {
        app(SkirServer::class)->addMethod(
            new MethodDescriptor('Broken', 2003, Type::int32(), Type::string()),
            fn (int $value): array => [$value],
        );

        $this
            ->call('POST', '/skir', content: 'Broken:2003::1')
            ->assertServerError()
            ->assertJsonPath('error.code', 'skir_invalid_response');
    }

    #[Test]
    public function it_dispatches_a_posted_skir_rpc_request(): void
    {
        app(SkirServer::class)->addMethod(
            new MethodDescriptor('Square', 1001, Type::float32(), Type::float32()),
            fn (float $value): float => $value * $value,
        );

        $this
            ->postJson('/skir', [
                'method' => 'Square',
                'request' => 5.0,
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertContent('25');
    }

    #[Test]
    public function it_decodes_struct_requests_and_encodes_struct_responses(): void
    {
        $userType = Type::struct([
            Field::value('id', 0, Type::int32()),
            Field::value('name', 1, Type::string()),
        ]);

        app(SkirServer::class)->addMethod(
            new MethodDescriptor('RenameUser', 1002, $userType, $userType),
            fn (array $user, RequestContext $context): array => [
                'id' => $user['id'],
                'name' => "{$user['name']} from {$context->request->ip()}",
            ],
        );

        $this
            ->postJson('/skir', [
                'method' => 'RenameUser',
                'request' => [42, 'Ruben'],
            ])
            ->assertOk()
            ->assertExactJson(['id' => 42, 'name' => 'Ruben from 127.0.0.1']);
    }

    #[Test]
    public function it_dispatches_a_get_request_from_the_query_string(): void
    {
        app(SkirServer::class)->addMethod(
            new MethodDescriptor('Square', 1001, Type::float32(), Type::float32()),
            fn (float $value): float => $value * $value,
        );

        $this
            ->getJson('/skir?Square:1001::5.0')
            ->assertOk()
            ->assertContent('25');
    }

    #[Test]
    public function it_dispatches_a_head_request_from_the_query_string(): void
    {
        app(SkirServer::class)->addMethod(
            new MethodDescriptor('Square', 1001, Type::float32(), Type::float32()),
            fn (float $value): float => $value * $value,
        );

        $this
            ->call(
                'HEAD',
                '/skir?Square:1001::5.0',
                server: ['HTTP_ACCEPT' => 'application/json'],
            )
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertContent('');
    }

    #[Test]
    public function it_registers_a_route_macro_for_skir_rpc_endpoints(): void
    {
        $route = Route::skirRpc('/rpc');

        $this->assertInstanceOf(LaravelRoute::class, $route);

        app(SkirServer::class)->addMethod(
            new MethodDescriptor('Square', 1001, Type::float32(), Type::float32()),
            fn (float $value): float => $value * $value,
        );

        $this
            ->postJson('/rpc', [
                'method' => 'Square',
                'request' => 5.0,
            ])
            ->assertOk()
            ->assertContent('25');
    }

    #[Test]
    public function it_exposes_endpoint_studio_when_enabled_on_the_route(): void
    {
        Route::skirRpc('/studio-rpc', [
            SquareProcedureProvider::class,
            DoubleProcedureProvider::class,
        ])->studio();

        $this
            ->get('/studio-rpc?studio')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Skir RPC Studio')
            ->assertSee('Square')
            ->assertSee('Double')
            ->assertSee('/studio-rpc');
    }

    #[Test]
    public function it_keeps_endpoint_studio_disabled_by_default(): void
    {
        Route::skirRpc('/private-studio-rpc', [
            SquareProcedureProvider::class,
        ]);

        $this
            ->get('/private-studio-rpc?studio')
            ->assertNotFound();
    }

    #[Test]
    public function it_registers_procedure_providers_on_a_route(): void
    {
        $route = Route::skirRpc('/provider-rpc', [
            SquareProcedureProvider::class,
        ]);
        $server = app(SkirEndpointRegistry::class)->server($route);

        $prepared = $server->procedure('Square')->prepare(
            5.0,
            new RequestContext(Request::create('/provider-rpc', 'POST'), TestSkirMethod::Square->descriptor()),
        );

        $this->assertSame([], $prepared->middleware);
        $this->assertSame(25.0, $prepared->invoke());

        $this
            ->postJson('/provider-rpc', [
                'method' => 'Square',
                'request' => 5.0,
            ])
            ->assertOk()
            ->assertContent('25');
    }

    #[Test]
    public function it_dispatches_an_explicit_invokable_controller_method(): void
    {
        Route::skirRpc('/controller-rpc', [
            Skir::method(TestSkirMethod::Square, SquareController::class),
        ]);

        $this
            ->postJson('/controller-rpc', [
                'method' => 'Square',
                'request' => 5.0,
            ])
            ->assertOk()
            ->assertContent('25');
    }

    #[Test]
    public function it_registers_attributed_public_controller_methods(): void
    {
        Route::skirRpc('/attribute-rpc', [
            Skir::controller(MultiMethodController::class),
        ]);

        $this
            ->postJson('/attribute-rpc', [
                'method' => 'Double',
                'request' => 5.0,
            ])
            ->assertOk()
            ->assertContent('10');

        $this
            ->postJson('/attribute-rpc', [
                'method' => 'RenameUser',
                'request' => [42, 'Maxim'],
            ])
            ->assertOk()
            ->assertExactJson(['id' => 42, 'name' => 'Maxim updated']);

        $this
            ->postJson('/attribute-rpc', [
                'method' => 'Ignored',
                'request' => 5.0,
            ])
            ->assertNotFound();
    }

    #[Test]
    public function it_allows_multiple_controllers_on_one_endpoint(): void
    {
        Route::skirRpc('/multi-controller-rpc', [
            Skir::method(TestSkirMethod::Square, SquareController::class),
            Skir::controller(MultiMethodController::class),
        ]);

        $this
            ->postJson('/multi-controller-rpc', [
                'method' => 'Square',
                'request' => 5.0,
            ])
            ->assertOk()
            ->assertContent('25');

        $this
            ->postJson('/multi-controller-rpc', [
                'method' => 'Double',
                'request' => 5.0,
            ])
            ->assertOk()
            ->assertContent('10');
    }

    #[Test]
    public function it_rejects_duplicate_methods_on_one_endpoint(): void
    {
        $this->expectException(SkirServerException::class);
        $this->expectExceptionMessage('Skir method [Square] with number [1001] is already registered on this endpoint.');

        Route::skirRpc('/duplicate-rpc', [
            Skir::method(TestSkirMethod::Square, SquareController::class),
            Skir::method(TestSkirMethod::Square, SquareController::class),
        ]);
    }

    #[Test]
    public function studio_lists_controller_registered_methods(): void
    {
        Route::skirRpc('/controller-studio-rpc', [
            Skir::controller(MultiMethodController::class),
        ])->studio();

        $this
            ->get('/controller-studio-rpc?studio')
            ->assertOk()
            ->assertSee('Double')
            ->assertSee('RenameUser')
            ->assertDontSee('ignored');
    }

    #[Test]
    public function it_keeps_route_procedure_registries_isolated(): void
    {
        Route::skirRpc('/square-rpc', [
            SquareProcedureProvider::class,
        ]);

        Route::skirRpc('/double-rpc', [
            DoubleProcedureProvider::class,
        ]);

        $this
            ->postJson('/square-rpc', [
                'method' => 'Double',
                'request' => 5.0,
            ])
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'skir_method_not_found',
                    'message' => 'Skir method [Double] is not registered.',
                ],
            ]);

        $this
            ->postJson('/double-rpc', [
                'method' => 'Double',
                'request' => 5.0,
            ])
            ->assertOk()
            ->assertContent('10');
    }

    #[Test]
    public function it_answers_json_object_requests_in_readable_json(): void
    {
        Route::skirRpc('/readable-rpc', [
            RenameUserProcedureProvider::class,
        ]);

        $this
            ->postJson('/readable-rpc', [
                'method' => 'RenameUser',
                'request' => [
                    'id' => 42,
                    'name' => 'Ruben',
                ],
            ])
            ->assertOk()
            ->assertExactJson([
                'id' => 42,
                'name' => 'Ruben updated',
            ]);
    }

    #[Test]
    public function it_injects_the_primary_skir_context(): void
    {
        app(SkirServer::class)->addMethod(
            new MethodDescriptor('ContextMethod', 2001, Type::float32(), Type::float32()),
            fn (float $value, SkirContext $context): float => $context->method->name === 'ContextMethod' ? $value : 0.0,
        );

        $this
            ->postJson('/skir', [
                'method' => 'ContextMethod',
                'request' => 5.0,
            ])
            ->assertOk()
            ->assertContent('5');
    }

    #[Test]
    public function it_keeps_request_context_as_a_compatibility_type(): void
    {
        $context = new RequestContext(
            request: request(),
            method: new MethodDescriptor('Compat', 2002, Type::float32(), Type::float32()),
        );

        $this->assertInstanceOf(SkirContext::class, $context);
    }

    #[Test]
    public function it_returns_a_package_local_error_for_unknown_methods(): void
    {
        $this
            ->postJson('/skir', [
                'method' => 'Missing',
                'request' => [],
            ])
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'skir_method_not_found',
                    'message' => 'Skir method [Missing] is not registered.',
                ],
            ]);
    }

    #[Test]
    public function it_returns_a_package_local_error_for_invalid_request_envelopes(): void
    {
        $this
            ->postJson('/skir', [
                'request' => 5.0,
            ])
            ->assertUnprocessable()
            ->assertExactJson([
                'error' => [
                    'code' => 'skir_invalid_request',
                    'message' => 'Skir requests must include a [method] field.',
                ],
            ]);
    }
}

final class SquareProcedureProvider implements ProcedureProvider
{
    public function register(SkirServer $server): void
    {
        $server->addMethod(
            new MethodDescriptor('Square', 1001, Type::float32(), Type::float32()),
            fn (float $value): float => $value * $value,
        );
    }
}

final class DoubleProcedureProvider implements ProcedureProvider
{
    public function register(SkirServer $server): void
    {
        $server->addMethod(
            new MethodDescriptor('Double', 1002, Type::float32(), Type::float32()),
            fn (float $value): float => $value * 2,
        );
    }
}

final class RenameUserProcedureProvider implements ProcedureProvider
{
    public function register(SkirServer $server): void
    {
        $server->addMethod(
            new MethodDescriptor('RenameUser', 1003, Type::struct([
                Field::value('id', 0, Type::int32()),
                Field::value('name', 1, Type::string()),
            ]), Type::struct([
                Field::value('id', 0, Type::int32()),
                Field::value('name', 1, Type::string()),
            ])),
            fn (array $user): array => [
                'id' => $user['id'],
                'name' => "{$user['name']} updated",
            ],
        );
    }
}

enum TestSkirMethod implements SkirMethodReference
{
    case Square;
    case Double;
    case RenameUser;

    public function descriptor(): MethodDescriptor
    {
        return match ($this) {
            self::Square => new MethodDescriptor('Square', 1001, Type::float32(), Type::float32()),
            self::Double => new MethodDescriptor('Double', 1002, Type::float32(), Type::float32()),
            self::RenameUser => new MethodDescriptor('RenameUser', 1003, Type::struct([
                Field::value('id', 0, Type::int32()),
                Field::value('name', 1, Type::string()),
            ]), Type::struct([
                Field::value('id', 0, Type::int32()),
                Field::value('name', 1, Type::string()),
            ])),
        };
    }
}

final class SquareController
{
    public function __invoke(float $value, SkirContext $context): float
    {
        return $context->method->name === 'Square' ? $value * $value : 0.0;
    }
}

final class MultiMethodController
{
    #[SkirMethod(TestSkirMethod::Double)]
    public function anyName(float $value): float
    {
        return $value * 2;
    }

    #[SkirMethod(TestSkirMethod::RenameUser)]
    public function rename(RenameUserPayload $payload): RenameUserPayload
    {
        return new RenameUserPayload($payload->id, "{$payload->name} updated");
    }

    public function ignored(float $value): float
    {
        return $value * 100;
    }
}

final readonly class RenameUserPayload
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    /**
     * @param  array{
     *     id: int,
     *     name: string
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self($payload['id'], $payload['name']);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
