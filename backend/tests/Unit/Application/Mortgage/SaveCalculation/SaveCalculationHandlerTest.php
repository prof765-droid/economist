<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mortgage\SaveCalculation;

use App\Application\Mortgage\SaveCalculation\SaveCalculationCommand;
use App\Application\Mortgage\SaveCalculation\SaveCalculationHandler;
use App\Domain\Mortgage\Calculation\Calculation;
use App\Domain\Mortgage\Calculation\CalculationRepository;
use App\Domain\Mortgage\Credit\InvalidCreditTermsException;
use App\Domain\Mortgage\Credit\RepaymentType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class SaveCalculationHandlerTest extends TestCase
{
    private const string ID = '019a2b3c-4d5e-7f00-8a1b-2c3d4e5f6a7b';

    public function testSavesCalculationWithTimeFromClock(): void
    {
        $repository = self::repository();
        $handler = new SaveCalculationHandler($repository, new MockClock('2026-10-24 12:00:00'));

        $handler->handle(self::command('5000000', '1000000'));

        $saved = $repository->find(Uuid::fromString(self::ID));
        self::assertNotNull($saved);
        self::assertEquals(new \DateTimeImmutable('2026-10-24 12:00:00'), $saved->getCreatedAt());
        // Параметры прошли через домен — округлены до копеек.
        self::assertSame('5000000.00', $saved->getTerms()->getPropertyPrice()->toString());
    }

    public function testInvalidTermsAreNotSaved(): void
    {
        $repository = self::repository();
        $handler = new SaveCalculationHandler($repository, new MockClock());

        try {
            // Сумма кредита 100 000 < 500 000.
            $handler->handle(self::command('600000', '500000'));
            self::fail('Expected InvalidCreditTermsException.');
        } catch (InvalidCreditTermsException) {
            self::assertNull($repository->find(Uuid::fromString(self::ID)));
        }
    }

    // Фейк в памяти, а не мок: проверяем, что сохранено, а не «вызвали ли save()».
    private static function repository(): CalculationRepository
    {
        return new class implements CalculationRepository {
            /** @var array<string, Calculation> */
            private array $calculations = [];

            public function save(Calculation $calculation): void
            {
                $this->calculations[$calculation->getId()->toRfc4122()] = $calculation;
            }

            public function find(Uuid $id): ?Calculation
            {
                return $this->calculations[$id->toRfc4122()] ?? null;
            }
        };
    }

    private static function command(string $price, string $downPayment): SaveCalculationCommand
    {
        return new SaveCalculationCommand(
            Uuid::fromString(self::ID),
            $price,
            $downPayment,
            '18.5',
            240,
            RepaymentType::ANNUITY,
            new \DateTimeImmutable('2026-10-23'),
        );
    }
}
