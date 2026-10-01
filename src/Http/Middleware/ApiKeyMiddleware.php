<?php

declare(strict_types=1);

namespace Catalog\Http\Middleware;

use Catalog\Http\ApiProblem;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Single service API key. The comparison is constant-time so a caller cannot
 * recover the key by timing failed requests.
 */
final class ApiKeyMiddleware implements MiddlewareInterface
{
    /** @param list<string> $openPaths paths served without a key, e.g. /health */
    public function __construct(
        private readonly string $expectedKey,
        private readonly array $openPaths = ['/health'],
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (in_array($path, $this->openPaths, true)) {
            return $handler->handle($request);
        }

        $presented = $request->getHeaderLine('X-Api-Key');
        if ($presented === '' || !hash_equals($this->expectedKey, $presented)) {
            throw ApiProblem::unauthorized();
        }

        return $handler->handle($request);
    }
}
