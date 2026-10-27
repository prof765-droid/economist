<?php

declare(strict_types=1);

namespace App\Domain\Mortgage;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

/**
 * Неотрицательная денежная сумма, всегда 2 знака после запятой.
 *
 * Почему BigDecimal, а не float: float хранит числа в двоичной системе и не может точно
 * представить 0.1 или 1.005 — погрешность копится от месяца к месяцу графика.
 * BigDecimal хранит десятичные цифры и округляет только там, где мы явно попросили.
 *
 * Почему 2 знака: столько же хранит колонка NUMERIC(15, 2) в БД. Округляем сами при создании,
 * чтобы считать ровно с тем, что ляжет в БД (иначе PostgreSQL округлит молча, и расчёт
 * «до сохранения» разойдётся с расчётом «после»).
 *
 * Объект неизменяемый (readonly): каждая операция возвращает новый Money, исходный не меняется.
 */
final readonly class Money
{
    private const int SCALE = 2;

    // Конструктор приватный: создать Money можно только через of()/zero(),
    // поэтому объект с 3 знаками или отрицательной суммой просто не может существовать.
    private function __construct(
        private BigDecimal $amount,
    ) {
    }

    /**
     * Единственная «точка входа» с проверками — все операции ниже возвращают результат через неё.
     *
     * @throws \InvalidArgumentException не число или сумма < 0
     */
    public static function of(string|BigDecimal $amount): self
    {
        try {
            // HalfUp — «школьное» округление: 1.005 → 1.01, 1.004 → 1.00.
            $decimal = BigDecimal::of($amount)->toScale(self::SCALE, RoundingMode::HalfUp);
        } catch (MathException $e) {
            // Наружу — встроенное исключение PHP: домен не навязывает вызывающему коду
            // исключения brick/math. Исходное сохраняем в previous — для отладки.
            throw new \InvalidArgumentException(\sprintf('Invalid money amount "%s".', $amount), previous: $e);
        }

        // Проверяем после округления: '-0.001' округлится до '0.00' — это ноль, а не долг.
        if ($decimal->isNegative()) {
            throw new \InvalidArgumentException(\sprintf('Money amount cannot be negative, got "%s".', $decimal));
        }

        return new self($decimal);
    }

    public static function zero(): self
    {
        return new self(BigDecimal::zero()->toScale(self::SCALE));
    }

    // Сумма двух неотрицательных чисел с 2 знаками — снова такое же число, проверять нечего.
    public function plus(self $other): self
    {
        return new self($this->amount->plus($other->amount));
    }

    // Через of(): если вычли больше, чем было, получим исключение — в графике это баг (остаток ушёл в минус).
    public function minus(self $other): self
    {
        return self::of($this->amount->minus($other->amount));
    }

    /**
     * Множитель — BigDecimal, а не Money/string: это промежуточная математика
     * (месячная ставка, коэффициент аннуитета), а не ввод пользователя.
     * Результат округляется через of(): 1000.00 × 0.0154166667 = 15.41666667 → 15.42.
     */
    public function multipliedBy(BigDecimal $factor): self
    {
        return self::of($this->amount->multipliedBy($factor));
    }

    // brick/math требует явно указать точность и правило округления при делении:
    // 1000.00 / 3 = 333.333… — без них она бы бросила исключение, а не потеряла копейки молча.
    public function dividedBy(int $divisor): self
    {
        return self::of($this->amount->dividedBy($divisor, self::SCALE, RoundingMode::HalfUp));
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount->isGreaterThan($other->amount);
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->amount->isGreaterThanOrEqualTo($other->amount);
    }

    // Меньшая из двух сумм. Нужна правилу графика «основной долг = min(плановый, остаток)»:
    // в последний месяц нельзя заплатить больше, чем осталось долга.
    public function min(self $other): self
    {
        return $other->isGreaterThan($this) ? $this : $other;
    }

    // Сравнение по значению, а не по ссылке: два разных объекта Money('10.00') равны.
    public function equals(self $other): bool
    {
        return $this->amount->isEqualTo($other->amount);
    }

    // Доступ к числу для математики, которой нет в Money (например, формула аннуитета).
    // Отдавать BigDecimal безопасно: он тоже неизменяемый.
    public function getAmount(): BigDecimal
    {
        return $this->amount;
    }

    public function toString(): string
    {
        return (string) $this->amount;
    }
}
