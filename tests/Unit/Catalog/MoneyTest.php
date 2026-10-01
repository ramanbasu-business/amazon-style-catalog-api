<?php

declare(strict_types=1);

namespace Catalog\Tests\Unit\Catalog;

use Catalog\Catalog\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFromDecimalString_WithValidAmount_RoundTrips(): void
    {
        self::assertSame('19.99', Money::fromDecimalString('19.99', 'GBP')->toDecimalString());
        self::assertSame('5.00', Money::fromDecimalString('5', 'GBP')->toDecimalString());
        self::assertSame('5.50', Money::fromDecimalString('5.5', 'GBP')->toDecimalString());
        self::assertSame('0.09', Money::fromDecimalString('0.09', 'GBP')->toDecimalString());
    }

    public function testFromDecimalString_WithValidAmount_StoresMinorUnits(): void
    {
        self::assertSame(1999, Money::fromDecimalString('19.99', 'GBP')->minorUnits);
        self::assertSame(-250, Money::fromDecimalString('-2.50', 'GBP')->minorUnits);
    }

    public function testToDecimalString_WithNegativeAmount_KeepsSign(): void
    {
        self::assertSame('-2.50', Money::fromDecimalString('-2.50', 'GBP')->toDecimalString());
    }

    public function testFromDecimalString_WithThreeDecimalPlaces_Throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimalString('1.005', 'GBP');
    }

    public function testFromDecimalString_WithNonNumericAmount_Throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimalString('19.99 GBP', 'GBP');
    }

    public function testConstruct_WithMalformedCurrency_Throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(100, 'Pounds');
    }
}
