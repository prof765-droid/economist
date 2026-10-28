<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mortgage\Calculator;

use App\Domain\Mortgage\Calculator\AnnuityCalculator;
use App\Domain\Mortgage\Calculator\DifferentiatedCalculator;
use App\Domain\Mortgage\Calculator\MortgageCalculator;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\RepaymentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MortgageCalculatorTest extends TestCase
{
    /**
     * Первый платёж из эталонов 1 000 000 / 12 % / 3 мес.: по нему видно, какая стратегия считала.
     *
     * @return iterable<string, array{RepaymentType, string}>
     */
    public static function firstPayments(): iterable
    {
        yield 'аннуитет' => [RepaymentType::ANNUITY, '340022.11'];
        yield 'дифференцированный' => [RepaymentType::DIFFERENTIATED, '343333.33'];
    }

    #[DataProvider('firstPayments')]
    public function testChoosesStrategyByRepaymentType(RepaymentType $type, string $firstPayment): void
    {
        $calculator = new MortgageCalculator([new AnnuityCalculator(), new DifferentiatedCalculator()]);

        $schedule = $calculator->calculate(self::terms($type));

        self::assertSame($firstPayment, $schedule->getMonthlyPayment()->toString());
    }

    public function testThrowsWhenNoStrategySupportsRepaymentType(): void
    {
        $calculator = new MortgageCalculator([new AnnuityCalculator()]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"differentiated"');

        $calculator->calculate(self::terms(RepaymentType::DIFFERENTIATED));
    }

    private static function terms(RepaymentType $type): CreditTerms
    {
        return CreditTerms::create('1000000.00', '0', '12', 3, $type, new \DateTimeImmutable('2026-10-23'));
    }
}
