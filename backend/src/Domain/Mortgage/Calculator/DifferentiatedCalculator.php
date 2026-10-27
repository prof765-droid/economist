<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Calculator;

use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\PaymentType;
use App\Domain\Mortgage\Schedule\Payment;
use App\Domain\Mortgage\Schedule\PaymentSchedule;

/**
 * Дифференцированный: основной долг гасится равными частями S / n, проценты — на остаток (дизайн 3.4).
 *
 * Цикл похож на AnnuityCalculator, но общий базовый класс ради ~10 строк не вводим (YAGNI):
 * общее — округление, min, вычитание — уже в Money, дата — в CreditTerms::getPaymentDate().
 */
final readonly class DifferentiatedCalculator implements PaymentScheduleCalculator
{
    public function supports(PaymentType $type): bool
    {
        return PaymentType::DIFFERENTIATED === $type;
    }

    public function calculate(CreditTerms $terms): PaymentSchedule
    {
        $creditAmount = $terms->getCreditAmount();
        $monthlyRate = $terms->getAnnualRate()->getMonthlyRate();
        $termMonths = $terms->getTermMonths();
        // 1000000.00 / 3 = 333333.33 — потерянная копейка уйдёт в последний платёж.
        $plannedPrincipal = $creditAmount->dividedBy($termMonths);

        $payments = [];
        $balance = $creditAmount;

        for ($number = 1; $number <= $termMonths && !$balance->isZero(); ++$number) {
            $interest = $balance->multipliedBy($monthlyRate);
            // min — страховка на случай ослабления лимитов (дизайн 3.4), при текущих не срабатывает.
            // В последний месяц гасим весь остаток — туда уходит разница от округления S / n.
            $principal = $number === $termMonths
                ? $balance
                : $plannedPrincipal->min($balance);
            $balance = $balance->minus($principal);

            $payments[] = new Payment(
                $number,
                $terms->getPaymentDate($number),
                $principal->plus($interest),
                $interest,
                $principal,
                $balance,
            );
        }

        return new PaymentSchedule($creditAmount, $payments);
    }
}
