<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Schedule;

use App\Domain\Mortgage\Money;

/**
 * Результат расчёта: сумма кредита и список платежей.
 *
 * Итоги — методы, а не поля: считаются из списка, поэтому разойтись с ним не могут.
 */
final readonly class PaymentSchedule
{
    /** @var non-empty-list<Payment> */
    private array $payments;

    /**
     * creditAmount передаётся явно, а не выводится из Σ principal, — иначе нечего было бы сверять:
     * ошибка калькулятора (например, в коррекции последнего платежа) ловится здесь, при создании.
     *
     * Принимаем list, а не non-empty-list: «непустой» — это то, что гарантирует проверка ниже,
     * а не обещание вызывающего. PHPDoc PHP не проверяет — калькулятор с багом может передать [].
     *
     * @param list<Payment> $payments
     *
     * @throws \LogicException график не сходится — это баг калькулятора, а не ошибка ввода
     */
    public function __construct(
        private Money $creditAmount,
        array $payments,
    ) {
        if ([] === $payments) {
            throw new \LogicException('Payment schedule cannot be empty.');
        }

        $principalSum = array_reduce(
            $payments,
            static fn (Money $sum, Payment $payment): Money => $sum->plus($payment->getPrincipal()),
            Money::zero(),
        );

        if (!$principalSum->equals($creditAmount)) {
            throw new \LogicException(\sprintf('Sum of principal %s does not match credit amount %s.', $principalSum->toString(), $creditAmount->toString()));
        }

        $lastBalance = $payments[array_key_last($payments)]->getBalance();

        if (!$lastBalance->isZero()) {
            throw new \LogicException(\sprintf('Balance after the last payment must be 0.00, got %s.', $lastBalance->toString()));
        }

        // Присваиваем после проверок: PHPStan уже знает, что список непустой.
        $this->payments = $payments;
    }

    public function getCreditAmount(): Money
    {
        return $this->creditAmount;
    }

    /**
     * @return non-empty-list<Payment>
     */
    public function getPayments(): array
    {
        return $this->payments;
    }

    // Первый платёж — одно правило для обоих типов: в аннуитете он регулярный, в дифференцированном — максимальный.
    public function getMonthlyPayment(): Money
    {
        return $this->payments[0]->getAmount();
    }

    public function getTotalPayment(): Money
    {
        return array_reduce(
            $this->payments,
            static fn (Money $sum, Payment $payment): Money => $sum->plus($payment->getAmount()),
            Money::zero(),
        );
    }

    // Не уйдёт в минус: Σ principal = creditAmount (проверено в конструкторе), а проценты ≥ 0.
    public function getOverpayment(): Money
    {
        return $this->getTotalPayment()->minus($this->creditAmount);
    }
}
