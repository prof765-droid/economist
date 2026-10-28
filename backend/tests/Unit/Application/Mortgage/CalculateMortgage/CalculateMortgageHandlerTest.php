<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mortgage\CalculateMortgage;

use App\Application\Mortgage\CalculateMortgage\CalculateMortgageCommand;
use App\Application\Mortgage\CalculateMortgage\CalculateMortgageHandler;
use App\Domain\Mortgage\Calculator\AnnuityCalculator;
use App\Domain\Mortgage\Calculator\DifferentiatedCalculator;
use App\Domain\Mortgage\Calculator\MortgageCalculator;
use App\Domain\Mortgage\Credit\InvalidCreditTermsException;
use App\Domain\Mortgage\Credit\RepaymentType;
use PHPUnit\Framework\TestCase;

// Настоящий MortgageCalculator, без моков: домен чистый и быстрый, а мок проверял бы только «вызвали ли метод».
final class CalculateMortgageHandlerTest extends TestCase
{
    public function testCalculatesSchedule(): void
    {
        // Эталон из фичи 7: аннуитет 600 000 / 12 % / 3 мес.
        $schedule = self::handler()->handle(self::command('600000.00', '0', '12', 3));

        self::assertSame('204013.27', $schedule->getMonthlyPayment()->toString());
        self::assertCount(3, $schedule->getPayments());
    }

    public function testInvalidTermsExceptionPassesThrough(): void
    {
        try {
            // Сумма кредита 100 000 < 500 000 и срок 0 — два нарушения.
            self::handler()->handle(self::command('600000.00', '500000.00', '12', 0));
            self::fail('Expected InvalidCreditTermsException.');
        } catch (InvalidCreditTermsException $e) {
            self::assertSame(['downPayment', 'termMonths'], array_keys($e->getViolations()));
        }
    }

    private static function handler(): CalculateMortgageHandler
    {
        return new CalculateMortgageHandler(
            new MortgageCalculator([new AnnuityCalculator(), new DifferentiatedCalculator()]),
        );
    }

    private static function command(string $price, string $downPayment, string $rate, int $termMonths): CalculateMortgageCommand
    {
        return new CalculateMortgageCommand(
            $price,
            $downPayment,
            $rate,
            $termMonths,
            RepaymentType::ANNUITY,
            new \DateTimeImmutable('2026-10-23'),
        );
    }
}
