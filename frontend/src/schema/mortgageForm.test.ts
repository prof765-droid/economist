import { expect, test } from 'vitest'
import { type MortgageFormValues, mortgageFormSchema } from './mortgageForm'

const valid: MortgageFormValues = {
  propertyPrice: '5000000.00',
  downPayment: '1000000.00',
  annualRate: '18.5',
  termMonths: '240',
  repaymentType: 'annuity',
  startDate: '2026-10-23',
}

// Record<string, string>, а не Partial<MortgageFormValues>: проверяем и значения вне типа (repaymentType: '')
function errorsOf(values: Record<string, string>): Record<string, string> {
  const result = mortgageFormSchema.safeParse({ ...valid, ...values })

  return Object.fromEntries((result.error?.issues ?? []).map((issue) => [issue.path.join('.'), issue.message]))
}

test('валидные значения превращаются в параметры API', () => {
  expect(mortgageFormSchema.parse(valid)).toEqual({ ...valid, termMonths: 240 })
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
  [{ termMonths: '361' }, 'termMonths', 'Срок должен быть от 1 до 360 месяцев'],
  [{ termMonths: '0' }, 'termMonths', 'Срок должен быть от 1 до 360 месяцев'],
  [{ termMonths: '12.5' }, 'termMonths', 'Срок должен быть от 1 до 360 месяцев'],
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

test('границы включены: ставка 30, срок 360', () => {
  expect(errorsOf({ annualRate: '30', termMonths: '360' })).toEqual({})
})
