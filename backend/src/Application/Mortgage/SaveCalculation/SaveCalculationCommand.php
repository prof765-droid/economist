<?php

declare(strict_types=1);

namespace App\Application\Mortgage\SaveCalculation;

use App\Domain\Mortgage\Credit\RepaymentType;
use Symfony\Component\Uid\Uuid;

/**
 * Намерение «сохранить расчёт» — те же параметры, что у CalculateMortgageCommand, плюс id.
 *
 * id генерирует контроллер, а не handler: handler ничего не возвращает — так команды
 * работают в шине, переход на Messenger (этап 5) не потребует менять сценарий (дизайн 2.2).
 */
final readonly class SaveCalculationCommand
{
    public function __construct(
        private Uuid $id,
        private string $propertyPrice,
        private string $downPayment,
        private string $annualRate,
        private int $termMonths,
        private RepaymentType $repaymentType,
        private \DateTimeImmutable $startDate,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPropertyPrice(): string
    {
        return $this->propertyPrice;
    }

    public function getDownPayment(): string
    {
        return $this->downPayment;
    }

    public function getAnnualRate(): string
    {
        return $this->annualRate;
    }

    public function getTermMonths(): int
    {
        return $this->termMonths;
    }

    public function getRepaymentType(): RepaymentType
    {
        return $this->repaymentType;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }
}
