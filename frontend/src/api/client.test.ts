import { afterEach, expect, test, vi } from 'vitest'
import { ApiError, calculate, getCalculation } from './client'
import type { MortgageParameters } from './types'

const parameters: MortgageParameters = {
  propertyPrice: '5000000.00',
  downPayment: '1000000.00',
  annualRate: '18.5',
  termMonths: 240,
  repaymentType: 'annuity',
  startDate: '2026-10-23',
}

afterEach(() => {
  vi.restoreAllMocks()
})

function mockFetch(body: unknown, init: ResponseInit) {
  return vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json(body, init))
}

async function errorOf(promise: Promise<unknown>): Promise<ApiError['details']> {
  const error: unknown = await promise.catch((e: unknown) => e)
  expect(error).toBeInstanceOf(ApiError)

  return (error as ApiError).details
}

test('calculate шлёт параметры JSON-ом и возвращает тело ответа', async () => {
  const result = { summary: {}, schedule: [] }
  const fetch = mockFetch(result, { status: 200 })

  await expect(calculate(parameters)).resolves.toEqual(result)
  expect(fetch).toHaveBeenCalledWith('/api/mortgage/calculate', expect.objectContaining({
    method: 'POST',
    body: JSON.stringify(parameters),
    headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
  }))
})

test('422 → validation с нарушениями', async () => {
  const violations = [{ field: 'downPayment', message: 'Сумма кредита должна быть не меньше 500 000 ₽' }]
  mockFetch({ type: 'https://economist.local/errors/validation', title: 'Validation failed', status: 422, violations }, { status: 422 })

  expect(await errorOf(calculate(parameters))).toEqual({ kind: 'validation', violations })
})

test('429 + Retry-After: 30 → rateLimited с retryAfter = 30', async () => {
  mockFetch({ type: 'about:blank', title: 'Too Many Requests', status: 429 }, { status: 429, headers: { 'Retry-After': '30' } })

  expect(await errorOf(calculate(parameters))).toEqual({ kind: 'rateLimited', retryAfter: 30 })
})

test('404 → notFound', async () => {
  mockFetch({ type: 'about:blank', title: 'Not Found', status: 404 }, { status: 404 })

  expect(await errorOf(getCalculation('0192f3a1-0000-7000-8000-000000000000'))).toEqual({ kind: 'notFound' })
})

test('500 → server', async () => {
  mockFetch({ type: 'about:blank', title: 'Internal Server Error', status: 500 }, { status: 500 })

  expect(await errorOf(calculate(parameters))).toEqual({ kind: 'server', status: 500 })
})

test('fetch отклонён → network', async () => {
  vi.spyOn(globalThis, 'fetch').mockRejectedValue(new TypeError('Failed to fetch'))

  expect(await errorOf(calculate(parameters))).toEqual({ kind: 'network' })
})
