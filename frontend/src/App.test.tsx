import { render, screen } from '@testing-library/react'
import { createMemoryRouter } from 'react-router'
import { RouterProvider } from 'react-router/dom'
import { afterEach, expect, test, vi } from 'vitest'
import App from './App'
import type { CalculationResult } from './api/types'
import { routes } from './routes'

afterEach(() => {
  vi.restoreAllMocks()
})

function renderAt(path: string) {
  render(<RouterProvider router={createMemoryRouter(routes, { initialEntries: [path] })} />)
}

test('рендерит заголовок', () => {
  render(<App />)

  expect(screen.getByRole('heading', { name: 'Ипотечный калькулятор' })).toBeInTheDocument()
})

test('/c/:id — страница сохранённого расчёта с id из адреса', async () => {
  const id = '0192f3a1-0000-7000-8000-000000000001'
  const saved: CalculationResult = {
    id,
    summary: { creditAmount: '4000000.00', monthlyPayment: '63275.86', totalPayment: '15186214.84', overpayment: '11186214.84' },
    schedule: [],
  }
  const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json(saved))

  renderAt(`/c/${id}`)

  expect(await screen.findByRole('heading', { name: 'Сохранённый расчёт' })).toBeInTheDocument()
  expect(fetch).toHaveBeenCalledWith(`/api/calculations/${id}`, undefined)
})

test('неизвестный адрес — «Страница не найдена» со ссылкой на калькулятор', () => {
  renderAt('/nope')

  expect(screen.getByRole('heading', { name: 'Страница не найдена' })).toBeInTheDocument()
  expect(screen.getByRole('link', { name: 'К калькулятору' })).toHaveAttribute('href', '/')
})
