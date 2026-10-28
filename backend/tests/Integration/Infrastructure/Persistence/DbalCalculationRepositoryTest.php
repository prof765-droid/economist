<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Domain\Mortgage\Calculation\Calculation;
use App\Domain\Mortgage\Calculation\CalculationRepository;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\RepaymentType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Настоящий PostgreSQL (дизайн 6.1): единственное место, где SQL написан руками.
 *
 * Репозиторий — из контейнера по интерфейсу: заодно проверяется, что autowiring
 * связал CalculationRepository с DbalCalculationRepository. Транзакцию после теста откатывает dama.
 */
final class DbalCalculationRepositoryTest extends KernelTestCase
{
    public function testFindsSavedCalculationUnchanged(): void
    {
        $repository = self::repository();
        $original = new Calculation(
            Uuid::v7(),
            // Крайние значения NUMERIC(15,2) и NUMERIC(5,2): точность не теряется на пути строка → БД → строка.
            CreditTerms::create(
                '9999999999.99',
                '1000000.01',
                '18.5',
                360,
                RepaymentType::DIFFERENTIATED,
                new \DateTimeImmutable('2026-01-31'),
            ),
            // Микросекунды: datetimetz_immutable DBAL их отбросил бы — маппер форматирует дату сам.
            new \DateTimeImmutable('2026-10-24 12:00:00.123456+00:00'),
        );

        $repository->save($original);

        self::assertEquals($original, $repository->find($original->getId()));
    }

    public function testFindReturnsNullForUnknownId(): void
    {
        self::assertNull(self::repository()->find(Uuid::v7()));
    }

    private static function repository(): CalculationRepository
    {
        return self::getContainer()->get(CalculationRepository::class);
    }
}
