<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mortgage\Loan;

use App\Domain\Mortgage\Loan\InterestRate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InterestRateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validRates(): iterable
    {
        yield 'округление до 2 знаков' => ['18.555', '18.56'];
        yield 'верхняя граница' => ['30', '30.00'];
        yield 'минимальная ставка' => ['0.01', '0.01'];
    }

    #[DataProvider('validRates')]
    public function testOfRoundsToTwoDecimalPlaces(string $input, string $expected): void
    {
        self::assertSame($expected, InterestRate::of($input)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRates(): iterable
    {
        yield 'ноль' => ['0'];
        yield 'отрицательная' => ['-1'];
        yield 'выше максимума' => ['30.01'];
        yield 'округляется до нуля' => ['0.004'];
        yield 'не число' => ['abc'];
    }

    #[DataProvider('invalidRates')]
    public function testOfRejectsInvalidRate(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);

        InterestRate::of($input);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function monthlyRates(): iterable
    {
        yield '12 % годовых' => ['12', '0.0100000000'];
        yield '18.5 % годовых' => ['18.5', '0.0154166667'];
    }

    #[DataProvider('monthlyRates')]
    public function testGetMonthlyRate(string $annual, string $expected): void
    {
        self::assertSame($expected, (string) InterestRate::of($annual)->getMonthlyRate());
    }
}
