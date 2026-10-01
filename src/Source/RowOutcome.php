<?php

declare(strict_types=1);

namespace Catalog\Source;

final class RowOutcome
{
    public function __construct(
        public readonly int $lineNumber,
        public readonly bool $accepted,
        public readonly ?string $errorMessage = null,
    ) {
    }
}
