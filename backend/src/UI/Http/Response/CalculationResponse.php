<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use App\Application\Mortgage\GetCalculation\CalculationResult;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Schedule\PaymentSchedule;

/**
 * Тело ответа с расчётом (дизайн 4.3).
 *
 * id и parameters есть только у сохранённого расчёта (GET); у calculate они null
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

    public static function fromResult(CalculationResult $result): self
    {
        $calculation = $result->getCalculation();
        $schedule = self::fromSchedule($result->getSchedule());

        return new self(
            $calculation->getId()->toRfc4122(),
            self::parameters($calculation->getTerms()),
            $schedule->summary,
            $schedule->schedule,
        );
    }

    /**
     * В формате запроса (дизайн 4.2): фронт подставляет их в форму «Открыть в калькуляторе» как есть.
     * Значения — уже из домена, после округления: annualRate "18.5" вернётся как "18.50".
     *
     * @return array<string, mixed>
     */
    private static function parameters(CreditTerms $terms): array
    {
        return [
            'propertyPrice' => $terms->getPropertyPrice()->toString(),
            'downPayment' => $terms->getDownPayment()->toString(),
            'annualRate' => $terms->getAnnualRate()->toString(),
            'termMonths' => $terms->getTermMonths(),
            'repaymentType' => $terms->getRepaymentType()->value,
            'startDate' => $terms->getStartDate()->format('Y-m-d'),
        ];
    }
}
