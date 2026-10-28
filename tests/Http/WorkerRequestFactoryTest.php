<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ClientAddress;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\PayloadTooLargeException;
use Zef\Framework\Http\RequestBodyPolicy;
use Zef\Framework\Http\RequestFactory;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\WorkerBody;
use Zef\Framework\Http\WorkerConnection;
use Zef\Framework\Http\WorkerCookies;
use Zef\Framework\Http\WorkerHeaders;
use Zef\Framework\Http\WorkerQuery;
use Zef\Framework\Http\WorkerRequest;
use Zef\Framework\Http\WorkerRequestFactory;
use Zef\Framework\Http\WorkerUri;

/**
 * The worker half of the runtime adapter, on its own terms: one
 * composition every runtime mode funnels into -- the wire shape the
 * glue fills becoming the same ServerRequest the SAPI twin answers,
 * with the bags derived from the wire and the budget judged at the
 * boundary.
 *
 * @internal
 */
#[CoversClass(WorkerRequestFactory::class)]
#[CoversClass(WorkerRequest::class)]
#[CoversClass(WorkerHeaders::class)]
#[CoversClass(WorkerBody::class)]
#[CoversClass(WorkerUri::class)]
#[CoversClass(WorkerQuery::class)]
#[CoversClass(WorkerCookies::class)]
#[CoversClass(WorkerConnection::class)]
#[CoversClass(RequestBodyPolicy::class)]
#[CoversClass(ServerRequest::class)]
#[CoversClass(RequestFactory::class)]
#[CoversClass(ClientAddress::class)]
final class WorkerRequestFactoryTest extends TestCase
{
    public function testTheWireShapeBecomesTheInboundRequest(): void
    {
        $workerRequest = new WorkerRequest(
            'post',
            '/v1/users?page=2&sort=name',
            [
                'Host' => 'api.example.com',
                'Content-Type' => 'application/json',
                'Content-Length' => '17',
                'Cookie' => 'session=abc; theme=dark',
                'X-Request-Id' => 'req-42',
            ],
            '{"name":"Zef"}',
            '1.1',
            '192.0.2.10',
            54123,
        );

        $request = WorkerRequestFactory::fromWorker($workerRequest);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://api.example.com/v1/users?page=2&sort=name', (string) $request->getUri());
        self::assertSame('/v1/users?page=2&sort=name', $request->getRequestTarget());
        self::assertSame(['page' => '2', 'sort' => 'name'], $request->getQueryParams());
        self::assertSame(['session' => 'abc', 'theme' => 'dark'], $request->getCookieParams());
        self::assertSame('{"name":"Zef"}', $request->getBody()->getContents());
        self::assertSame(['REMOTE_ADDR' => '192.0.2.10', 'REMOTE_PORT' => 54123], $request->getServerParams());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('api.example.com', $request->getHeaderLine('Host'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('req-42', $request->getHeaderLine('X-Request-Id'));
    }

    public function testTheParsedBodyStartsNullForEveryContentType(): void
    {
        $workerRequest = new WorkerRequest(
            'POST',
            '/v1/users',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'name=Zef',
        );

        $request = WorkerRequestFactory::fromWorker($workerRequest);

        self::assertNull(
            $request->getParsedBody(),
            'the worker has no engine-decoded bag: the decode belongs downstream',
        );
    }

    public function testTheUploadTreeStartsEmpty(): void
    {
        $request = WorkerRequestFactory::fromWorker(new WorkerRequest('POST', '/v1/uploads'));

        self::assertSame(
            [],
            $request->getUploadedFiles(),
            'a worker protocol that carries parsed uploads widens the payload; until then the tree is empty',
        );
    }

    public function testTheAbsoluteSpellingRidesWithoutTheJoin(): void
    {
        $workerRequest = new WorkerRequest(
            'GET',
            'https://api.example.com:8443/v1/users',
            ['Host' => 'api.example.com'],
        );

        $request = WorkerRequestFactory::fromWorker($workerRequest);

        self::assertSame('https://api.example.com:8443/v1/users', (string) $request->getUri());
    }

    public function testTheDefaultBudgetAnswersWhenNoPolicyIsSupplied(): void
    {
        $workerRequest = new WorkerRequest('POST', '/v1/users', ['Content-Length' => '16'], str_repeat('A', 16));

        $request = WorkerRequestFactory::fromWorker($workerRequest);

        self::assertSame('A', $request->getBody()->read(1), 'the default two-mebibyte posture serves the body');
    }

    public function testTheOverBudgetClaimIsRefusedAtTheBoundary(): void
    {
        $this->expectException(PayloadTooLargeException::class);

        WorkerRequestFactory::fromWorker(
            new WorkerRequest('POST', '/v1/users', ['Content-Length' => '104857600'], 'small'),
            new RequestBodyPolicy(maxBytes: 16),
        );
    }

    public function testTheOverBudgetPayloadIsRefusedAtTheBoundary(): void
    {
        $this->expectException(PayloadTooLargeException::class);

        WorkerRequestFactory::fromWorker(
            new WorkerRequest('POST', '/v1/users', [], str_repeat('A', 17)),
            new RequestBodyPolicy(maxBytes: 16),
        );
    }

    public function testTheBuiltRequestStillAnswersTheIngressGuard(): void
    {
        $workerRequest = new WorkerRequest(
            'GET',
            '/v1/users?page=2',
            ['Host' => 'api.example.com'],
            '',
            '1.1',
            '192.0.2.10',
            54123,
        );

        $guarded = WorkerRequestFactory::fromWorker($workerRequest);

        $guarded = RequestFactory::validateIngress($guarded, ['api.example.com'], [], new RequestBodyPolicy());

        self::assertSame(
            '192.0.2.10',
            ClientAddress::resolve($guarded),
            "the injected REMOTE_ADDR is the address the layer's own downstream reader answers",
        );
    }

    public function testTheIngressGuardStillRefusesAnUntrustedHost(): void
    {
        $workerRequest = new WorkerRequest('GET', '/v1/users', ['Host' => 'evil.example.net']);

        $request = WorkerRequestFactory::fromWorker($workerRequest);

        $this->expectException(HttpException::class);

        RequestFactory::validateIngress($request, ['api.example.com'], [], new RequestBodyPolicy());
    }
}
