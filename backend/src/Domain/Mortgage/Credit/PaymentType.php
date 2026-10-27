<?php

declare(strict_types=1);

namespace App\Domain\Mortgage\Credit;

/**
 * Тип ежемесячного платежа по кредиту.
 */
enum PaymentType: string
{
    // Аннуитетный: платёж одинаковый каждый месяц, в начале в нём больше процентов, в конце — основного долга.
    case ANNUITY = 'annuity';

    // Дифференцированный: основной долг гасится равными частями, проценты начисляются на остаток,
    // поэтому платёж от месяца к месяцу уменьшается.
    case DIFFERENTIATED = 'differentiated';
}
