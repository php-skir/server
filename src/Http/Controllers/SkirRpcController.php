<?php

declare(strict_types=1);

namespace Skir\Server\Http\Controllers;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Routing\Router;
use Skir\Runtime\DenseJson;
use Skir\Runtime\Exceptions\SkirRuntimeException;
use Skir\Runtime\ReadableJson;
use Skir\Server\Exceptions\SkirServerException;
use Skir\Server\Http\Requests\SkirMethodRequestScope;
use Skir\Server\Http\RpcRequest;
use Skir\Server\PreparedProcedure;
use Skir\Server\RegisteredProcedure;
use Skir\Server\RequestContext;
use Skir\Server\Routing\SkirEndpointRegistry;
use Skir\Server\Studio\StudioRenderer;
use Symfony\Component\HttpFoundation\Response;

final readonly class SkirRpcController
{
    public function __construct(
        private SkirEndpointRegistry $endpoints,
        private StudioRenderer $studioRenderer,
        private Container $container,
        private SkirMethodRequestScope $requestScope,
        private Router $router,
    ) {}

    public function __invoke(Request $request): Response
    {
        try {
            $server = $this->endpoints->server($request->route());

            if ($this->isStudioRequest($request)) {
                if (! $this->studioEnabled($request)) {
                    abort(Response::HTTP_NOT_FOUND);
                }

                return response(
                    $this->studioRenderer->render($server, '/'.$request->path()),
                    Response::HTTP_OK,
                )->header('content-type', 'text/html; charset=UTF-8');
            }

            $rpcRequest = RpcRequest::parse($this->rpcBody($request));
            $procedure = $server->procedure($rpcRequest->method);
            $decodedRequest = DenseJson::decode($procedure->descriptor->requestType, $rpcRequest->payload);

            return $this->requestScope->run(
                $request,
                $decodedRequest,
                function (Request $methodRequest, mixed $decodedPayload) use ($rpcRequest, $procedure): Response {
                    $context = new RequestContext($methodRequest, $procedure->descriptor);
                    $prepared = $procedure->prepare($decodedPayload, $context);

                    $response = (new Pipeline($this->container))
                        ->send($methodRequest)
                        ->through($this->procedureMiddleware($prepared))
                        ->then(fn (): Response => $this->invokeAndEncodeResponse(
                            $rpcRequest,
                            $procedure,
                            $prepared,
                        ));

                    return $this->router->prepareResponse($methodRequest, $response);
                },
            );
        } catch (SkirServerException $exception) {
            return $exception->toResponse();
        } catch (SkirRuntimeException $exception) {
            return SkirServerException::invalidRequest($exception->getMessage())->toResponse();
        }
    }

    private function invokeAndEncodeResponse(
        RpcRequest $rpcRequest,
        RegisteredProcedure $procedure,
        PreparedProcedure $prepared,
    ): Response {
        $result = $prepared->invoke();
        $responseType = $procedure->descriptor->responseType;

        try {
            $json = $rpcRequest->readable
                ? ReadableJson::toJson($responseType, $result)
                : DenseJson::toJson($responseType, $result);
        } catch (SkirRuntimeException $exception) {
            throw SkirServerException::invalidResponse($procedure->descriptor->name, $exception->getMessage());
        }

        return response($json, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    /** @return list<Closure|string> */
    private function procedureMiddleware(PreparedProcedure $prepared): array
    {
        if ($this->container->bound('middleware.disable')) {
            if ($this->container->make('middleware.disable') === true) {
                return [];
            }
        }

        return $prepared->middleware;
    }

    private function isStudioRequest(Request $request): bool
    {
        if (! $this->usesQueryString($request)) {
            return false;
        }

        $queryKey = (string) ($request->route()?->defaults['skirStudioQueryKey'] ?? 'studio');

        return $this->rpcBody($request) === '' || $request->query->has($queryKey);
    }

    private function studioEnabled(Request $request): bool
    {
        return ($request->route()?->defaults['skirStudioEnabled'] ?? false) === true;
    }

    private function rpcBody(Request $request): string
    {
        if ($this->usesQueryString($request)) {
            return rawurldecode((string) $request->server->get('QUERY_STRING', ''));
        }

        return $request->getContent();
    }

    private function usesQueryString(Request $request): bool
    {
        if ($request->isMethod('GET')) {
            return true;
        }

        return $request->isMethod('HEAD');
    }
}
