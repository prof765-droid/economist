import type {
  CalculationResult,
  MortgageParameters,
  ProblemDetails,
  SaveResponse,
  Violation,
} from './types'

export type ApiErrorDetails =
  | { kind: 'validation'; violations: Violation[] }
  | { kind: 'notFound' }
  | { kind: 'rateLimited'; retryAfter: number }
  | { kind: 'server'; status: number }
  | { kind: 'network' }

export class ApiError extends Error {
  readonly details: ApiErrorDetails

  constructor(details: ApiErrorDetails) {
    super(`API error: ${details.kind}`)
    this.name = 'ApiError'
    this.details = details
  }
}

/** Детали ошибки из `catch`: не `ApiError` — значит, упал наш код, для пользователя это как `5xx`. */
export function toErrorDetails(error: unknown): ApiErrorDetails {
  return error instanceof ApiError ? error.details : { kind: 'server', status: 0 }
}

export function calculate(parameters: MortgageParameters): Promise<CalculationResult> {
  return request('/api/mortgage/calculate', postJson(parameters))
}

export function saveCalculation(parameters: MortgageParameters): Promise<SaveResponse> {
  return request('/api/calculations', postJson(parameters))
}

export function getCalculation(id: string): Promise<CalculationResult> {
  return request(`/api/calculations/${encodeURIComponent(id)}`)
}

function postJson(body: unknown): RequestInit {
  return {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  let response: Response
  try {
    response = await fetch(path, init)
  } catch {
    // fetch отклоняется только когда ответа нет вовсе: сеть, CORS, обрыв
    throw new ApiError({ kind: 'network' })
  }

  if (response.ok) {
    return (await response.json()) as T
  }

  throw await toApiError(response)
}

async function toApiError(response: Response): Promise<ApiError> {
  switch (response.status) {
    case 422: {
      const problem = (await response.json().catch(() => null)) as ProblemDetails | null
      if (problem?.violations) {
        return new ApiError({ kind: 'validation', violations: problem.violations })
      }
      break
    }
    case 404:
      return new ApiError({ kind: 'notFound' })
    case 429: {
      // бэк всегда шлёт секунды ≥ 1 (дизайн 5); без заголовка — повторить через секунду
      const retryAfter = Number(response.headers.get('Retry-After'))
      return new ApiError({ kind: 'rateLimited', retryAfter: retryAfter > 0 ? retryAfter : 1 })
    }
  }

  return new ApiError({ kind: 'server', status: response.status })
}
