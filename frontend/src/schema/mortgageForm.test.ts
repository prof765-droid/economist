import { expect, test } from 'vitest'
import { type MortgageFormValues, mortgageFormSchema } from './mortgageForm'

const valid: MortgageFormValues = {
  propertyPrice: '5000000.00',
  downPayment: '1000000.00',
  annualRate: '18.5',
  term: '20',
  termUnit: 'years',
  repaymentType: 'annuity',
  startDate: '2026-10-23',
}

// Record<string, string>, а не Partial<MortgageFormValues>: проверяем и значения вне типа (repaymentType: '')
function errorsOf(values: Record<string, string>): Record<string, string> {
  const result = mortgageFormSchema.safeParse({ ...valid, ...values })

  return Object.fromEntries((result.error?.issues ?? []).map((issue) => [issue.path.join('.'), issue.message]))
}

test('валидные значения превращаются в параметры API: годы × 12 → termMonths', () => {
  const { term: _term, termUnit: _termUnit, ...parameters } = valid

  expect(mortgageFormSchema.parse(valid)).toEqual({ ...parameters, termMonths: 240 })
})

test('срок в месяцах уходит как есть', () => {
  expect(mortgageFormSchema.parse({ ...valid, term: '245', termUnit: 'months' })).toMatchObject({ termMonths: 245 })
})

test('сумма кредита 499 999.99 — ошибка на downPayment', () => {
  expect(errorsOf({ propertyPrice: '1499999.99', downPayment: '1000000.00' })).toEqual({
    downPayment: 'Сумма кредита должна быть не меньше 500 000 ₽',
  })
})

test('сумма кредита ровно 500 000 — без ошибки, даже где float ошибается', () => {
  expect(errorsOf({ propertyPrice: '1000000.07', downPayment: '500000.07' })).toEqual({})
})

test.each([
  [{ term: '31' }, 'term', 'Срок должен быть от 1 до 30 лет'],
  [{ term: '0' }, 'term', 'Срок должен быть от 1 до 30 лет'],
  [{ term: '1.5' }, 'term', 'Срок должен быть от 1 до 30 лет'],
  [{ term: '' }, 'term', 'Заполните поле'],
  [{ term: '361', termUnit: 'months' }, 'term', 'Срок должен быть от 1 до 360 месяцев'],
  [{ term: '0', termUnit: 'months' }, 'term', 'Срок должен быть от 1 до 360 месяцев'],
  [{ term: '12.5', termUnit: 'months' }, 'term', 'Срок должен быть от 1 до 360 месяцев'],
  [{ termUnit: 'days' }, 'termUnit', 'Выберите единицы срока'],
  [{ annualRate: '30.01' }, 'annualRate', 'Ставка должна быть больше 0 % и не больше 30 %'],
  [{ annualRate: '0' }, 'annualRate', 'Ставка должна быть больше 0 % и не больше 30 %'],
  [{ propertyPrice: '10000000000.01' }, 'propertyPrice', 'Стоимость недвижимости должна быть не больше 10 000 000 000 ₽'],
  [{ propertyPrice: '' }, 'propertyPrice', 'Заполните поле'],
  [{ propertyPrice: '12.' }, 'propertyPrice', 'Введите число, например 5000000.00'],
  [{ repaymentType: '' }, 'repaymentType', 'Выберите тип погашения'],
  [{ startDate: '' }, 'startDate', 'Введите дату в формате ГГГГ-ММ-ДД'],
])('%j → ошибка на %s', (values, field, message) => {
  expect(errorsOf(values)).toEqual({ [field]: message })
})

test('границы включены: ставка 30, срок 30 лет и 360 месяцев', () => {
  expect(errorsOf({ annualRate: '30', term: '30' })).toEqual({})
  expect(errorsOf({ term: '360', termUnit: 'months' })).toEqual({})
})
