// Срок в форме — число и единицы; в API всегда месяцы (дизайн 7.1).
// Это перевод единиц, а не финансовый расчёт: принцип «фронт ничего не считает» не нарушен.

export type TermUnit = 'years' | 'months'

const MONTHS_IN_YEAR = 12

const INTEGER = /^\d+$/

export function toMonths(term: number, unit: TermUnit): number {
  return unit === 'years' ? term * MONTHS_IN_YEAR : term
}

/** Срок из API в единицах формы: кратно 12 — годы, иначе месяцы. */
export function fromMonths(months: number): { term: number; unit: TermUnit } {
  return months % MONTHS_IN_YEAR === 0
    ? { term: months / MONTHS_IN_YEAR, unit: 'years' }
    : { term: months, unit: 'months' }
}

/**
 * Значение поля при смене единиц: 20 лет → «240», 240 мес. → «20».
 * Месяцы, не кратные 12, в годы не переводятся — поле очищается, а не превращается молча в другой срок.
 * Не целое число (пусто, «abc») — оставляем как есть: пересчитывать нечего, ошибку покажет схема.
 */
export function convertTerm(value: string, from: TermUnit, to: TermUnit): string {
  if (from === to || !INTEGER.test(value)) {
    return value
  }
  const months = toMonths(Number(value), from)
  if (to === 'months') {
    return String(months)
  }

  return months % MONTHS_IN_YEAR === 0 ? String(months / MONTHS_IN_YEAR) : ''
}

/** Целое число в диапазоне 1…max, записанное только цифрами (без «1.5», «1e1», пробелов). */
export function isTermInRange(value: string, max: number): boolean {
  return INTEGER.test(value) && Number(value) >= 1 && Number(value) <= max
}

const plural = new Intl.PluralRules('ru-RU')

// 'other' у русского — дробные числа; срок целый, но тип требует все категории
const YEARS: Record<Intl.LDMLPluralRule, string> = {
  zero: 'лет',
  one: 'год',
  two: 'года',
  few: 'года',
  many: 'лет',
  other: 'года',
}

/** 240 → «20 лет (240 мес.)», 12 → «1 год (12 мес.)», 245 → «245 мес.». */
export function formatTerm(months: number): string {
  const { term, unit } = fromMonths(months)

  return unit === 'years' ? `${term} ${YEARS[plural.select(term)]} (${months} мес.)` : `${months} мес.`
}
