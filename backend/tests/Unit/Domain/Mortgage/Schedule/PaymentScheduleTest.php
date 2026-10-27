<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mortgage\Schedule;

use App\Domain\Mortgage\Money;
use App\Domain\Mortgage\Schedule\Payment;
use App\Domain\Mortgage\Schedule\PaymentSchedule;
use PHPUnit\Framework\TestCase;

/**
 * График собираем вручную из эталона «аннуитет 600000.00 / 12 % / 3 мес.» — калькуляторов ещё нет (фича 7).
 */
final class PaymentScheduleTest extends TestCase
{
    public function testCalculatesSummary(): void
    {
        $schedule = new PaymentSchedule(Money::of('600000.00'), self::referencePayments());

        self::assertSame('600000.00', $schedule->getCreditAmount()->toString());
        self::assertCount(3, $schedule->getPayments());
        self::assertSame('204013.27', $schedule->getMonthlyPayment()->toString());
        self::assertSame('612039.80', $schedule->getTotalPayment()->toString());
        self::assertSame('12039.80', $schedule->getOverpayment()->toString());
    }

    public function testOverpaymentEqualsSumOfInterest(): void
    {
        $payments = self::referencePayments();
        $interestSum = array_reduce(
            $payments,
            static fn (Money $sum, Payment $payment): Money => $sum->plus($payment->getInterest()),
            Money::zero(),
        );

        $schedule = new PaymentSchedule(Money::of('600000.00'), $payments);

        self::assertTrue($interestSum->equals($schedule->getOverpayment()));
    }

    public function testRejectsEmptySchedule(): void
    {
        $this->expectException(\LogicException::class);

        new PaymentSchedule(Money::of('600000.00'), []);
    }

    public function testRejectsPrincipalSumNotMatchingCreditAmount(): void
    {
        [$first, , $last] = self::referencePayments();
        // Одна копейка долга «потерялась»: остаток при этом по-прежнему 0.00.
        $payments = [$first, self::payment(2, '204013.27', '4019.87', '199993.39', '201993.33'), $last];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Sum of principal');

        new PaymentSchedule(Money::of('600000.00'), $payments);
    }

    public function testRejectsNonZeroLastBalance(): void
    {
        [$first, $second] = self::referencePayments();
        $payments = [$first, $second, self::payment(3, '204013.26', '2019.93', '201993.33', '0.01')];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Balance after the last payment');

        new PaymentSchedule(Money::of('600000.00'), $payments);
    }

    /**
     * @return non-empty-list<Payment>
     */
    private static function referencePayments(): array
    {
        return [
            self::payment(1, '204013.27', '6000.00', '198013.27', '401986.73'),
            self::payment(2, '204013.27', '4019.87', '199993.40', '201993.33'),
            self::payment(3, '204013.26', '2019.93', '201993.33', '0.00'),
        ];
    }

    private static function payment(int $number, string $amount, string $interest, string $principal, string $balance): Payment
    {
        return new Payment(
            $number,
            // 23-е есть в каждом месяце — простой modify() тут не «съезжает» (см. CreditTerms::getPaymentDate).
            new \DateTimeImmutable('2026-10-23')->modify("+{$number} month"),
            Money::of($amount),
            Money::of($interest),
            Money::of($principal),
            Money::of($balance),
        );
    }
}
