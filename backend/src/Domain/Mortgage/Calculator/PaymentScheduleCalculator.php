<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Calculator;

use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\RepaymentType;
use App\Domain\Mortgage\Schedule\PaymentSchedule;

/**
 * Стратегия расчёта графика для одного типа погашения.
 *
 * Новый тип погашения = новый класс с этим интерфейсом: services.yaml пометит его тегом,
 * и MortgageCalculator получит его сам — существующий код не меняется (Open/Closed).
 */
interface PaymentScheduleCalculator
{
    public function supports(RepaymentType $type): bool;

    public function calculate(CreditTerms $terms): PaymentSchedule;
}
