import { expect, test } from 'vitest'
import { applyDecimalMask, groupThousands } from './decimalMask'

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

test.each([
  ['', ''],
  ['500', '500'],
  ['5000', '5\u202f000'],
  ['5000000.5', '5\u202f000\u202f000.5'],
  ['12.', '12.'],
  ['1234.567', '1\u202f234.567'],
])('groupThousands(%j) → %j', (input, expected) => {
  expect(groupThousands(input)).toBe(expected)
})
