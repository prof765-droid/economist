<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Loan;

use App\Domain\Mortgage\Money;
use Brick\Math\BigDecimal;

/**
 * Условия кредита — единственная точка входа данных в домен.
 *
 * Создаётся только через create(): она проверяет все правила разом, поэтому дальше
 * калькуляторы и график доверяют объекту и ничего не перепроверяют.
 */
final readonly class LoanTerms
{
    // Строки, а не числа: сравниваем с BigDecimal, а строка точна (как MAX_RATE в InterestRate).
    public const string MAX_PROPERTY_PRICE = '10000000000.00';
    public const string MIN_LOAN_AMOUNT = '500000.00';
    public const int MIN_TERM_MONTHS = 1;
    public const int MAX_TERM_MONTHS = 360;

    private function __construct(
        private Money $propertyPrice,
        private Money $downPayment,
        private InterestRate $annualRate,
        private int $termMonths,
        private PaymentType $paymentType,
        private \DateTimeImmutable $startDate,
    ) {
    }

    /**
     * Суммы и ставка приходят строками — как из JSON и из БД; превращаем их в VO здесь,
     * чтобы ошибку разбора привязать к конкретному полю.
     *
     * @throws InvalidLoanTermsException все нарушения сразу, {поле → сообщение}
     */
    public static function create(
        string $propertyPrice,
        string $downPayment,
        string $annualRate,
        int $termMonths,
        PaymentType $paymentType,
        \DateTimeImmutable $startDate,
    ): self {
        $violations = [];

        // Каждый VO — в своём try: ошибка в цене не должна мешать проверить взнос и ставку.
        // null значит «не распарсилось» — правила, зависящие от этого поля, пропускаем.
        try {
            $price = Money::of($propertyPrice);
        } catch (\InvalidArgumentException) {
            $price = null;
            $violations['propertyPrice'] = 'Стоимость недвижимости должна быть неотрицательным числом';
        }

        try {
            $payment = Money::of($downPayment);
        } catch (\InvalidArgumentException) {
            $payment = null;
            $violations['downPayment'] = 'Первоначальный взнос должен быть неотрицательным числом';
        }

        try {
            $rate = InterestRate::of($annualRate);
        } catch (\InvalidArgumentException) {
            $rate = null;
            $violations['annualRate'] = 'Ставка должна быть больше 0 % и не больше 30 %';
        }

        if (null !== $price && $price->getAmount()->isGreaterThan(self::MAX_PROPERTY_PRICE)) {
            $violations['propertyPrice'] = 'Стоимость недвижимости должна быть не больше 10 000 000 000 ₽';
        }

        // Только если оба распарсились: иначе при мусоре в цене получили бы ложное нарушение на взносе.
        // Вычитаем через BigDecimal: Money::minus при взносе больше цены бросил бы исключение,
        // а нам нужен результат, чтобы сравнить с минимумом.
        if (null !== $price && null !== $payment
            && $price->getAmount()->minus($payment->getAmount())->isLessThan(self::MIN_LOAN_AMOUNT)) {
            // Привязано к взносу: «суммы кредита» в форме нет, исправляют её, уменьшая взнос.
            $violations['downPayment'] = 'Сумма кредита должна быть не меньше 500 000 ₽';
        }

        if ($termMonths < self::MIN_TERM_MONTHS || $termMonths > self::MAX_TERM_MONTHS) {
            $violations['termMonths'] = 'Срок должен быть от 1 до 360 месяцев';
        }

        // Проверяем все три null: PHPStan не знает, что при пустом списке нарушений все VO созданы.
        if ([] !== $violations || null === $price || null === $payment || null === $rate) {
            throw new InvalidLoanTermsException($violations);
        }

        return new self($price, $payment, $rate, $termMonths, $paymentType, $startDate);
    }

    public function getPropertyPrice(): Money
    {
        return $this->propertyPrice;
    }

    public function getDownPayment(): Money
    {
        return $this->downPayment;
    }

    public function getAnnualRate(): InterestRate
    {
        return $this->annualRate;
    }

    public function getTermMonths(): int
    {
        return $this->termMonths;
    }

    public function getPaymentType(): PaymentType
    {
        return $this->paymentType;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    // Не уйдёт в минус: create() гарантирует, что сумма кредита ≥ 500 000.
    public function getLoanAmount(): Money
    {
        return $this->propertyPrice->minus($this->downPayment);
    }

    /**
     * Дата платежа № N (первый — через месяц после начала).
     *
     * Считаем от даты начала, а не от предыдущего платежа — иначе после февраля график «съедет»
     * на 28-е навсегда. Если такого числа в месяце нет — последний день месяца: 31 янв. → 28 фев. → 31 мар.
     *
     * Просто modify("+N month") нельзя: 31 янв. + 1 месяц = 3 марта (PHP переносит лишние дни).
     * Поэтому сначала берём 1-е число целевого месяца — у него переполнения не бывает, — а день ставим сами.
     */
    public function getPaymentDate(int $number): \DateTimeImmutable
    {
        $target = $this->startDate->modify("first day of +{$number} month");
        $day = min((int) $this->startDate->format('j'), (int) $target->format('t'));

        return $target->setDate((int) $target->format('Y'), (int) $target->format('n'), $day);
    }
}
