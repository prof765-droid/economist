<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mortgage\Calculator;

use App\Domain\Mortgage\Calculator\DifferentiatedCalculator;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\RepaymentType;
use App\Domain\Mortgage\Schedule\Payment;
use PHPUnit\Framework\TestCase;

final class DifferentiatedCalculatorTest extends TestCase
{
    public function testSupportsOnlyDifferentiated(): void
    {
        $calculator = new DifferentiatedCalculator();

        self::assertTrue($calculator->supports(RepaymentType::DIFFERENTIATED));
        self::assertFalse($calculator->supports(RepaymentType::ANNUITY));
    }

    /**
     * S = 1000000.00, 12 % годовых → i = 0.01, n = 3.
     *   плановый долг S / n = 333333.333… → 333333.33 (копейка потерялась — уйдёт в последний платёж)
     * 1: проценты 1000000.00 · 0.01 = 10000.00;  долг 333333.33;  платёж 343333.33;  остаток 666666.67
     * 2: проценты 666666.67 · 0.01 = 6666.6667 → 6666.67;  долг 333333.33;  платёж 340000.00;  остаток 333333.34
     * 3 (последний): проценты 3333.3334 → 3333.33;  долг = весь остаток 333333.34;  платёж 336666.67.
     */
    public function testShortScheduleMatchesHandCalculation(): void
    {
        $terms = CreditTerms::create('1000000.00', '0', '12', 3, RepaymentType::DIFFERENTIATED, new \DateTimeImmutable('2026-10-23'));

        $schedule = new DifferentiatedCalculator()->calculate($terms);

        self::assertSame([
            ['343333.33', '10000.00', '333333.33', '666666.67'],
            ['340000.00', '6666.67', '333333.33', '333333.34'],
            ['336666.67', '3333.33', '333333.34', '0.00'],
        ], array_map(
            static fn (Payment $payment): array => [
                $payment->getAmount()->toString(),
                $payment->getInterest()->toString(),
                $payment->getPrincipal()->toString(),
                $payment->getBalance()->toString(),
            ],
            $schedule->getPayments(),
        ));
        self::assertSame('1020000.00', $schedule->getTotalPayment()->toString());
    }
}
