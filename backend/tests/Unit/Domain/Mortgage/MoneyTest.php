<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mortgage;

use App\Domain\Mortgage\Money;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validAmounts(): iterable
    {
        yield 'половина копейки — вверх' => ['1.005', '1.01'];
        yield 'меньше половины — вниз' => ['1.004', '1.00'];
        yield 'ноль' => ['0', '0.00'];
        yield 'целое' => ['3500000', '3500000.00'];
    }

    #[DataProvider('validAmounts')]
    public function testOfRoundsToTwoDecimalPlaces(string $input, string $expected): void
    {
        self::assertSame($expected, Money::of($input)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'отрицательная' => ['-0.01'];
        yield 'не число' => ['abc'];
        yield 'пустая строка' => [''];
    }

    #[DataProvider('invalidAmounts')]
    public function testOfRejectsInvalidAmount(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::of($input);
    }

    public function testOfKeepsMathExceptionAsPrevious(): void
    {
        try {
            Money::of('abc');
            self::fail('Expected \InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertInstanceOf(MathException::class, $e->getPrevious());
        }
    }

    public function testZero(): void
    {
        self::assertSame('0.00', Money::zero()->toString());
        self::assertTrue(Money::zero()->isZero());
    }

    public function testPlusAndMinus(): void
    {
        $a = Money::of('100.50');
        $b = Money::of('0.75');

        self::assertSame('101.25', $a->plus($b)->toString());
        self::assertSame('99.75', $a->minus($b)->toString());
        self::assertTrue($a->minus($a)->isZero());
    }

    public function testMinusBelowZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::of('1.00')->minus(Money::of('1.01'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function multiplications(): iterable
    {
        // 1000.00 × 0.0154166667 = 15.416666670
        yield 'округление вверх' => ['1000.00', '0.0154166667', '15.42'];
        // 3500000.00 × 0.0154166667 = 53958.33345
        yield 'округление вниз' => ['3500000.00', '0.0154166667', '53958.33'];
    }

    #[DataProvider('multiplications')]
    public function testMultipliedByRoundsHalfUp(string $amount, string $factor, string $expected): void
    {
        self::assertSame($expected, Money::of($amount)->multipliedBy(BigDecimal::of($factor))->toString());
    }

    public function testDividedByRoundsHalfUp(): void
    {
        self::assertSame('333.33', Money::of('1000.00')->dividedBy(3)->toString());
        self::assertSame('666.67', Money::of('2000.00')->dividedBy(3)->toString());
    }

    public function testComparisons(): void
    {
        $small = Money::of('10.00');
        $big = Money::of('20.00');

        self::assertTrue($big->isGreaterThan($small));
        self::assertFalse($small->isGreaterThan($big));
        self::assertFalse($small->isGreaterThan(Money::of('10')));

        self::assertTrue($big->isGreaterThanOrEqualTo($small));
        self::assertTrue($small->isGreaterThanOrEqualTo(Money::of('10')));
        self::assertFalse($small->isGreaterThanOrEqualTo($big));

        self::assertTrue($small->equals(Money::of('10')));
        self::assertFalse($small->equals($big));
    }

    public function testMinReturnsSmaller(): void
    {
        $small = Money::of('10.00');
        $big = Money::of('20.00');

        self::assertSame($small, $small->min($big));
        self::assertSame($small, $big->min($small));
    }

    public function testGetAmountReturnsBigDecimalWithScale2(): void
    {
        $amount = Money::of('42')->getAmount();

        self::assertSame(2, $amount->getScale());
        self::assertSame('42.00', (string) $amount);
    }

    public function testOperationsDoNotChangeOriginal(): void
    {
        $money = Money::of('100.00');

        $money->plus(Money::of('1.00'));
        $money->minus(Money::of('1.00'));
        $money->multipliedBy(BigDecimal::of('2'));
        $money->dividedBy(4);

        self::assertSame('100.00', $money->toString());
    }
}
