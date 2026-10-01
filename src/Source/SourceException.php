<?php

declare(strict_types=1);

namespace Catalog\Source;

use RuntimeException;

/**
 * A source failure. `retryable` decides whether the worker backs off and tries
 * again or marks the job permanently failed: a timeout is worth retrying, a
 * rejected payload never will be.
 */
class SourceException extends RuntimeException
{
    public function __construct(string $message, private readonly bool $retryable = false)
    {
        parent::__construct($message);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public static function transient(string $message): self
    {
        return new self($message, true);
    }

    public static function permanent(string $message): self
    {
        return new self($message, false);
    }
}
