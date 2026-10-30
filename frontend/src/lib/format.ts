import type { RepaymentType } from '../api/types'

// Только отображение, без арифметики: Intl форматирует десятичную строку как есть, не через float.
const money = new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

/** `"63275.86"` → `"63 275,86"` (разделитель разрядов — неразрывный пробел). */
export function formatMoney(value: string): string {
  return money.format(value as `${number}`)
}

/** `"2026-11-23"` → `"23.11.2026"`; без `Date` — нет сдвига часового пояса. */
export function formatDate(value: string): string {
  const [year, month, day] = value.split('-')

  return `${day}.${month}.${year}`
}

const rate = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 })

/** `"18.50"` → `"18,5"`. */
export function formatRate(value: string): string {
  return rate.format(value as `${number}`)
}

const REPAYMENT_TYPES: Record<RepaymentType, string> = {
  annuity: 'Аннуитетный',
  differentiated: 'Дифференцированный',
}

export function formatRepaymentType(type: RepaymentType): string {
  return REPAYMENT_TYPES[type]
}
