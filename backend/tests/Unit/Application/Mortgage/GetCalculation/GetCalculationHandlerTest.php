<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mortgage\GetCalculation;

use App\Application\Mortgage\GetCalculation\GetCalculationHandler;
use App\Domain\Mortgage\Calculation\Calculation;
use App\Domain\Mortgage\Calculation\CalculationNotFoundException;
use App\Domain\Mortgage\Calculation\CalculationRepository;
use App\Domain\Mortgage\Calculator\AnnuityCalculator;
use App\Domain\Mortgage\Calculator\DifferentiatedCalculator;
use App\Domain\Mortgage\Calculator\MortgageCalculator;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\RepaymentType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class GetCalculationHandlerTest extends TestCase
{
    public function testReturnsCalculationWithRecalculatedSchedule(): void
    {
        // Эталон из фичи 7: аннуитет 600 000 / 12 % / 3 мес.
        $calculation = new Calculation(
            Uuid::v7(),
            CreditTerms::create('600000', '0', '12', 3, RepaymentType::ANNUITY, new \DateTimeImmutable('2026-10-23')),
            new MockClock('2026-10-24 12:00:00')->now(),
        );

        $result = self::handler($calculation)->handle($calculation->getId());

        self::assertSame($calculation, $result->getCalculation());
        self::assertSame('204013.27', $result->getSchedule()->getMonthlyPayment()->toString());
        self::assertCount(3, $result->getSchedule()->getPayments());
    }

    public function testUnknownIdThrowsNotFound(): void
    {
        $id = Uuid::fromString('019a2b3c-4d5e-7f00-8a1b-2c3d4e5f6a7b');

        $this->expectException(CalculationNotFoundException::class);
        $this->expectExceptionMessage('Calculation "019a2b3c-4d5e-7f00-8a1b-2c3d4e5f6a7b" not found.');

        self::handler()->handle($id);
    }

    // Фейк в памяти с заранее сохранёнными расчётами — без БД, тест остаётся unit.
    private static function handler(Calculation ...$calculations): GetCalculationHandler
    {
        $repository = new class implements CalculationRepository {
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
        foreach ($calculations as $calculation) {
            $repository->save($calculation);
        }

        return new GetCalculationHandler(
            $repository,
            new MortgageCalculator([new AnnuityCalculator(), new DifferentiatedCalculator()]),
        );
    }
}
