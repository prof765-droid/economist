import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { type ApiErrorDetails, getCalculation, toErrorDetails } from '../api/client'
import type { CalculationResult } from '../api/types'
import { ErrorBanner } from '../components/ErrorBanner'
import { ParametersList } from '../components/ParametersList'
import { ScheduleTable } from '../components/ScheduleTable'
import { SummaryCards } from '../components/SummaryCards'
import { errorMessage } from '../lib/errorMessage'

type State =
  | { status: 'loading' }
  | { status: 'loaded'; result: CalculationResult }
  | { status: 'failed'; error: ApiErrorDetails }

export function SavedCalculationPage() {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const [state, setState] = useState<State>({ status: 'loading' })
  // «Повторить» увеличивает счётчик — эффект загружает расчёт заново
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    // ответ на устаревший запрос (StrictMode, смена id) не должен перезаписать актуальный
    let ignore = false
    getCalculation(id).then(
      (result) => !ignore && setState({ status: 'loaded', result }),
      (e: unknown) => !ignore && setState({ status: 'failed', error: toErrorDetails(e) }),
    )

    return () => {
      ignore = true
    }
  }, [id, attempt])

  function retry() {
    setState({ status: 'loading' })
    setAttempt((n) => n + 1)
  }

  if (state.status === 'loading') {
    return (
      <main>
        <p>Загружаем расчёт…</p>
      </main>
    )
  }

  if (state.status === 'failed') {
    return (
      <main>
        {state.error.kind === 'notFound' ? (
          <h1>Расчёт не найден</h1>
        ) : (
          <ErrorBanner
            message={errorMessage(state.error, 'Не удалось загрузить расчёт. Попробуйте ещё раз.')}
            onRetry={state.error.kind === 'rateLimited' ? undefined : retry}
          />
        )}
        <Link to="/">К калькулятору</Link>
      </main>
    )
  }

  const { parameters, summary, schedule } = state.result

  return (
    <main>
      <h1>Сохранённый расчёт</h1>
      {parameters && <ParametersList parameters={parameters} />}
      <SummaryCards summary={summary} />
      {parameters && (
        <button type="button" onClick={() => void navigate('/', { state: { parameters } })}>
          Открыть в калькуляторе
        </button>
      )}
      <ScheduleTable schedule={schedule} />
    </main>
  )
}
