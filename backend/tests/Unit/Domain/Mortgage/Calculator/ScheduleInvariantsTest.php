<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mortgage\Calculator;

use App\Domain\Mortgage\Calculator\AnnuityCalculator;
use App\Domain\Mortgage\Calculator\DifferentiatedCalculator;
use App\Domain\Mortgage\Calculator\MortgageCalculator;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\RepaymentType;
use App\Domain\Mortgage\Money;
use App\Domain\Mortgage\Schedule\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Свойства, которые должны выполняться для любого графика (дизайн 6.1).
 *
 * Эталоны ловят неверную формулу, инварианты — то, что алгоритм не ломается на других данных:
 * на длинных сроках и краях лимитов, где копится ошибка округления.
 */
final class ScheduleInvariantsTest extends TestCase
{
    /**
     * Все сочетания: 2 типа × 5 сроков × 4 ставки × 3 суммы = 120 графиков.
     *
     * @return iterable<string, array{RepaymentType, int, string, string}>
     */
    public static function terms(): iterable
    {
        foreach (RepaymentType::cases() as $type) {
            foreach ([1, 2, 12, 240, 360] as $termMonths) {
                foreach (['0.01', '6', '18.5', '30'] as $rate) {
                    foreach (['500000.00', '1234567.89', '4000000.00'] as $creditAmount) {
                        yield "{$type->value}, {$termMonths} мес., {$rate} %, {$creditAmount}" => [$type, $termMonths, $rate, $creditAmount];
                    }
                }
            }
        }
    }

    #[DataProvider('terms')]
    public function testScheduleInvariants(RepaymentType $type, int $termMonths, string $rate, string $creditAmount): void
    {
        // Начало 31-го — заодно проверяем, что даты берутся из CreditTerms (короткие месяцы, 29 фев.).
        $terms = CreditTerms::create($creditAmount, '0', $rate, $termMonths, $type, new \DateTimeImmutable('2027-01-31'));
        $calculator = new MortgageCalculator([new AnnuityCalculator(), new DifferentiatedCalculator()]);

        $schedule = $calculator->calculate($terms);
        $payments = $schedule->getPayments();

        self::assertLessThanOrEqual($termMonths, \count($payments));
        self::assertSame($creditAmount, self::sum($payments, static fn (Payment $p): Money => $p->getPrincipal())->toString());
        self::assertSame('0.00', $payments[array_key_last($payments)]->getBalance()->toString());
        self::assertSame(
            self::sum($payments, static fn (Payment $p): Money => $p->getInterest())->toString(),
            $schedule->getOverpayment()->toString(),
        );

        foreach ($payments as $index => $payment) {
            self::assertSame($index + 1, $payment->getNumber());
            self::assertEquals($terms->getPaymentDate($index + 1), $payment->getDate());
            self::assertTrue($payment->getAmount()->equals($payment->getInterest()->plus($payment->getPrincipal())));
        }

        // Последний платёж — корректирующий, поэтому сравниваем только остальные.
        $regular = \array_slice($payments, 0, -1);

        if (RepaymentType::ANNUITY === $type) {
            foreach ($regular as $payment) {
                self::assertTrue($payment->getAmount()->equals($schedule->getMonthlyPayment()), "Платёж № {$payment->getNumber()} отличается от первого");
            }
        } else {
            // Не растут, кроме последнего: в него уходит разница от округления S / n,
            // и при малой ставке он бывает больше предпоследнего (дизайн 6.1).
            for ($i = 1, $count = \count($regular); $i < $count; ++$i) {
                self::assertFalse(
                    $regular[$i]->getAmount()->isGreaterThan($regular[$i - 1]->getAmount()),
                    "Платёж № {$regular[$i]->getNumber()} больше предыдущего",
                );
            }
        }
    }

    /**
     * @param list<Payment>            $payments
     * @param \Closure(Payment): Money $field
     */
    private static function sum(array $payments, \Closure $field): Money
    {
        return array_reduce(
            $payments,
            static fn (Money $sum, Payment $payment): Money => $sum->plus($field($payment)),
            Money::zero(),
        );
    }
}
