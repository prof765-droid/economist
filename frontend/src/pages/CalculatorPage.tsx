import { useState } from 'react'
import { useLocation } from 'react-router'
import { type ApiErrorDetails, calculate, toErrorDetails } from '../api/client'
import type { CalculationResult, MortgageParameters, Violation } from '../api/types'
import { ErrorBanner } from '../components/ErrorBanner'
import { MortgageForm } from '../components/MortgageForm'
import { ScheduleTable } from '../components/ScheduleTable'
import { ShareLink } from '../components/ShareLink'
import { SummaryCards } from '../components/SummaryCards'
import { errorMessage } from '../lib/errorMessage'
import page from './Page.module.css'

// 422 — не баннер, а ошибки у полей формы
type BannerError = Exclude<ApiErrorDetails, { kind: 'validation' }>

// Результат хранится вместе со своими параметрами: «Поделиться» отправляет то, что на экране,
// даже если следующий расчёт не удался
interface Calculation {
  // номер расчёта — key для ShareLink: новый расчёт сбрасывает его сообщения
  number: number
  result: CalculationResult
  parameters: MortgageParameters
  sharedId: string | null
}

export function CalculatorPage() {
  const initialParameters = (useLocation().state as { parameters?: MortgageParameters } | null)?.parameters
  const [calculation, setCalculation] = useState<Calculation | null>(null)
  const [error, setError] = useState<BannerError | null>(null)
  const [violations, setViolations] = useState<Violation[]>([])
  // последние отправленные параметры — для «Повторить»
  const [lastParameters, setLastParameters] = useState<MortgageParameters | null>(null)

  async function submit(parameters: MortgageParameters) {
    setLastParameters(parameters)
    setError(null)

    try {
      const result = await calculate(parameters)
      setCalculation((previous) => ({ number: (previous?.number ?? 0) + 1, result, parameters, sharedId: null }))
    } catch (e) {
      const details = toErrorDetails(e)
      if (details.kind === 'validation') {
        setViolations(details.violations)
      } else {
        setError(details)
      }
    }
  }

  function shared(parameters: MortgageParameters, id: string) {
    // пока шло сохранение, мог пройти новый расчёт — его id не трогаем
    setCalculation((current) => (current?.parameters === parameters ? { ...current, sharedId: id } : current))
  }

  return (
    <main className={page.page}>
      <div className={`${page.section} ${page.card}`}>
        <h1>Ипотечный калькулятор</h1>
        {!calculation && (
          <p className={page.hint}>Заполните условия кредита и нажмите «Рассчитать» — появятся итоги и график платежей.</p>
        )}
        <MortgageForm
          onSubmit={submit}
          serverErrors={violations}
          initialParameters={initialParameters}
          actions={
            calculation && (
              <ShareLink
                key={calculation.number}
                parameters={calculation.parameters}
                sharedId={calculation.sharedId}
                onShared={(id) => shared(calculation.parameters, id)}
              />
            )
          }
        />
      </div>

      {error && lastParameters && (
        <ErrorBanner
          message={errorMessage(error, 'Не удалось выполнить расчёт. Попробуйте ещё раз.')}
          onRetry={error.kind === 'rateLimited' ? undefined : () => void submit(lastParameters)}
        />
      )}

      {calculation && (
        <section className={page.section} aria-label="Результат расчёта">
          <SummaryCards summary={calculation.result.summary} />
          <ScheduleTable schedule={calculation.result.schedule} />
        </section>
      )}
    </main>
  )
}
