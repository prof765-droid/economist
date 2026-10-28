<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use App\Domain\Mortgage\Schedule\PaymentSchedule;

// Деньги — строки: число JSON фронт прочитал бы как float и потерял бы копейки (раздел 1 дизайна).
final readonly class SummaryResponse
{
    public function __construct(
        public string $creditAmount,
        public string $monthlyPayment,
        public string $totalPayment,
        public string $overpayment,
    ) {
    }

    public static function fromSchedule(PaymentSchedule $schedule): self
    {
        return new self(
            $schedule->getCreditAmount()->toString(),
            $schedule->getMonthlyPayment()->toString(),
            $schedule->getTotalPayment()->toString(),
            $schedule->getOverpayment()->toString(),
        );
    }
}
