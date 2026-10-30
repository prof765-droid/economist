import type { ApiErrorDetails } from '../api/client'

const seconds = new Intl.PluralRules('ru')
const SECONDS: Partial<Record<Intl.LDMLPluralRule, string>> = { one: 'секунду', few: 'секунды' }

/** Текст ошибки API для пользователя; `fallback` — что не удалось сделать (5xx и прочее). */
export function errorMessage(error: ApiErrorDetails, fallback: string): string {
  switch (error.kind) {
    case 'rateLimited':
      return `Слишком много запросов. Попробуйте через ${error.retryAfter} ${SECONDS[seconds.select(error.retryAfter)] ?? 'секунд'}`
    case 'network':
      return 'Не удалось связаться с сервером. Проверьте подключение к интернету.'
    default:
      return fallback
  }
}
