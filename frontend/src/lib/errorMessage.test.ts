import { expect, test } from 'vitest'
import { errorMessage } from './errorMessage'

const FALLBACK = 'Не удалось выполнить расчёт.'

test.each([
  [1, '1 секунду'],
  [2, '2 секунды'],
  [5, '5 секунд'],
  [11, '11 секунд'],
  [21, '21 секунду'],
  [22, '22 секунды'],
  [30, '30 секунд'],
])('429: через %i — «%s»', (retryAfter, expected) => {
  expect(errorMessage({ kind: 'rateLimited', retryAfter }, FALLBACK)).toBe(`Слишком много запросов. Попробуйте через ${expected}`)
})

test('сеть — про подключение, а не про расчёт', () => {
  expect(errorMessage({ kind: 'network' }, FALLBACK)).toBe('Не удалось связаться с сервером. Проверьте подключение к интернету.')
})

test('остальные ошибки — текст вызывающего кода', () => {
  expect(errorMessage({ kind: 'server', status: 500 }, FALLBACK)).toBe(FALLBACK)
  expect(errorMessage({ kind: 'notFound' }, FALLBACK)).toBe(FALLBACK)
})
