<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use App\Domain\Mortgage\Schedule\Payment;
use OpenApi\Attributes as OA;

// Строка графика. Поле — payment, а не amount: в JSON оно стоит рядом с interest и principal.
final readonly class PaymentResponse
{
    public function __construct(
        #[OA\Property(example: 1)]
        public int $number,
        #[OA\Property(format: 'date', example: '2027-01-01')]
        public string $date,
        #[OA\Property(description: 'Платёж: проценты + основной долг', example: '75931.04')]
        public string $payment,
        #[OA\Property(example: '74000.00')]
        public string $interest,
        #[OA\Property(example: '1931.04')]
        public string $principal,
        #[OA\Property(description: 'Остаток долга после платежа', example: '4798068.96')]
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
