<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Credit;

/**
 * Пользователь ввёл недопустимые условия кредита.
 *
 * Несёт все нарушения сразу в виде {поле → сообщение}: фронт подсвечивает все неверные поля,
 * а не только первое. Имена полей — в терминах домена (`downPayment`, `termMonths`);
 * в имена полей API их переводит UI-слой.
 *
 * Наследник \DomainException: это не баг в коде, а нарушение бизнес-правила.
 */
final class InvalidCreditTermsException extends \DomainException
{
    /**
     * @param array<string, string> $violations
     */
    public function __construct(
        private readonly array $violations,
    ) {
        // Исключение «ошибок нет» — бессмыслица: значит, вызывающий код забыл проверить список.
        if ([] === $violations) {
            throw new \InvalidArgumentException('Violations list cannot be empty.');
        }

        // Сообщение — для логов и отладки; пользователю показываются сами нарушения.
        parent::__construct(\sprintf('Invalid credit terms: %s.', implode(', ', array_keys($violations))));
    }

    /**
     * @return array<string, string>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
