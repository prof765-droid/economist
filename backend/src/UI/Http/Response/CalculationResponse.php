<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use App\Application\Mortgage\GetCalculation\CalculationResult;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Schedule\PaymentSchedule;
use App\UI\Http\Request\CalculateMortgageRequest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

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
        #[OA\Property(description: 'Только у сохранённого расчёта (GET)', format: 'uuid', example: '019a3f2e-7c1b-7d4e-9a10-3b5c8e2f6d71')]
        public ?string $id,
        // Формат запроса (дизайн 4.2) — ссылка на его схему, а не безликий object.
        #[OA\Property(description: 'Только у сохранённого расчёта (GET): параметры после округления доменом', ref: new Model(type: CalculateMortgageRequest::class), nullable: true)]
        public ?array $parameters,
        public SummaryResponse $summary,
        // Тип элементов явно: PropertyInfo сначала спрашивает конструктор через ReflectionExtractor
        // (with_constructor_extractor), видит просто array, и до phpdoc очередь не доходит.
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: PaymentResponse::class)))]
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
            self::parametersOf($calculation->getTerms()),
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
    private static function parametersOf(CreditTerms $terms): array
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
