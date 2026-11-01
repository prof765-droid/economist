<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use App\Domain\Mortgage\Schedule\PaymentSchedule;
use OpenApi\Attributes as OA;

// Деньги — строки: число JSON фронт прочитал бы как float и потерял бы копейки (раздел 1 дизайна).
final readonly class SummaryResponse
{
    public function __construct(
        #[OA\Property(description: 'Сумма кредита: стоимость − взнос', example: '4800000.00')]
        public string $creditAmount,
        #[OA\Property(description: 'Первый платёж (у аннуитета все, кроме последнего, такие же)', example: '75931.04')]
        public string $monthlyPayment,
        #[OA\Property(description: 'Сумма всех платежей', example: '18223439.69')]
        public string $totalPayment,
        #[OA\Property(description: 'Переплата: всего выплачено − сумма кредита', example: '13423439.69')]
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
