import { useState } from 'react'
import { ApiError, type ApiErrorDetails, calculate } from '../api/client'
import type { CalculationResult, MortgageParameters, Violation } from '../api/types'
import { ErrorBanner } from '../components/ErrorBanner'
import { MortgageForm } from '../components/MortgageForm'
import { ScheduleTable } from '../components/ScheduleTable'
import { SummaryCards } from '../components/SummaryCards'

// 422 — не баннер, а ошибки у полей формы
type BannerError = Exclude<ApiErrorDetails, { kind: 'validation' }>

export function CalculatorPage() {
  const [result, setResult] = useState<CalculationResult | null>(null)
  const [error, setError] = useState<BannerError | null>(null)
  const [violations, setViolations] = useState<Violation[]>([])
  // последние отправленные параметры: для «Повторить», в фиче 16 — для «Поделиться»
  const [lastParameters, setLastParameters] = useState<MortgageParameters | null>(null)

  async function submit(parameters: MortgageParameters) {
    setLastParameters(parameters)
    setError(null)

    try {
      setResult(await calculate(parameters))
    } catch (e) {
      const details: ApiErrorDetails = e instanceof ApiError ? e.details : { kind: 'server', status: 0 }
      if (details.kind === 'validation') {
        setViolations(details.violations)
      } else {
        setError(details)
      }
    }
  }

  return (
    <main>
      <h1>Ипотечный калькулятор</h1>
      <MortgageForm onSubmit={submit} serverErrors={violations} />

      {error && lastParameters && (
        <ErrorBanner
          message={errorMessage(error)}
          onRetry={error.kind === 'rateLimited' ? undefined : () => void submit(lastParameters)}
        />
      )}

      {result && (
        <section aria-label="Результат расчёта">
          <SummaryCards summary={result.summary} />
          <ScheduleTable schedule={result.schedule} />
        </section>
      )}
    </main>
  )
}

const seconds = new Intl.PluralRules('ru')
const SECONDS: Partial<Record<Intl.LDMLPluralRule, string>> = { one: 'секунду', few: 'секунды' }

function errorMessage(error: BannerError): string {
  switch (error.kind) {
    case 'rateLimited':
      return `Слишком много запросов. Попробуйте через ${error.retryAfter} ${SECONDS[seconds.select(error.retryAfter)] ?? 'секунд'}`
    case 'network':
      return 'Не удалось связаться с сервером. Проверьте подключение к интернету.'
    default:
      return 'Не удалось выполнить расчёт. Попробуйте ещё раз.'
  }
}
