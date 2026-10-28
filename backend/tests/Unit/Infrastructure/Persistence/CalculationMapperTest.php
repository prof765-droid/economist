<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence;

use App\Domain\Mortgage\Calculation\Calculation;
use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\RepaymentType;
use App\Infrastructure\Persistence\CalculationMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CalculationMapperTest extends TestCase
{
    public function testMapsToRowInDatabaseFormat(): void
    {
        $row = new CalculationMapper()->toRow(self::calculation());

        self::assertSame([
            'id' => '019a2b3c-4d5e-7f00-8a1b-2c3d4e5f6a7b',
            'property_price' => '5000000.00',
            'down_payment' => '1000000.00',
            'annual_rate' => '18.50',
            'term_months' => 240,
            'repayment_type' => 'differentiated',
            'start_date' => '2026-10-23',
            'created_at' => '2026-10-24 12:00:00.123456+00:00',
        ], $row);
    }

    public function testRestoresSameCalculationFromRow(): void
    {
        $mapper = new CalculationMapper();
        $original = self::calculation();

        self::assertEquals($original, $mapper->fromRow($mapper->toRow($original)));
    }

    public function testRejectsColumnOfUnexpectedType(): void
    {
        $row = new CalculationMapper()->toRow(self::calculation());
        $row['term_months'] = '240';

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Column "term_months" must be an integer, got string.');

        new CalculationMapper()->fromRow($row);
    }

    private static function calculation(): Calculation
    {
        return new Calculation(
            Uuid::fromString('019a2b3c-4d5e-7f00-8a1b-2c3d4e5f6a7b'),
            CreditTerms::create(
                '5000000',
                '1000000',
                '18.5',
                240,
                RepaymentType::DIFFERENTIATED,
                new \DateTimeImmutable('2026-10-23'),
            ),
            new \DateTimeImmutable('2026-10-24 12:00:00.123456'),
        );
    }
}
