import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createMemoryRouter } from 'react-router'
import { RouterProvider } from 'react-router/dom'
import { afterEach, expect, test, vi } from 'vitest'
import type { CalculationResult } from '../api/types'
import { CalculatorPage } from './CalculatorPage'
import { SavedCalculationPage } from './SavedCalculationPage'

const id = '0192f3a1-0000-7000-8000-000000000001'

const saved: CalculationResult = {
  id,
  parameters: {
    propertyPrice: '5000000.00',
    downPayment: '1000000.00',
    annualRate: '18.50',
    termMonths: 240,
    repaymentType: 'differentiated',
    startDate: '2026-10-23',
  },
  summary: {
    creditAmount: '4000000.00',
    monthlyPayment: '78333.33',
    totalPayment: '11431666.67',
    overpayment: '7431666.67',
  },
  schedule: [
    { number: 1, date: '2026-11-23', payment: '78333.33', interest: '61666.67', principal: '16666.66', balance: '3983333.34' },
  ],
}

afterEach(() => {
  vi.restoreAllMocks()
})

function renderAt(path: string) {
  const router = createMemoryRouter(
    [
      { path: '/', element: <CalculatorPage /> },
      { path: '/c/:id', element: <SavedCalculationPage /> },
    ],
    { initialEntries: [path] },
  )
  render(<RouterProvider router={router} />)

  return router
}

test('показывает итоги и график сохранённого расчёта', async () => {
  const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json(saved))

  renderAt(`/c/${id}`)

  expect(await screen.findByRole('heading', { name: 'Сохранённый расчёт' })).toBeInTheDocument()
  expect(screen.getAllByText(/^78\s333,33/)).not.toHaveLength(0)
  // условия кредита: получатель ссылки формы не видел
  expect(screen.getByText('18,5 % годовых')).toBeInTheDocument()
  expect(screen.getByText('240 мес.')).toBeInTheDocument()
  expect(screen.getByText('Дифференцированный')).toBeInTheDocument()
  expect(screen.getByText('23.10.2026')).toBeInTheDocument()
  expect(screen.getByRole('cell', { name: '23.11.2026' })).toBeInTheDocument()
  expect(fetch).toHaveBeenCalledWith(`/api/calculations/${id}`, undefined)
})

test('404 → «Расчёт не найден»', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json({ status: 404 }, { status: 404 }))

  renderAt(`/c/${id}`)

  expect(await screen.findByRole('heading', { name: 'Расчёт не найден' })).toBeInTheDocument()
})

test('500 → баннер, «Повторить» загружает расчёт заново', async () => {
  const fetch = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(Response.json({ status: 500 }, { status: 500 }))
    .mockResolvedValueOnce(Response.json(saved))
  const user = userEvent.setup()

  renderAt(`/c/${id}`)
  await user.click(await screen.findByRole('button', { name: 'Повторить' }))

  expect(await screen.findByRole('heading', { name: 'Сохранённый расчёт' })).toBeInTheDocument()
  expect(fetch).toHaveBeenCalledTimes(2)
})

test('«Открыть в калькуляторе» ведёт на / с параметрами в форме', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json(saved))
  const user = userEvent.setup()

  const router = renderAt(`/c/${id}`)
  await user.click(await screen.findByRole('button', { name: 'Открыть в калькуляторе' }))

  expect(router.state.location.pathname).toBe('/')
  expect(await screen.findByLabelText('Стоимость недвижимости, ₽')).toHaveValue('5\u202f000\u202f000.00')
  expect(screen.getByLabelText('Первоначальный взнос, ₽')).toHaveValue('1\u202f000\u202f000.00')
  expect(screen.getByLabelText('Ставка, % годовых')).toHaveValue('18.50')
  expect(screen.getByLabelText('Срок, месяцев')).toHaveValue('240')
  expect(screen.getByLabelText('Тип погашения')).toHaveValue('differentiated')
  expect(screen.getByLabelText('Дата выдачи кредита')).toHaveValue('2026-10-23')
})
