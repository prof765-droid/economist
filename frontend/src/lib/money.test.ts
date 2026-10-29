import { expect, test } from 'vitest'
import { toCents } from './money'

test.each([
  ['1000000.07', 100000007n],
  ['18.5', 1850n],
  ['500000', 50000000n],
  ['0.01', 1n],
])('%j → %d копеек', (input, expected) => {
  expect(toCents(input)).toBe(expected)
})

test('вычитание точное там, где float ошибается', () => {
  expect(1000000.07 - 500000.07).toBeLessThan(500000)
  expect(toCents('1000000.07') - toCents('500000.07')).toBe(50000000n)
})
