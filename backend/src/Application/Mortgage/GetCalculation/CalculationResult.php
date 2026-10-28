<?php

declare(strict_types=1);

namespace App\Application\Mortgage\GetCalculation;

use App\Domain\Mortgage\Calculation\Calculation;
use App\Domain\Mortgage\Schedule\PaymentSchedule;

/**
 * Сохранённый расчёт вместе с пересчитанным графиком — результат GetCalculation.
 *
 * Именованный тип, а не массив [$calculation, $schedule]: вернуть нужно два объекта,
 * и PHPStan знает тип каждого. Внутри — те же доменные объекты, маппинга нет (дизайн 2.2).
 */
final readonly class CalculationResult
{
    public function __construct(
        private Calculation $calculation,
        private PaymentSchedule $schedule,
    ) {
    }

    public function getCalculation(): Calculation
    {
        return $this->calculation;
    }

    public function getSchedule(): PaymentSchedule
    {
        return $this->schedule;
    }
}
