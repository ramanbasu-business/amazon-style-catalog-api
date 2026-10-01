<?php

declare(strict_types=1);

namespace Catalog\Source;

/**
 * The source refused the request because of its own rate limit. Always
 * retryable, and carries the source's suggested wait where one is given.
 */
final class ThrottledException extends SourceException
{
    public function __construct(string $message, public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message, retryable: true);
    }
}
