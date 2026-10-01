<?php

declare(strict_types=1);

namespace Catalog\Catalog;

use InvalidArgumentException;

/**
 * Money is stored in minor units as an integer. Prices are compared and summed
 * across the catalog, and floats would accumulate rounding error doing that.
 */
final class Money
{
    public function __construct(
        public readonly int $minorUnits,
        public readonly string $currency,
    ) {
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Currency must be a 3-letter ISO code, got: {$currency}");
        }
    }

    public static function fromDecimalString(string $amount, string $currency): self
    {
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException("Price must be a decimal with up to 2 places, got: {$amount}");
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $negative = str_starts_with($whole, '-');
        $minor = (int) ltrim($whole, '-') * 100 + (int) str_pad($fraction, 2, '0');

        return new self($negative ? -$minor : $minor, $currency);
    }

    public function toDecimalString(): string
    {
        $sign = $this->minorUnits < 0 ? '-' : '';
        $abs = abs($this->minorUnits);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }
}
