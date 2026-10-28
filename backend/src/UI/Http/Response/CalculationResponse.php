<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use App\Domain\Mortgage\Schedule\PaymentSchedule;

/**
 * Тело ответа с расчётом (дизайн 4.3).
 *
 * id и parameters есть только у сохранённого расчёта (GET, фича 12); у calculate они null
 * и не попадают в JSON — контроллер сериализует с SKIP_NULL_VALUES.
 */
final readonly class CalculationResponse
{
    /**
     * @param array<string, mixed>|null $parameters
     * @param list<PaymentResponse>     $schedule
     */
    public function __construct(
        public ?string $id,
        public ?array $parameters,
        public SummaryResponse $summary,
        public array $schedule,
    ) {
    }

    public static function fromSchedule(PaymentSchedule $schedule): self
    {
        return new self(
            null,
            null,
            SummaryResponse::fromSchedule($schedule),
            array_map(PaymentResponse::fromPayment(...), $schedule->getPayments()),
        );
    }
}
