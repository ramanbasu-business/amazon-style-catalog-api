<?php

declare(strict_types=1);

namespace Catalog\Http\Handler;

use Catalog\Http\Json;
use Catalog\Support\Clock;
use Catalog\Support\Database;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Liveness plus a datastore check. Returns 503 when the database is unreachable
 * so an orchestrator takes the instance out of rotation instead of sending it
 * traffic it cannot serve.
 */
final class HealthHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $databaseUp = Database::isReachable($this->connection);

        return Json::write($response, [
            'status' => $databaseUp ? 'ok' : 'degraded',
            'checks' => ['database' => $databaseUp ? 'up' : 'down'],
            'time' => $this->clock->now()->format(DATE_ATOM),
        ], $databaseUp ? 200 : 503);
    }
}
