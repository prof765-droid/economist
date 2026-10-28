<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Calculation;

use Symfony\Component\Uid\Uuid;

/**
 * Хранилище расчётов. Интерфейс — в домене, реализация (DBAL) — в Infrastructure:
 * домен говорит, что ему нужно, и не знает, где и как это лежит.
 */
interface CalculationRepository
{
    public function save(Calculation $calculation): void;

    // null, а не исключение: репозиторий только ищет. Считать ли отсутствие ошибкой —
    // решает сценарий (GetCalculationHandler бросит CalculationNotFoundException → 404).
    public function find(Uuid $id): ?Calculation;
}
