<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Mortgage\Calculation\Calculation;
use App\Domain\Mortgage\Calculation\CalculationRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Хранение расчётов через Doctrine DBAL, без ORM: одна таблица, запись один раз, чтение по id —
 * возможности ORM (связи, отслеживание изменений) не нужны. Переводы строка ⇄ объект — в CalculationMapper.
 */
final readonly class DbalCalculationRepository implements CalculationRepository
{
    public function __construct(
        private Connection $connection,
        private CalculationMapper $mapper,
    ) {
    }

    public function save(Calculation $calculation): void
    {
        // Остальные параметры по умолчанию строки — PostgreSQL сам приведёт их к NUMERIC, UUID, DATE.
        // Срок — число, тип указываем явно, чтобы он и ушёл числом.
        $this->connection->insert('calculation', $this->mapper->toRow($calculation), [
            'term_months' => ParameterType::INTEGER,
        ]);
    }

    public function find(Uuid $id): ?Calculation
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM calculation WHERE id = :id',
            ['id' => $id->toRfc4122()],
        );

        // fetchAssociative() возвращает false, если строки нет.
        return false === $row ? null : $this->mapper->fromRow($row);
    }
}
