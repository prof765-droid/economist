import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createMemoryRouter } from 'react-router'
import { RouterProvider } from 'react-router/dom'
import { afterEach, expect, test, vi } from 'vitest'
import type { CalculationResult } from '../api/types'
import { CalculatorPage } from './CalculatorPage'

const result: CalculationResult = {
  summary: {
    creditAmount: '4000000.00',
    monthlyPayment: '63275.86',
    totalPayment: '15186206.40',
    overpayment: '11186206.40',
  },
  schedule: [
    { number: 1, date: '2026-11-23', payment: '63275.86', interest: '61666.67', principal: '1609.19', balance: '3998390.81' },
    { number: 2, date: '2026-12-23', payment: '63275.86', interest: '61641.86', principal: '1634.00', balance: '3996756.81' },
  ],
}

afterEach(() => {
  vi.restoreAllMocks()
})

// Response читается один раз — на каждый вызов fetch новый
function mockFetch(...responses: (() => Response)[]) {
  const fetch = vi.spyOn(globalThis, 'fetch')
  for (const response of responses) {
    fetch.mockImplementationOnce(() => Promise.resolve(response()))
  }

  return fetch
}

const ok = () => Response.json(result)

// страница читает location.state — нужен роутер
function renderPage() {
  render(<RouterProvider router={createMemoryRouter([{ path: '/', element: <CalculatorPage /> }])} />)
}

async function fillAndSubmit(overrides: Partial<Record<string, string>> = {}) {
  const user = userEvent.setup()
  renderPage()

  const values = {
    'Стоимость недвижимости, ₽': '5000000',
    'Первоначальный взнос, ₽': '1000000',
    'Ставка, % годовых': '18.5',
    Срок: '20',
    ...overrides,
  }
  for (const [label, value] of Object.entries(values)) {
    await user.type(screen.getByLabelText(label), value ?? '')
  }
  await user.click(screen.getByRole('button', { name: 'Рассчитать' }))

  return user
}

test('отправляет параметры и показывает итоги и график', async () => {
  const fetch = mockFetch(ok)

  await fillAndSubmit()

  expect(await screen.findByText('Ежемесячный платёж')).toBeInTheDocument()
  // разделитель разрядов ru-RU — неразрывный пробел, \s его ловит
  expect(screen.getAllByText(/^63\s275,86/)).not.toHaveLength(0)
  expect(screen.getAllByRole('row')).toHaveLength(1 + result.schedule.length)
  expect(screen.getByRole('cell', { name: '23.11.2026' })).toBeInTheDocument()

  const body: unknown = JSON.parse(String(fetch.mock.calls[0]?.[1]?.body))
  expect(body).toMatchObject({ propertyPrice: '5000000', annualRate: '18.5', termMonths: 240, repaymentType: 'annuity' })
})

test('ошибка Zod показывается у поля, запрос не уходит', async () => {
  const fetch = mockFetch(ok)

  await fillAndSubmit({ Срок: '31' })

  expect(screen.getByLabelText('Срок')).toHaveAccessibleDescription('Срок должен быть от 1 до 30 лет')
  expect(fetch).not.toHaveBeenCalled()
})

test('по умолчанию срок в годах; в месяцах уходит как есть', async () => {
  const user = userEvent.setup()
  const fetch = mockFetch(ok)
  renderPage()

  expect(screen.getByLabelText('Единицы срока')).toHaveValue('years')

  await user.type(screen.getByLabelText('Стоимость недвижимости, ₽'), '5000000')
  await user.type(screen.getByLabelText('Первоначальный взнос, ₽'), '1000000')
  await user.type(screen.getByLabelText('Ставка, % годовых'), '18.5')
  await user.selectOptions(screen.getByLabelText('Единицы срока'), 'months')
  await user.type(screen.getByLabelText('Срок'), '245')
  await user.click(screen.getByRole('button', { name: 'Рассчитать' }))

  await screen.findByText('Ежемесячный платёж')
  expect(JSON.parse(String(fetch.mock.calls[0]?.[1]?.body))).toMatchObject({ termMonths: 245 })
})

test('смена единиц пересчитывает срок; не кратные 12 месяцы в годы — поле очищается', async () => {
  const user = userEvent.setup()
  renderPage()
  const term = screen.getByLabelText('Срок')
  const unit = screen.getByLabelText('Единицы срока')

  await user.type(term, '20')
  await user.selectOptions(unit, 'months')
  expect(term).toHaveValue('240')

  await user.selectOptions(unit, 'years')
  expect(term).toHaveValue('20')

  await user.selectOptions(unit, 'months')
  await user.type(term, '{Backspace}5')
  await user.selectOptions(unit, 'years')
  expect(term).toHaveValue('')
})

test('422 с termMonths → сообщение у поля «Срок»', async () => {
  const message = 'Срок должен быть от 1 до 360 месяцев'
  mockFetch(() =>
    Response.json(
      { type: 'https://economist.local/errors/validation', title: 'Validation failed', status: 422, violations: [{ field: 'termMonths', message }] },
      { status: 422 },
    ),
  )

  await fillAndSubmit()

  expect(await screen.findByText(message)).toBeInTheDocument()
  expect(screen.getByLabelText('Срок')).toHaveAccessibleDescription(message)
})

