<?php

declare(strict_types=1);

namespace Skir\Server\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Skir\Client\Http\SkirRpcRequest;
use Skir\Runtime\DenseJson;
use Skir\Runtime\EnumValue;
use Skir\Runtime\Field;
use Skir\Runtime\MethodDescriptor;
use Skir\Runtime\Type;
use Skir\Runtime\Variant;
use Skir\Server\SkirServer;
use Skir\Server\Tests\TestCase;

final class ClientServerContractTest extends TestCase
{
    #[Test]
    public function the_client_request_body_is_understood_by_the_server(): void
    {
        $descriptor = $this->profileMethod();
        $request = [
            'id' => 42,
            'tags' => ['admin', 'beta'],
            'status' => EnumValue::wrapper('suspended_reason', 'manual review'),
            'avatar' => "\x01\x02\x03",
            'note' => null,
        ];
        $response = [
            'id' => 42,
            'tags' => ['verified'],
            'status' => EnumValue::constant('active'),
            'avatar' => "\x04\x05\x06",
            'note' => 'updated',
        ];
        $receivedRequest = null;

        app(SkirServer::class)->addMethod(
            $descriptor,
            function (array $decodedRequest) use (&$receivedRequest, $response): array {
                $receivedRequest = $decodedRequest;

                return $response;
            },
        );

        $clientRequest = new SkirRpcRequest($descriptor, $request, '/skir');

        $httpResponse = $this
            ->call('POST', '/skir', content: (string) $clientRequest->body())
            ->assertOk()
            ->assertHeader('content-type', 'application/json');

        $this->assertEquals($request, $receivedRequest);
        $this->assertEquals($response, DenseJson::fromJson($descriptor->responseType, $httpResponse->getContent()));
    }

    private function profileMethod(): MethodDescriptor
    {
        $statusType = Type::enum([
            Variant::constant('active', 1),
            Variant::wrapper('suspended_reason', 2, Type::string()),
        ]);
        $profileType = Type::struct([
            Field::value('id', 0, Type::int32()),
            Field::value('tags', 1, Type::array(Type::string())),
            Field::value('status', 2, $statusType),
            Field::value('avatar', 3, Type::bytes()),
            Field::value('note', 4, Type::optional(Type::string())),
        ]);

        return new MethodDescriptor('UpdateProfile', 7001, $profileType, $profileType);
    }
}
