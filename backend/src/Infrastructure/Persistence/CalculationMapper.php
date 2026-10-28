<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Mortgage\Calculation\Calculation;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\RepaymentType;
use Symfony\Component\Uid\Uuid;

/**
 * Строка таблицы calculation ⇄ доменный Calculation. Единственное место, где домен встречается с форматом БД.
 *
 * Деньги и ставка ходят строками в обе стороны: DBAL отдаёт NUMERIC строкой без потери точности,
 * а Money::of() строку и ждёт. Поэтому кастомный Doctrine Type не нужен — его место было бы в ORM.
 */
final class CalculationMapper
{
    // Микросекунды (.u) обязательны: стандартный тип DBAL datetimetz_immutable их отбрасывает,
    // и после save → find createdAt уже не равнялся бы исходному.
    private const string CREATED_AT_FORMAT = 'Y-m-d H:i:s.uP';

    /**
     * @return array<string, string|int>
     */
    public function toRow(Calculation $calculation): array
    {
        $terms = $calculation->getTerms();

        return [
            'id' => $calculation->getId()->toRfc4122(),
            'property_price' => $terms->getPropertyPrice()->toString(),
            'down_payment' => $terms->getDownPayment()->toString(),
            'annual_rate' => $terms->getAnnualRate()->toString(),
            'term_months' => $terms->getTermMonths(),
            'repayment_type' => $terms->getRepaymentType()->value,
            'start_date' => $terms->getStartDate()->format('Y-m-d'),
            'created_at' => $calculation->getCreatedAt()->format(self::CREATED_AT_FORMAT),
        ];
    }

    /**
     * Восстанавливаем через тот же CreditTerms::create(), что и при вводе: в БД может лежать
     * только то, что прошло правила, — значит, и прочитанное снова их пройдёт (дизайн 3.1.1).
     *
     * @param array<string, mixed> $row как вернул DBAL fetchAssociative()
     */
    public function fromRow(array $row): Calculation
    {
        // '!' обнуляет время: без него к дате приклеилось бы текущее время, и даты из БД не были бы равны.
        $startDate = \DateTimeImmutable::createFromFormat('!Y-m-d', self::string($row, 'start_date'));
        if (false === $startDate) {
            throw new \UnexpectedValueException(\sprintf('Invalid start_date "%s".', self::string($row, 'start_date')));
        }

        $terms = CreditTerms::create(
            self::string($row, 'property_price'),
            self::string($row, 'down_payment'),
            self::string($row, 'annual_rate'),
            self::int($row, 'term_months'),
            RepaymentType::from(self::string($row, 'repayment_type')),
            $startDate,
        );

        return new Calculation(
            Uuid::fromString(self::string($row, 'id')),
            $terms,
            new \DateTimeImmutable(self::string($row, 'created_at')),
        );
    }

    /**
     * DBAL типизирует значения строки как mixed — проверяем тип явно, а не приводим (string) вслепую:
     * если схема разойдётся с кодом, увидим понятную ошибку, а не странный расчёт.
     *
     * @param array<string, mixed> $row
     */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Column "%s" must be a string, got %s.', $column, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function int(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (!\is_int($value)) {
            throw new \UnexpectedValueException(\sprintf('Column "%s" must be an integer, got %s.', $column, get_debug_type($value)));
        }

        return $value;
    }
}