test('422 с downPayment → сообщение у поля «Первоначальный взнос»', async () => {
  const message = 'Сумма кредита должна быть не меньше 500 000 ₽'
  mockFetch(() =>
    Response.json(
      { type: 'https://economist.local/errors/validation', title: 'Validation failed', status: 422, violations: [{ field: 'downPayment', message }] },
      { status: 422 },
    ),
  )

  await fillAndSubmit()

  expect(await screen.findByText(message)).toBeInTheDocument()
  expect(screen.getByLabelText('Первоначальный взнос, ₽')).toHaveAccessibleDescription(message)
})

test('500 → баннер, «Повторить» отправляет запрос повторно', async () => {
  const fetch = mockFetch(() => Response.json({ status: 500 }, { status: 500 }), ok)

  const user = await fillAndSubmit()
  await user.click(await screen.findByRole('button', { name: 'Повторить' }))

  expect(await screen.findByText('Ежемесячный платёж')).toBeInTheDocument()
  expect(fetch).toHaveBeenCalledTimes(2)
  expect(fetch.mock.calls[1]?.[1]?.body).toBe(fetch.mock.calls[0]?.[1]?.body)
  expect(screen.queryByRole('button', { name: 'Повторить' })).not.toBeInTheDocument()
})

test('429 → «Попробуйте через N секунд» без «Повторить»', async () => {
  mockFetch(() => Response.json({ status: 429 }, { status: 429, headers: { 'Retry-After': '30' } }))

  await fillAndSubmit()

  expect(await screen.findByText('Слишком много запросов. Попробуйте через 30 секунд')).toBeInTheDocument()
  expect(screen.queryByRole('button', { name: 'Повторить' })).not.toBeInTheDocument()
})

test('маска: ввод 1.234 в стоимость не принимает третий знак', async () => {
  const user = userEvent.setup()
  renderPage()
  const price = screen.getByLabelText('Стоимость недвижимости, ₽')

  await user.type(price, '1.234')

  expect(price).toHaveValue('1.23')
})

test('маска: вставка «5 000 000» через буфер обмена', async () => {
  const user = userEvent.setup()
  renderPage()
  const price = screen.getByLabelText('Стоимость недвижимости, ₽')

  await user.click(price)
  await user.paste('5 000 000')

  // в поле — разряды через узкий пробел, в запрос уходит без пробелов (проверено в первом тесте)
  expect(price).toHaveValue('5\u202f000\u202f000')
})

test('разряды: вставка цифры в середину числа не сбивает курсор в конец', async () => {
  const user = userEvent.setup()
  renderPage()
  const price = screen.getByLabelText<HTMLInputElement>('Стоимость недвижимости, ₽')

  await user.type(price, '5000000')
  expect(price).toHaveValue('5\u202f000\u202f000')

  // курсор после «5», вводим «1» → 51 000 000, курсор — после «51»
  await user.type(price, '1', { initialSelectionStart: 1, initialSelectionEnd: 1 })
  expect(price).toHaveValue('51\u202f000\u202f000')
  expect(price.selectionStart).toBe(2)
})

test('маска: отклонённый ввод показывает подсказку, допустимый ввод и уход с поля её убирают', async () => {
  const hint = 'Только цифры, не больше 2 знаков после точки'
  const user = userEvent.setup()
  renderPage()
  const rate = screen.getByLabelText('Ставка, % годовых')

  // случай из фичи 15: «240» вставили в «Ставку» с 18.5 — вставка не прошла
  await user.type(rate, '18.5')
  await user.paste('240')
  expect(rate).toHaveValue('18.5')
  expect(screen.getByText(hint)).toBeInTheDocument()

  await user.type(rate, '{Backspace}')
  expect(screen.queryByText(hint)).not.toBeInTheDocument()

  await user.type(rate, 'x')
  expect(screen.getByText(hint)).toBeInTheDocument()
  await user.tab()
  expect(screen.queryByText(hint)).not.toBeInTheDocument()
})

test('«Поделиться» отправляет параметры последнего расчёта, а не текущей формы; после перерасчёта — новый POST', async () => {
  const saved = (id: string) => () => Response.json({ id }, { status: 201 })
  const fetch = mockFetch(ok, saved('id-1'), ok, saved('id-2'))

  const user = await fillAndSubmit()
  await screen.findByText('Ежемесячный платёж')

  // поле изменено без перерасчёта — делимся тем, что на экране
  await user.type(screen.getByLabelText('Срок'), '{Backspace}{Backspace}10')
  await user.click(screen.getByRole('button', { name: 'Поделиться' }))

  expect(await screen.findByRole('textbox', { name: 'Ссылка на расчёт' })).toHaveValue(`${location.origin}/c/id-1`)
  expect(fetch.mock.calls[1]?.[0]).toBe('/api/calculations')
  expect(JSON.parse(String(fetch.mock.calls[1]?.[1]?.body))).toMatchObject({ termMonths: 240 })

  await user.click(screen.getByRole('button', { name: 'Рассчитать' }))
  // новый расчёт сбросил ссылку
  await waitFor(() => expect(screen.queryByRole('textbox', { name: 'Ссылка на расчёт' })).not.toBeInTheDocument())
  await user.click(screen.getByRole('button', { name: 'Поделиться' }))

  expect(await screen.findByRole('textbox', { name: 'Ссылка на расчёт' })).toHaveValue(`${location.origin}/c/id-2`)
  expect(fetch).toHaveBeenCalledTimes(4)
  expect(JSON.parse(String(fetch.mock.calls[3]?.[1]?.body))).toMatchObject({ termMonths: 120 })
})
