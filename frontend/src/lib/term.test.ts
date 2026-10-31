import { expect, test } from 'vitest'
import { convertTerm, formatTerm, fromMonths, isTermInRange, toMonths } from './term'

test('годы × 12 → месяцы, месяцы — как есть', () => {
  expect(toMonths(20, 'years')).toBe(240)
  expect(toMonths(245, 'months')).toBe(245)
})

test.each([
  [240, { term: 20, unit: 'years' }],
  [12, { term: 1, unit: 'years' }],
  [245, { term: 245, unit: 'months' }],
  [6, { term: 6, unit: 'months' }],
])('%i мес. в единицах формы → %j', (months, expected) => {
  expect(fromMonths(months)).toEqual(expected)
})

test.each([
  ['20', 'years', 'months', '240'],
  ['240', 'months', 'years', '20'],
  ['245', 'months', 'years', ''],
  ['', 'years', 'months', ''],
  ['abc', 'months', 'years', 'abc'],
  ['1.5', 'years', 'months', '1.5'],
  ['20', 'years', 'years', '20'],
] as const)('«%s» %s → %s = «%s»', (value, from, to, expected) => {
  expect(convertTerm(value, from, to)).toBe(expected)
})

test.each([
  ['1', 30, true],
  ['30', 30, true],
  ['0', 30, false],
  ['31', 30, false],
  ['1.5', 30, false],
  ['1e1', 30, false],
  [' 5', 30, false],
  ['', 30, false],
] as const)('«%s» в 1…%i — %s', (value, max, expected) => {
  expect(isTermInRange(value, max)).toBe(expected)
})

test.each([
  [240, '20 лет (240 мес.)'],
  [12, '1 год (12 мес.)'],
  [24, '2 года (24 мес.)'],
  [252, '21 год (252 мес.)'],
  [132, '11 лет (132 мес.)'],
  [245, '245 мес.'],
])('%i мес. → «%s»', (months, expected) => {
  expect(formatTerm(months)).toBe(expected)
})
