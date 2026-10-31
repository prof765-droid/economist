import { z } from 'zod'
import type { MortgageParameters } from '../api/types'
import { toCents } from '../lib/money'
import { isTermInRange, type TermUnit, toMonths } from '../lib/term'

// Правила повторяют домен (CreditTerms) и Request DTO вместе с текстами — пользователь видит
// одно сообщение, кто бы ни поймал ошибку. Источник правды — бэк, здесь — быстрая обратная связь.

const DECIMAL = /^\d+(\.\d{1,2})?$/
const MAX_PROPERTY_PRICE_CENTS = 10_000_000_000_00n
const MIN_CREDIT_AMOUNT_CENTS = 500_000_00n
const MAX_ANNUAL_RATE_CENTS = 30_00n

const REQUIRED_MESSAGE = 'Заполните поле'
const DECIMAL_MESSAGE = 'Введите число, например 5000000.00'

// годы — только целые: «20 лет 6 мес.» вводится в месяцах (дизайн 7.1); 30 лет = лимит домена 360 мес.
const TERM_LIMITS: Record<TermUnit, { max: number; message: string }> = {
  years: { max: 30, message: 'Срок должен быть от 1 до 30 лет' },
  months: { max: 360, message: 'Срок должен быть от 1 до 360 месяцев' },
}

// abort: в Zod 4 проверки поля идут дальше после ошибки — без него toCents('abc') бросит исключение
const decimal = () =>
  z
    .string()
    .min(1, { error: REQUIRED_MESSAGE, abort: true })
    .regex(DECIMAL, { error: DECIMAL_MESSAGE, abort: true })

export const mortgageFormSchema = z
  .object({
    propertyPrice: decimal().refine((value) => toCents(value) <= MAX_PROPERTY_PRICE_CENTS, {
      error: 'Стоимость недвижимости должна быть не больше 10 000 000 000 ₽',
    }),
    downPayment: decimal(),
    annualRate: decimal().refine(
      (value) => toCents(value) > 0n && toCents(value) <= MAX_ANNUAL_RATE_CENTS,
      { error: 'Ставка должна быть больше 0 % и не больше 30 %' },
    ),
    term: z.string().min(1, { error: REQUIRED_MESSAGE, abort: true }),
    termUnit: z.enum(['years', 'months'], { error: 'Выберите единицы срока' }),
    repaymentType: z.enum(['annuity', 'differentiated'], { error: 'Выберите тип погашения' }),
    startDate: z
      .string()
      .regex(/^\d{4}-\d{2}-\d{2}$/, { error: 'Введите дату в формате ГГГГ-ММ-ДД' }),
  })
  .refine(
    ({ propertyPrice, downPayment }) =>
      toCents(propertyPrice) - toCents(downPayment) >= MIN_CREDIT_AMOUNT_CENTS,
    { path: ['downPayment'], error: 'Сумма кредита должна быть не меньше 500 000 ₽' },
  )
  // на уровне объекта: диапазон срока зависит от соседнего поля termUnit. Как и проверка суммы кредита,
  // не запускается, пока в суммах ошибка формата (abort) — Zod пропускает проверки объекта при явном
  // abort в любом поле, даже с опцией when. Пустой срок ловит само поле — «Заполните поле» видно сразу.
  .superRefine(({ term, termUnit }, ctx) => {
    const { max, message } = TERM_LIMITS[termUnit]
    if (!isTermInRange(term, max)) {
      ctx.addIssue({ code: 'custom', path: ['term'], message })
    }
  })
  // в API срок — только месяцы: на выходе схемы те же MortgageParameters, что и до фичи 18
  .transform(({ term, termUnit, ...parameters }) => ({
    ...parameters,
    termMonths: toMonths(Number(term), termUnit),
  })) satisfies z.ZodType<MortgageParameters>

/** Значения полей формы (вход схемы): суммы и срок — строки, как в `<input>`; срок — число + единицы. */
export type MortgageFormValues = z.input<typeof mortgageFormSchema>

