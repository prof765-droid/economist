<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Calculator;

use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Schedule\PaymentSchedule;

/**
 * Доменный сервис: выбирает стратегию по типу платежа и считает график.
 *
 * Стратегии приходят снаружи (в Symfony — !tagged_iterator в services.yaml): сам класс
 * не знает ни про контейнер, ни про то, сколько типов платежей существует.
 */
final readonly class MortgageCalculator
{
    /**
     * @param iterable<PaymentScheduleCalculator> $calculators
     */
    public function __construct(
        private iterable $calculators,
    ) {
    }

    /**
     * @throws \LogicException нет стратегии для типа платежа — ошибка конфигурации, а не ввода (→ 500)
     */
    public function calculate(CreditTerms $terms): PaymentSchedule
    {
        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($terms->getPaymentType())) {
                return $calculator->calculate($terms);
            }
        }

        throw new \LogicException(\sprintf('No payment schedule calculator supports payment type "%s".', $terms->getPaymentType()->value));
    }
}
