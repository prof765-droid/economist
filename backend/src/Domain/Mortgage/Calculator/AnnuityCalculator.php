<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Calculator;

use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\RepaymentType;
use App\Domain\Mortgage\Money;
use App\Domain\Mortgage\Schedule\Payment;
use App\Domain\Mortgage\Schedule\PaymentSchedule;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Аннуитет: платёж одинаковый каждый месяц, кроме последнего — он корректирующий (дизайн 3.4).
 */
final readonly class AnnuityCalculator implements PaymentScheduleCalculator
{
    public function supports(RepaymentType $type): bool
    {
        return RepaymentType::ANNUITY === $type;
    }

    public function calculate(CreditTerms $terms): PaymentSchedule
    {
        $creditAmount = $terms->getCreditAmount();
        $monthlyRate = $terms->getAnnualRate()->getMonthlyRate();
        $termMonths = $terms->getTermMonths();
        $annuity = self::annuity($creditAmount, $monthlyRate, $termMonths);

        $payments = [];
        $balance = $creditAmount;

        for ($number = 1; $number <= $termMonths && !$balance->isZero(); ++$number) {
            // Проценты — от остатка до платежа, округляются до копейки каждый месяц (multipliedBy округляет).
            $interest = $balance->multipliedBy($monthlyRate);
            // min — страховка на случай ослабления лимитов (дизайн 3.4): если ошибка округления
            // накопилась в сторону переплаты, долг кончится раньше срока, а не уйдёт в минус.
            // При текущих лимитах не срабатывает, поэтому тестом не покрыт.
            // В последний месяц гасим весь остаток — сюда уходит накопленная разница от округлений.
            $principal = $number === $termMonths
                ? $balance
                : $annuity->minus($interest)->min($balance);
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

    /**
     * A = S · i · q / (q − 1), где q = (1 + i)^n.
     *
     * Это та же формула S · i / (1 − (1 + i)^−n), домноженная на q: BigDecimal::power()
     * не принимает отрицательную степень. Делим сразу до 2 знаков — одно округление точного
     * частного, без промежуточного (иначе возможна ошибка двойного округления на 0.005).
     *
     * @param int<CreditTerms::MIN_TERM_MONTHS, CreditTerms::MAX_TERM_MONTHS> $termMonths
     */
    private static function annuity(Money $creditAmount, BigDecimal $monthlyRate, int $termMonths): Money
    {
        $q = $monthlyRate->plus(1)->power($termMonths);

        return Money::of(
            $creditAmount->getValue()
                ->multipliedBy($monthlyRate)
                ->multipliedBy($q)
                ->dividedBy($q->minus(1), 2, RoundingMode::HalfUp),
        );
    }
}
