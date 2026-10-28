<?php

declare(strict_types=1);

namespace App\Application\Mortgage\GetCalculation;

use App\Domain\Mortgage\Calculation\CalculationNotFoundException;
use App\Domain\Mortgage\Calculation\CalculationRepository;
use App\Domain\Mortgage\Calculator\MortgageCalculator;
use Symfony\Component\Uid\Uuid;

/**
 * Сценарий «открыть расчёт по ссылке»: загрузить параметры и пересчитать график.
 *
 * Без Command: единственный параметр — id, уже проверенный роутингом (дизайн 2.2).
 */
final readonly class GetCalculationHandler
{
    public function __construct(
        private CalculationRepository $repository,
        private MortgageCalculator $calculator,
    ) {
    }

    /**
     * @throws CalculationNotFoundException расчёта с таким id нет (→ 404 в UI)
     */
    public function handle(Uuid $id): CalculationResult
    {
        $calculation = $this->repository->find($id) ?? throw CalculationNotFoundException::withId($id);

        return new CalculationResult($calculation, $this->calculator->calculate($calculation->getTerms()));
    }
}
