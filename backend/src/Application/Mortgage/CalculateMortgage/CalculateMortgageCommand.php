<?php

declare(strict_types=1);

namespace App\Application\Mortgage\CalculateMortgage;

use App\Domain\Mortgage\Credit\RepaymentType;

/**
 * Намерение «рассчитать график» — чистые данные, без проверок (дизайн 2.2).
 *
 * Суммы и ставка — строки, как их ждёт CreditTerms::create(): проверку и округление делает домен.
 * Тип погашения и дата уже типизированы — их один раз разбирает Request DTO в UI.
 */
final readonly class CalculateMortgageCommand
{
    public function __construct(
        private string $propertyPrice,
        private string $downPayment,
        private string $annualRate,
        private int $termMonths,
        private RepaymentType $repaymentType,
        private \DateTimeImmutable $startDate,
    ) {
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
