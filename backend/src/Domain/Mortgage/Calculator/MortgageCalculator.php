<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Calculator;

use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Schedule\PaymentSchedule;

/**
 * Доменный сервис: выбирает стратегию по типу погашения и считает график.
 *
 * Стратегии приходят снаружи (в Symfony — !tagged_iterator в services.yaml): сам класс
 * не знает ни про контейнер, ни про то, сколько типов погашения существует.
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
     * @throws \LogicException нет стратегии для типа погашения — ошибка конфигурации, а не ввода (→ 500)
     */
    public function calculate(CreditTerms $terms): PaymentSchedule
    {
        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($terms->getRepaymentType())) {
                return $calculator->calculate($terms);
            }
        }

        throw new \LogicException(\sprintf('No payment schedule calculator supports repayment type "%s".', $terms->getRepaymentType()->value));
    }
}
