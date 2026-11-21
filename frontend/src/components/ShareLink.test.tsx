import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import type { MortgageParameters } from '../api/types'
import { ShareLink } from './ShareLink'

const parameters: MortgageParameters = {
  propertyPrice: '5000000',
  downPayment: '1000000',
  annualRate: '18.5',
  termMonths: 240,
  repaymentType: 'annuity',
  startDate: '2026-10-23',
}
const id = '0192f3a1-0000-7000-8000-000000000001'
const url = `${location.origin}/c/${id}`

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
  Reflect.deleteProperty(navigator, 'share')
})

// sharedId хранит родитель (CalculatorPage) — здесь его минимальная замена
function Parent() {
  const [sharedId, setSharedId] = useState<string | null>(null)

  return <ShareLink parameters={parameters} sharedId={sharedId} onShared={setSharedId} />
}

function mockSave() {
  // Response читается один раз — на каждый вызов fetch новый
  return vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(Response.json({ id }, { status: 201 })))
}

// userEvent.setup() подменяет navigator.clipboard своей заглушкой — шпион ставим после
function setup() {
  const user = userEvent.setup()
  const writeText = vi.spyOn(navigator.clipboard, 'writeText')
  render(<Parent />)

  return { user, writeText }
}

function touchScreen(share: (data: ShareData) => Promise<void>) {
  vi.stubGlobal('matchMedia', (query: string) => ({ matches: query === '(pointer: coarse)' }))
  Object.defineProperty(navigator, 'share', { value: vi.fn(share), configurable: true })

  return navigator.share
}

test('первое нажатие сохраняет и копирует ссылку, второе — без запроса, та же ссылка', async () => {
  const fetch = mockSave()
  const { user, writeText } = setup()

  await user.click(screen.getByRole('button', { name: 'Поделиться' }))

  expect(await screen.findByText('Ссылка скопирована')).toBeInTheDocument()
  expect(writeText).toHaveBeenLastCalledWith(url)
  expect(screen.getByRole('textbox', { name: 'Ссылка на расчёт' })).toHaveValue(url)
  expect(fetch).toHaveBeenCalledTimes(1)
  expect(JSON.parse(String(fetch.mock.calls[0]?.[1]?.body))).toEqual(parameters)

  await user.click(screen.getByRole('button', { name: 'Поделиться' }))

  expect(fetch).toHaveBeenCalledTimes(1)
  expect(writeText).toHaveBeenCalledTimes(2)
  expect(writeText).toHaveBeenLastCalledWith(url)
})

test('на сенсорном устройстве с Web Share вызывается navigator.share, а не буфер обмена', async () => {
  mockSave()
  const share = touchScreen(() => Promise.resolve())
  const { user, writeText } = setup()

  await user.click(screen.getByRole('button', { name: 'Поделиться' }))

  expect(await screen.findByRole('textbox', { name: 'Ссылка на расчёт' })).toHaveValue(url)
  expect(share).toHaveBeenCalledWith({ url })
  expect(writeText).not.toHaveBeenCalled()
})

test('закрытое меню «Поделиться» — не ошибка, сообщения нет', async () => {
  mockSave()
  touchScreen(() => Promise.reject(new DOMException('Share canceled', 'AbortError')))
  const { user } = setup()

  await user.click(screen.getByRole('button', { name: 'Поделиться' }))

  expect(await screen.findByRole('textbox', { name: 'Ссылка на расчёт' })).toBeInTheDocument()
  expect(screen.queryByRole('status')).not.toBeInTheDocument()
})

test('браузер не дал скопировать → «Скопируйте ссылку вручную», ссылка видна', async () => {
  mockSave()
  const { user, writeText } = setup()
  writeText.mockRejectedValueOnce(new DOMException('Not allowed', 'NotAllowedError'))

  await user.click(screen.getByRole('button', { name: 'Поделиться' }))

  expect(await screen.findByText('Скопируйте ссылку вручную')).toBeInTheDocument()
  expect(screen.getByRole('textbox', { name: 'Ссылка на расчёт' })).toHaveValue(url)

  await user.click(screen.getByRole('button', { name: 'Скопировать' }))

  expect(await screen.findByText('Ссылка скопирована')).toBeInTheDocument()
})

test('429 → «Попробуйте через N секунд», ссылки нет', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    Response.json({ status: 429 }, { status: 429, headers: { 'Retry-After': '30' } }),
  )
  const { user } = setup()

  await user.click(screen.getByRole('button', { name: 'Поделиться' }))

  expect(await screen.findByText('Слишком много запросов. Попробуйте через 30 секунд')).toBeInTheDocument()
  expect(screen.queryByRole('textbox', { name: 'Ссылка на расчёт' })).not.toBeInTheDocument()
})

test('расчёт сменился во время сохранения (компонент размонтирован) → ссылка не копируется', async () => {
  let respond: (response: Response) => void = () => {}
  vi.spyOn(globalThis, 'fetch').mockImplementationOnce(() => new Promise((resolve) => (respond = resolve)))
  const user = userEvent.setup()
  const writeText = vi.spyOn(navigator.clipboard, 'writeText')
  const onShared = vi.fn()
  const { unmount } = render(<ShareLink parameters={parameters} sharedId={null} onShared={onShared} />)

  await user.click(screen.getByRole('button', { name: 'Поделиться' }))
  // CalculatorPage размонтирует ShareLink сменой key при новом расчёте
  unmount()
  respond(Response.json({ id }, { status: 201 }))

  // onShared и проверка «на экране ли» идут подряд в одном продолжении share()
  await waitFor(() => expect(onShared).toHaveBeenCalledWith(id))
  expect(writeText).not.toHaveBeenCalled()
})
