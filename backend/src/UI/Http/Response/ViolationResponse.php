<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use OpenApi\Attributes as OA;

// Одна ошибка поля в ответе 422 — от Validator'а или от домена, формат один (дизайн 5).
final readonly class ViolationResponse
{
    public function __construct(
        #[OA\Property(description: 'Поле запроса', example: 'downPayment')]
        public string $field,
        #[OA\Property(example: 'Сумма кредита должна быть не меньше 500 000 ₽')]
        public string $message,
    ) {
    }
}
