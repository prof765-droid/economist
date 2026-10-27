<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Loan;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

/**
 * Годовая процентная ставка в процентах: 18.50 = 18,5 % годовых.
 *
 * 2 знака после запятой — как в колонке NUMERIC(5, 2) и как в реальных условиях банков.
 * Округляем при создании, чтобы расчёт до сохранения и после чтения из БД совпадал
 * (иначе '18.555' дал бы один график сейчас, а '18.56' из БД — другой по ссылке).
 */
final readonly class InterestRate
{
    // Строка, а не float: сравнивать будем с BigDecimal, а '30.00' как строка точна.
    public const string MAX_RATE = '30.00';

    private const int SCALE = 2;

    // Месячная ставка — промежуточное число, в БД не хранится, поэтому правило «2 знака» на неё
    // не распространяется. 10 знаков — правило расчёта из дизайна (3.1, 5): от него зависят эталоны графиков.
    private const int MONTHLY_SCALE = 10;

    // Приватный конструктор: ставку можно создать только через of(), то есть только проверенную.
    private function __construct(
        private BigDecimal $rate,
    ) {
    }

    /**
     * @throws \InvalidArgumentException не число или ставка вне (0; 30]
     */
    public static function of(string $rate): self
    {
        try {
            $decimal = BigDecimal::of($rate)->toScale(self::SCALE, RoundingMode::HalfUp);
        } catch (MathException $e) {
            // Как в Money: наружу встроенное исключение PHP, исходное — в previous.
            throw new \InvalidArgumentException(\sprintf('Invalid interest rate "%s".', $rate), previous: $e);
        }

        // Проверяем уже округлённое значение: '0.004' станет '0.00' и не пройдёт «> 0».
        // Если проверять до округления, такая ставка проскочила бы и превратилась в ноль.
        // Ставка 0 запрещена: формула аннуитета делит на (q − 1), а при нулевой ставке q = 1.
        if (!$decimal->isPositive() || $decimal->isGreaterThan(self::MAX_RATE)) {
            throw new \InvalidArgumentException(\sprintf('Interest rate must be greater than 0 and at most %s, got "%s".', self::MAX_RATE, $decimal));
        }

        return new self($decimal);
    }

    /**
     * Месячная ставка как доля, а не проценты: 12 % годовых → 0.0100000000.
     * Делим на 1200 = 12 месяцев × 100 (проценты, доля).
     * Возвращаем BigDecimal, а не InterestRate: это уже не «ставка из договора»,
     * а множитель для формул калькулятора, к нему не относятся лимиты 0–30.
     */
    public function getMonthlyRate(): BigDecimal
    {
        return $this->rate->dividedBy(1200, self::MONTHLY_SCALE, RoundingMode::HalfUp);
    }

    public function toString(): string
    {
        return (string) $this->rate;
    }
}
