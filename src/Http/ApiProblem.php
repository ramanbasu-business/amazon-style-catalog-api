<?php

declare(strict_types=1);

namespace Catalog\Http;

use RuntimeException;

/**
 * An error the client is allowed to see, rendered as RFC 9457 problem details.
 * Anything not thrown as an ApiProblem becomes a generic 500, so internal
 * messages never leak through the API.
 */
class ApiProblem extends RuntimeException
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        private readonly int $status,
        private readonly string $title,
        string $detail = '',
        private readonly array $extra = [],
    ) {
        parent::__construct($detail);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function detail(): string
    {
        return $this->getMessage();
    }

    /** @return array<string, mixed> */
    public function extra(): array
    {
        return $this->extra;
    }

    public static function notFound(string $detail): self
    {
        return new self(404, 'Not Found', $detail);
    }

    public static function unauthorized(string $detail = 'A valid X-Api-Key header is required.'): self
    {
        return new self(401, 'Unauthorized', $detail);
    }

    /** @param array<string, string> $errors field name => message */
    public static function validation(array $errors): self
    {
        return new self(422, 'Unprocessable Entity', 'One or more fields are invalid.', ['errors' => $errors]);
    }

    public static function conflict(string $detail): self
    {
        return new self(409, 'Conflict', $detail);
    }

    public static function sourceUnavailable(string $detail): self
    {
        return new self(503, 'Service Unavailable', $detail);
    }
}
