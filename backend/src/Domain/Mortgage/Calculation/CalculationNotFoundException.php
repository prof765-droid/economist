<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Calculation;

use Symfony\Component\Uid\Uuid;

/**
 * Расчёта с таким id нет — например, ссылку набрали с ошибкой.
 *
 * Наследник \DomainException, как InvalidCreditTermsException: это не баг в коде,
 * а ожидаемая ситуация предметной области (UI превратит её в 404).
 */
final class CalculationNotFoundException extends \DomainException
{
    // Фабрика вместо конструктора с id: текст сообщения собирается в одном месте,
    // а вызов читается как фраза — CalculationNotFoundException::withId($id).
    public static function withId(Uuid $id): self
    {
        return new self(\sprintf('Calculation "%s" not found.', $id->toRfc4122()));
    }
}
