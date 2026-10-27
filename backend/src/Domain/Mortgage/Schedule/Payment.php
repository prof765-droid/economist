<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Schedule;

use App\Domain\Mortgage\Money;

/**
 * Платёж № N — одна строка графика.
 *
 * Сам себя не проверяет (amount = interest + principal и т. п.): его создают только калькуляторы,
 * а сходимость графика целиком проверяет PaymentSchedule.
 */
final readonly class Payment
{
    public function __construct(
        private int $number,
        private \DateTimeImmutable $date,
        private Money $amount,
        private Money $interest,
        private Money $principal,
        private Money $balance,
    ) {
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    // Сумма платежа: проценты + погашение основного долга.
    public function getAmount(): Money
    {
        return $this->amount;
    }

    // Проценты: плата банку за месяц пользования деньгами, считается от остатка долга до платежа.
    // Долг не уменьшает.
    public function getInterest(): Money
    {
        return $this->interest;
    }

    // Погашение основного долга («тело кредита»): на эту сумму уменьшается остаток.
    public function getPrincipal(): Money
    {
        return $this->principal;
    }

    // Остаток долга после этого платежа.
    public function getBalance(): Money
    {
        return $this->balance;
    }
}
