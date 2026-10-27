<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mortgage\Calculator;

use App\Domain\Mortgage\Calculator\AnnuityCalculator;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\PaymentType;
use App\Domain\Mortgage\Schedule\Payment;
use App\Domain\Mortgage\Schedule\PaymentSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Эталоны посчитаны независимо от этого кода (Python Decimal по тем же правилам округления),
 * регулярный платёж сверен с Excel/LibreOffice: =ROUND(PMT(i; n; -S); 2).
 */
final class AnnuityCalculatorTest extends TestCase
{
    public function testSupportsOnlyAnnuity(): void
    {
        $calculator = new AnnuityCalculator();

        self::assertTrue($calculator->supports(PaymentType::ANNUITY));
        self::assertFalse($calculator->supports(PaymentType::DIFFERENTIATED));
    }

    /**
     * S = 600000.00, 12 % годовых → i = 0.01, n = 3.
     *   q = 1.01³ = 1.030301
     *   A = 600000 · 0.01 · 1.030301 / 0.030301 = 204013.2669… → 204013.27
     * 1: проценты 600000.00 · 0.01 = 6000.00;  долг 204013.27 − 6000.00 = 198013.27;  остаток 401986.73
     * 2: проценты 401986.73 · 0.01 = 4019.8673 → 4019.87;  долг 199993.40;  остаток 201993.33
     * 3 (последний): проценты 2019.9333 → 2019.93;  долг = весь остаток 201993.33;  платёж 204013.26.
     */
    public function testShortScheduleMatchesHandCalculation(): void
    {
        $schedule = self::calculate('600000.00', '12', 3);

        self::assertSame([
            ['204013.27', '6000.00', '198013.27', '401986.73'],
            ['204013.27', '4019.87', '199993.40', '201993.33'],
            ['204013.26', '2019.93', '201993.33', '0.00'],
        ], self::rows($schedule));
        self::assertSame('612039.80', $schedule->getTotalPayment()->toString());
    }

    /**
     * n = 1: A = S · i · (1 + i) / i = S · (1 + i) = 500000.00 · 1.01 = 505000.00 — весь долг и проценты за месяц.
     */
    public function testOneMonthSchedule(): void
    {
        $schedule = self::calculate('500000.00', '12', 1);

        self::assertSame([['505000.00', '5000.00', '500000.00', '0.00']], self::rows($schedule));
    }

    /**
     * @return iterable<string, array{string, string, int, string, string, string|null}>
     */
    public static function referencePayments(): iterable
    {
        // сумма кредита, ставка, срок, регулярный платёж (= PMT), последний, итого (null — не сверяем)
        yield '4 млн, 18.5 %, 20 лет' => ['4000000.00', '18.5', 240, '63275.86', '63284.30', '15186214.84'];
        yield '4 млн, 18.5 %, 30 лет' => ['4000000.00', '18.5', 360, '61917.78', '61936.98', '22290420.00'];
        yield '1 млн, 6 %, 30 лет' => ['1000000.00', '6', 360, '5995.51', '5991.01', null];
        yield 'край лимитов: 500 тыс., 30 %, 30 лет' => ['500000.00', '30', 360, '12501.72', '13484.64', '4501602.12'];
    }

    #[DataProvider('referencePayments')]
    public function testLongScheduleMatchesReference(
        string $creditAmount,
        string $rate,
        int $termMonths,
        string $regular,
        string $last,
        ?string $total,
    ): void {
        $schedule = self::calculate($creditAmount, $rate, $termMonths);
        $payments = $schedule->getPayments();

        self::assertCount($termMonths, $payments);
        self::assertSame($regular, $schedule->getMonthlyPayment()->toString());
        self::assertSame($last, $payments[array_key_last($payments)]->getAmount()->toString());

        if (null !== $total) {
            self::assertSame($total, $schedule->getTotalPayment()->toString());
        }
    }

    private static function calculate(string $creditAmount, string $rate, int $termMonths): PaymentSchedule
    {
        // Взнос 0 → сумма кредита = стоимости, так эталон читается напрямую.
        $terms = CreditTerms::create($creditAmount, '0', $rate, $termMonths, PaymentType::ANNUITY, new \DateTimeImmutable('2026-10-23'));

        return new AnnuityCalculator()->calculate($terms);
    }

    /**
     * @return list<array{string, string, string, string}> (платёж, проценты, долг, остаток)
     */
    private static function rows(PaymentSchedule $schedule): array
    {
        return array_map(
            static fn (Payment $payment): array => [
                $payment->getAmount()->toString(),
                $payment->getInterest()->toString(),
                $payment->getPrincipal()->toString(),
                $payment->getBalance()->toString(),
            ],
            $schedule->getPayments(),
        );
    }
}
