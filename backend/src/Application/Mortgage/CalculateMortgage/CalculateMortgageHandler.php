<?php

declare(strict_types=1);

namespace App\Application\Mortgage\CalculateMortgage;

use App\Domain\Mortgage\Calculator\MortgageCalculator;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\InvalidCreditTermsException;
use App\Domain\Mortgage\Schedule\PaymentSchedule;

/**
 * Сценарий «рассчитать график без сохранения».
 *
 * Только связывает шаги: правила — в CreditTerms, алгоритм — в MortgageCalculator.
 */
final readonly class CalculateMortgageHandler
{
    public function __construct(
        private MortgageCalculator $calculator,
    ) {
    }

    /**
     * @throws InvalidCreditTermsException условия нарушают бизнес-правила (→ 422 в UI)
     */
    public function handle(CalculateMortgageCommand $command): PaymentSchedule
    {
        $terms = CreditTerms::create(
            $command->getPropertyPrice(),
            $command->getDownPayment(),
            $command->getAnnualRate(),
            $command->getTermMonths(),
            $command->getRepaymentType(),
            $command->getStartDate(),
        );

        return $this->calculator->calculate($terms);
    }
}
