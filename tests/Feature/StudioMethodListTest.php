<?php

declare(strict_types=1);

namespace Skir\Server\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Skir\Runtime\Field;
use Skir\Runtime\MethodDescriptor;
use Skir\Runtime\Type;
use Skir\Runtime\Variant;
use Skir\Server\ProcedureRegistry;
use Skir\Server\SkirServer;
use Skir\Server\Studio\MethodList;
use Skir\Server\Tests\TestCase;

final class StudioMethodListTest extends TestCase
{
    #[Test]
    public function it_lists_no_methods_for_an_empty_endpoint(): void
    {
        Route::skirRpc('/empty-list')->studio();

        $this
            ->get('/empty-list?list')
            ->assertOk()
            ->assertExactJson(['methods' => []]);
    }

    #[Test]
    public function the_method_list_is_hidden_when_studio_is_disabled(): void
    {
        Route::skirRpc('/hidden-list')->studio(false);

        $this
            ->get('/hidden-list?list')
            ->assertNotFound();
    }

    #[Test]
    public function it_describes_methods_as_skir_type_descriptors(): void
    {
        $server = new SkirServer(new ProcedureRegistry);
        $server->addMethod($this->inviteDescriptor(), fn (array $request): array => $request);

        $this->assertSame([
            'methods' => [[
                'method' => 'Invite',
                'number' => 7001,
                'request' => [
                    'type' => ['kind' => 'record', 'value' => 'skir:InviteRequest'],
                    'records' => [
                        [
                            'kind' => 'struct',
                            'id' => 'skir:InviteRequest',
                            'fields' => [
                                [
                                    'name' => 'email',
                                    'number' => 0,
                                    'type' => ['kind' => 'primitive', 'value' => 'string'],
                                ],
                                [
                                    'name' => 'account_type',
                                    'number' => 2,
                                    'type' => ['kind' => 'record', 'value' => 'skir:AccountType'],
                                ],
                                [
                                    'name' => 'details',
                                    'number' => 3,
                                    'type' => [
                                        'kind' => 'optional',
                                        'value' => [
                                            'kind' => 'array',
                                            'value' => ['item' => ['kind' => 'record', 'value' => 'skir:Detail']],
                                        ],
                                    ],
                                ],
                            ],
                            'removed_numbers' => [1],
                        ],
                        [
                            'kind' => 'enum',
                            'id' => 'skir:AccountType',
                            'variants' => [
                                ['name' => 'athlete', 'number' => 1],
                                [
                                    'name' => 'coach',
                                    'number' => 2,
                                    'type' => ['kind' => 'primitive', 'value' => 'int32'],
                                ],
                            ],
                        ],
                        [
                            'kind' => 'struct',
                            'id' => 'skir:Detail',
                            'fields' => [
                                [
                                    'name' => 'label',
                                    'number' => 0,
                                    'type' => ['kind' => 'primitive', 'value' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'response' => [
                    'type' => [
                        'kind' => 'optional',
                        'value' => ['kind' => 'primitive', 'value' => 'bytes'],
                    ],
                    'records' => [],
                ],
            ]],
        ], (new MethodList)->toArray($server));
    }

    #[Test]
    public function records_with_the_same_shape_share_one_definition_and_clashing_names_are_numbered(): void
    {
        $detail = Type::struct([Field::value('label', 0, Type::string())]);
        $otherDetail = Type::struct([Field::value('value', 0, Type::int32())]);

        $server = new SkirServer(new ProcedureRegistry);
        $server->addMethod(
            new MethodDescriptor(
                'Compare',
                7002,
                Type::struct([
                    Field::value('detail', 0, $detail),
                    Field::value('previous', 1, $detail),
                    Field::value('details', 2, Type::array($otherDetail)),
                ]),
                Type::bool(),
            ),
            fn (array $request): bool => true,
        );

        $request = (new MethodList)->toArray($server)['methods'][0]['request'];

        $this->assertSame(
            ['skir:CompareRequest', 'skir:Detail', 'skir:Detail2'],
            array_column($request['records'], 'id'),
        );
        $this->assertSame(
            ['skir:Detail', 'skir:Detail', 'skir:Detail2'],
            [
                $request['records'][0]['fields'][0]['type']['value'],
                $request['records'][0]['fields'][1]['type']['value'],
                $request['records'][0]['fields'][2]['type']['value']['item']['value'],
            ],
        );
    }

    #[Test]
    public function it_uses_the_schema_names_and_doc_comments_when_types_carry_them(): void
    {
        $status = Type::enum(
            [Variant::constant('pending', 1, doc: 'Not answered yet.'), Variant::constant('accepted', 2)],
            recordId: 'invitations/invitations.skir:Invitation.Status',
            doc: 'Where an invitation stands.',
        );
        $invitation = Type::struct(
            [
                Field::value('status', 0, $status, doc: 'Changes when the invitee answers.'),
                Field::value('previous_status', 1, $status),
            ],
            recordId: 'invitations/invitations.skir:Invitation',
            doc: 'One invitation.',
        );

        $server = new SkirServer(new ProcedureRegistry);
        $server->addMethod(
            new MethodDescriptor('GetInvitation', 7003, Type::string(), $invitation, doc: 'Looks up an invitation.'),
            fn (string $request): array => [],
        );

        $method = (new MethodList)->toArray($server)['methods'][0];

        $this->assertSame('Looks up an invitation.', $method['doc']);
        $this->assertSame([
            'type' => ['kind' => 'record', 'value' => 'invitations/invitations.skir:Invitation'],
            'records' => [
                [
                    'kind' => 'struct',
                    'id' => 'invitations/invitations.skir:Invitation',
                    'fields' => [
                        [
                            'name' => 'status',
                            'number' => 0,
                            'type' => ['kind' => 'record', 'value' => 'invitations/invitations.skir:Invitation.Status'],
                            'doc' => 'Changes when the invitee answers.',
                        ],
                        [
                            'name' => 'previous_status',
                            'number' => 1,
                            'type' => ['kind' => 'record', 'value' => 'invitations/invitations.skir:Invitation.Status'],
                        ],
                    ],
                    'doc' => 'One invitation.',
                ],
                [
                    'kind' => 'enum',
                    'id' => 'invitations/invitations.skir:Invitation.Status',
                    'variants' => [
                        ['name' => 'pending', 'number' => 1, 'doc' => 'Not answered yet.'],
                        ['name' => 'accepted', 'number' => 2],
                    ],
                    'doc' => 'Where an invitation stands.',
                ],
            ],
        ], $method['response']);
    }

    private function inviteDescriptor(): MethodDescriptor
    {
        return new MethodDescriptor(
            'Invite',
            7001,
            Type::struct([
                Field::value('email', 0, Type::string()),
                Field::removed(1),
                Field::value('account_type', 2, Type::enum([
                    Variant::constant('athlete', 1),
                    Variant::wrapper('coach', 2, Type::int32()),
                ])),
                Field::value('details', 3, Type::optional(Type::array(Type::struct([
                    Field::value('label', 0, Type::string()),
                ])))),
            ]),
            Type::optional(Type::bytes()),
        );
    }
}
