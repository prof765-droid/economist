import { expect, test } from 'vitest'
import { applyDecimalMask } from './decimalMask'

test.each([
  ['123.45', '123.45'],
  ['1,5', '1.5'],
  ['12.', '12.'],
  ['', ''],
  ['5 000 000', '5000000'],
  ['63 275,86', '63275.86'],
])('пропускает %j как %j', (input, expected) => {
  expect(applyDecimalMask(input)).toBe(expected)
})

test.each(['123.456', 'abc', '1.2.3', '-5', '63 275,86 ₽'])('отклоняет %j', (input) => {
  expect(applyDecimalMask(input)).toBeNull()
})
