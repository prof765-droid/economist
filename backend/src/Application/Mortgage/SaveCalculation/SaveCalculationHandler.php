<?php

declare(strict_types=1);

namespace App\Application\Mortgage\SaveCalculation;

use App\Domain\Mortgage\Calculation\Calculation;
use App\Domain\Mortgage\Calculation\CalculationRepository;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\InvalidCreditTermsException;
use Psr\Clock\ClockInterface;

/**
 * Сценарий «сохранить расчёт» (кнопка «Поделиться»).
 *
 * График не считается: храним только параметры, график пересчитает GET (дизайн 4.4).
 */
final readonly class SaveCalculationHandler
{
    public function __construct(
        private CalculationRepository $repository,
        // Часы снаружи, а не new \DateTimeImmutable(): в тесте время фиксирует MockClock (дизайн 2.2).
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidCreditTermsException условия нарушают бизнес-правила (→ 422 в UI)
     */
    public function handle(SaveCalculationCommand $command): void
    {
        // Те же правила, что у расчёта: в БД попадает только то, что можно посчитать.
        $terms = CreditTerms::create(
            $command->getPropertyPrice(),
            $command->getDownPayment(),
            $command->getAnnualRate(),
            $command->getTermMonths(),
            $command->getRepaymentType(),
            $command->getStartDate(),
        );

        $this->repository->save(new Calculation($command->getId(), $terms, $this->clock->now()));
    }
}
