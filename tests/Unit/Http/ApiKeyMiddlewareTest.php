<?php

declare(strict_types=1);

namespace Catalog\Tests\Unit\Http;

use Catalog\Tests\Support\AppTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;

final class ApiKeyMiddlewareTest extends AppTestCase
{
    private function withReachableDatabase(): void
    {
        $result = $this->createStub(Result::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->willReturn($result);

        $this->override(Connection::class, $connection);
    }

    public function testHealth_WithoutApiKey_IsServed(): void
    {
        $this->withReachableDatabase();

        $response = $this->request('GET', '/health', headers: []);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $this->decode($response)['status']);
    }

    public function testProtectedPath_WithoutApiKey_Returns401(): void
    {
        $response = $this->request('GET', '/v1/products/X1', headers: []);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));

        $body = $this->decode($response);
        self::assertSame('Unauthorized', $body['title']);
        self::assertSame(401, $body['status']);
    }

    public function testProtectedPath_WithWrongApiKey_Returns401(): void
    {
        $response = $this->request('GET', '/v1/products/X1', headers: ['X-Api-Key' => 'not-the-key']);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testUnknownRoute_WithValidApiKey_Returns404Problem(): void
    {
        // Authentication runs before routing, so a 404 here proves the key was
        // accepted rather than the route merely being absent.
        $response = $this->request('GET', '/v1/nothing-here');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $this->decode($response)['title']);
    }

    public function testAnyResponse_Always_CarriesSecurityHeaders(): void
    {
        $response = $this->request('GET', '/v1/nothing-here');

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testHealth_WhenDatabaseUnreachable_ReportsDegraded(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->willThrowException(new \RuntimeException('connection refused'));
        $this->override(Connection::class, $connection);

        $response = $this->request('GET', '/health', headers: []);

        self::assertSame(503, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame('degraded', $body['status']);
        self::assertSame('down', $body['checks']['database']);
    }
}
