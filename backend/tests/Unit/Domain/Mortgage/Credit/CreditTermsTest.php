<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mortgage\Credit;

use App\Domain\Mortgage\Credit\CreditTerms;
use App\Domain\Mortgage\Credit\InvalidCreditTermsException;
use App\Domain\Mortgage\Credit\RepaymentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CreditTermsTest extends TestCase
{
    public function testCreatesValidTerms(): void
    {
        $terms = self::create();

        self::assertSame('5000000.00', $terms->getPropertyPrice()->toString());
        self::assertSame('1000000.00', $terms->getDownPayment()->toString());
        self::assertSame('18.50', $terms->getAnnualRate()->toString());
        self::assertSame(240, $terms->getTermMonths());
        self::assertSame(RepaymentType::ANNUITY, $terms->getRepaymentType());
        self::assertSame('2026-10-23', $terms->getStartDate()->format('Y-m-d'));
        self::assertSame('4000000.00', $terms->getCreditAmount()->toString());
    }

    public function testRoundsPropertyPrice(): void
    {
        self::assertSame('5000000.56', self::create(propertyPrice: '5000000.555')->getPropertyPrice()->toString());
    }

    public function testCollectsAllViolationsAtOnce(): void
    {
        $violations = self::violationsOf(downPayment: '5000000', annualRate: '0', termMonths: 0);

        self::assertSame(['annualRate', 'downPayment', 'termMonths'], self::sortedKeys($violations));
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function boundaryValidTerms(): iterable
    {
        yield 'максимальная стоимость' => ['10000000000.00', '1000000', '18.5', 240];
        yield 'минимальный срок' => ['5000000', '1000000', '18.5', 1];
        yield 'максимальный срок' => ['5000000', '1000000', '18.5', 360];
        yield 'минимальная сумма кредита' => ['1500000', '1000000.00', '18.5', 240];
        yield 'максимальная ставка' => ['5000000', '1000000', '30', 240];
    }

    #[DataProvider('boundaryValidTerms')]
    public function testAcceptsBoundaryValues(string $price, string $downPayment, string $rate, int $term): void
    {
        $this->expectNotToPerformAssertions();

        self::create($price, $downPayment, $rate, $term);
    }

    /**
     * @return iterable<string, array{string, string, string, int, string}>
     */
    public static function singleViolations(): iterable
    {
        yield 'стоимость выше максимума' => ['10000000000.01', '1000000', '18.5', 240, 'propertyPrice'];
        yield 'срок выше максимума' => ['5000000', '1000000', '18.5', 361, 'termMonths'];
        yield 'сумма кредита ниже минимума' => ['1500000', '1000000.01', '18.5', 240, 'downPayment'];
        yield 'маленькая стоимость без взноса' => ['400000', '0', '18.5', 240, 'downPayment'];
        yield 'ставка выше максимума' => ['5000000', '1000000', '30.01', 240, 'annualRate'];
        // Цена не распарсилась — сумму кредита не проверяем, ложного нарушения на взносе нет.
        yield 'мусор в стоимости' => ['abc', '1000000', '18.5', 240, 'propertyPrice'];
        yield 'отрицательный взнос' => ['5000000', '-1', '18.5', 240, 'downPayment'];
    }

    #[DataProvider('singleViolations')]
    public function testReportsSingleViolation(string $price, string $downPayment, string $rate, int $term, string $field): void
    {
        self::assertSame([$field], array_keys(self::violationsOf($price, $downPayment, $rate, $term)));
    }

    public function testCreditAmountViolationMessage(): void
    {
        self::assertSame(
            ['downPayment' => 'Сумма кредита должна быть не меньше 500 000 ₽'],
            self::violationsOf(downPayment: '4500000.01'),
        );
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function paymentDates(): iterable
    {
        yield 'первый платёж через месяц' => ['2026-10-23', 1, '2026-11-23'];
        yield 'через 10 лет' => ['2026-10-23', 120, '2036-10-23'];
        yield '31 янв. → конец февраля' => ['2026-01-31', 1, '2026-02-28'];
        yield '31 янв. → 31 марта, без «съезда»' => ['2026-01-31', 2, '2026-03-31'];
        yield '31 янв. → 30 апреля' => ['2026-01-31', 3, '2026-04-30'];
        yield 'високосный февраль' => ['2028-01-31', 1, '2028-02-29'];
        yield '29 фев. → невисокосный год' => ['2028-02-29', 12, '2029-02-28'];
        yield '29 фев. → следующий високосный' => ['2028-02-29', 48, '2032-02-29'];
        yield '30 янв. → конец февраля' => ['2026-01-30', 1, '2026-02-28'];
        yield '30 янв. → 30 марта' => ['2026-01-30', 2, '2026-03-30'];
    }

    #[DataProvider('paymentDates')]
    public function testGetPaymentDate(string $start, int $number, string $expected): void
    {
        self::assertSame($expected, self::create(startDate: $start)->getPaymentDate($number)->format('Y-m-d'));
    }

    // Значения по умолчанию — валидный набор: в тесте меняем только проверяемое поле.
    private static function create(
        string $propertyPrice = '5000000',
        string $downPayment = '1000000',
        string $annualRate = '18.5',
        int $termMonths = 240,
        string $startDate = '2026-10-23',
    ): CreditTerms {
        // Строка без времени — конструктор даёт полночь; в отличие от createFromFormat, false не вернёт.
        return CreditTerms::create(
            $propertyPrice,
            $downPayment,
            $annualRate,
            $termMonths,
            RepaymentType::ANNUITY,
            new \DateTimeImmutable($startDate),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function violationsOf(
        string $propertyPrice = '5000000',
        string $downPayment = '1000000',
        string $annualRate = '18.5',
        int $termMonths = 240,
    ): array {
        try {
            self::create($propertyPrice, $downPayment, $annualRate, $termMonths);
        } catch (InvalidCreditTermsException $e) {
            return $e->getViolations();
        }

        self::fail('Expected InvalidCreditTermsException.');
    }

    /**
     * @param array<string, string> $violations
     *
     * @return list<string>
     */
    private static function sortedKeys(array $violations): array
    {
        $keys = array_keys($violations);
        sort($keys);

        return $keys;
    }
}
