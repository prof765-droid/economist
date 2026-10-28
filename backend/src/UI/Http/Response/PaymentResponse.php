<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use App\Domain\Mortgage\Schedule\Payment;

// Строка графика. Поле — payment, а не amount: в JSON оно стоит рядом с interest и principal.
final readonly class PaymentResponse
{
    public function __construct(
        public int $number,
        public string $date,
        public string $payment,
        public string $interest,
        public string $principal,
        public string $balance,
    ) {
    }

    public static function fromPayment(Payment $payment): self
    {
        return new self(
            $payment->getNumber(),
            $payment->getDate()->format('Y-m-d'),
            $payment->getAmount()->toString(),
            $payment->getInterest()->toString(),
            $payment->getPrincipal()->toString(),
            $payment->getBalance()->toString(),
        );
    }
}
