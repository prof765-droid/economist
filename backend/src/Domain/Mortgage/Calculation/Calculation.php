<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Calculation;

use App\Domain\Mortgage\Credit\CreditTerms;
use Symfony\Component\Uid\Uuid;

/**
 * Сохранённый расчёт — то, что открывается по ссылке «Поделиться».
 *
 * Хранит ровно то, что лежит в таблице: id, параметры и время создания.
 * График не хранится — пересчитывается при чтении (расчёт детерминирован, один источник правды).
 *
 * Один конструктор, без create()/restore(): создание и восстановление из БД ничем не отличаются
 * (нет доменных событий) — разделять незачем. После создания не меняется → readonly.
 */
final readonly class Calculation
{
    public function __construct(
        private Uuid $id,
        private CreditTerms $terms,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTerms(): CreditTerms
    {
        return $this->terms;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
