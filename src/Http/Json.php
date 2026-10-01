<?php

declare(strict_types=1);

namespace Catalog\Http;

use Psr\Http\Message\ResponseInterface;

final class Json
{
    /** @param array<string, mixed>|list<mixed> $data */
    public static function write(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $response->getBody()->write($body);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
