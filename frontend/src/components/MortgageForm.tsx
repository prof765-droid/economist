import { zodResolver } from '@hookform/resolvers/zod'
import { type ComponentProps, type ReactNode, type RefCallback, useEffect, useId, useLayoutEffect, useRef, useState } from 'react'
import { type Control, Controller, type FieldPath, useController, useForm } from 'react-hook-form'
import type { MortgageParameters, Violation } from '../api/types'
import { applyDecimalMask, groupThousands } from '../lib/decimalMask'
import { formatRepaymentType } from '../lib/format'
import { convertTerm, fromMonths, type TermUnit } from '../lib/term'
import { type MortgageFormValues, mortgageFormSchema } from '../schema/mortgageForm'
import styles from './MortgageForm.module.css'

// отклонённый маской ввод не должен пропадать молча (дизайн 7.1)
const MASK_HINT = 'Только цифры, не больше 2 знаков после точки'

interface Props {
  onSubmit: (parameters: MortgageParameters) => Promise<void> | void
  serverErrors: Violation[]
  // параметры сохранённого расчёта — пришли со страницы /c/:id по «Открыть в калькуляторе»
  initialParameters?: MortgageParameters
  // кнопки справа от «Рассчитать» (на узком экране — под ней), например «Поделиться»
  actions?: ReactNode
}

// поле нарушения из API → поле формы; срок в форме — число и единицы, в API — termMonths (дизайн 7.1)
const FIELDS: ReadonlyMap<string, FieldPath<MortgageFormValues>> = new Map([
  ['propertyPrice', 'propertyPrice'],
  ['downPayment', 'downPayment'],
  ['annualRate', 'annualRate'],
  ['termMonths', 'term'],
  ['repaymentType', 'repaymentType'],
  ['startDate', 'startDate'],
])

export function MortgageForm({ onSubmit, serverErrors, initialParameters, actions }: Props) {
  const { control, handleSubmit, setError, formState } = useForm<MortgageFormValues, unknown, MortgageParameters>({
    resolver: zodResolver(mortgageFormSchema),
    defaultValues: initialParameters ? toFormValues(initialParameters) : emptyValues(),
  })
  const repaymentTypeId = useId()
  const formId = useId()

  useEffect(() => {
    for (const { field, message } of serverErrors) {
      // нарушение без поля формы не теряем — показываем над кнопкой
      setError(FIELDS.get(field) ?? 'root.server', { message })
    }
  }, [serverErrors, setError])

  return (
    <>
      <form id={formId} className={styles.form} onSubmit={handleSubmit(onSubmit)} noValidate>
        <TextField control={control} name="propertyPrice" label="Стоимость недвижимости, ₽" masked />
        <TextField control={control} name="downPayment" label="Первоначальный взнос, ₽" masked />
        <TextField control={control} name="annualRate" label="Ставка, % годовых" masked />
        <TermField control={control} />

        <Controller
          control={control}
          name="repaymentType"
          render={({ field, fieldState }) => (
            <div className={styles.field}>
              <label className={styles.label} htmlFor={repaymentTypeId}>
                Тип погашения
              </label>
              <select
                {...field}
                id={repaymentTypeId}
                aria-invalid={fieldState.invalid}
                aria-describedby={fieldState.error ? `${repaymentTypeId}-error` : undefined}
              >
                <option value="annuity">{formatRepaymentType('annuity')}</option>
                <option value="differentiated">{formatRepaymentType('differentiated')}</option>
              </select>
              {fieldState.error && (
                <p className={styles.error} id={`${repaymentTypeId}-error`} role="alert">
                  {fieldState.error.message}
                </p>
              )}
            </div>
          )}
        />

        <TextField control={control} name="startDate" label="Дата выдачи кредита" type="date" />

        {formState.errors.root?.server && (
          <p className={styles.error} role="alert">
            {formState.errors.root.server.message}
          </p>
        )}
      </form>

      <hr />

      {/* строка кнопок вне <form>: Enter в поле со ссылкой из «Поделиться» не должен отправлять расчёт;
          «Рассчитать» привязана к форме атрибутом form */}
      <div className={styles.actions}>
        <button className={styles.submit} type="submit" form={formId} disabled={formState.isSubmitting}>
          {formState.isSubmitting ? 'Считаем…' : 'Рассчитать'}
        </button>
        {actions}
      </div>
    </>
  )
}

interface TextFieldProps {
  control: Control<MortgageFormValues, unknown, MortgageParameters>
  name: Exclude<FieldPath<MortgageFormValues>, 'repaymentType' | 'term' | 'termUnit'>
  label: string
  masked?: boolean
  type?: 'text' | 'date'
}

function TextField({ control, name, label, masked = false, type = 'text' }: TextFieldProps) {
  const id = useId()
  const errorId = `${id}-error`
  const [rejected, setRejected] = useState(false)

  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <div className={styles.field}>
          <label className={styles.label} htmlFor={id}>
            {label}
          </label>
          {masked ? (
            <DecimalInput
              id={id}
              name={field.name}
              value={field.value}
              fieldRef={field.ref}
              aria-invalid={fieldState.invalid}
              aria-describedby={fieldState.error ? errorId : undefined}
              onValueChange={(value) => {
                setRejected(value === null)
                // поле управляемое: не вызвали onChange — React вернёт прежнее значение
                if (value !== null) {
                  field.onChange(value)
                }
              }}
              onBlur={() => {
                setRejected(false)
                field.onBlur()
              }}
            />
          ) : (
            <input
              {...field}
              id={id}
              type={type}
              aria-invalid={fieldState.invalid}
              aria-describedby={fieldState.error ? errorId : undefined}
            />
          )}
          {masked && (
            <p className={styles.maskHint} aria-live="polite">
              {rejected ? MASK_HINT : ''}
            </p>
          )}
          {fieldState.error && (
            <p className={styles.error} id={errorId}>
              {fieldState.error.message}
            </p>
          )}
        </div>
      )}
    />
  )
}

const TERM_UNITS: Record<TermUnit, string> = { years: 'лет', months: 'месяцев' }

/** Срок: число и единицы рядом; смена единиц пересчитывает введённое число (дизайн 7.1). */
function TermField({ control }: { control: TextFieldProps['control'] }) {
  const id = useId()
  const errorId = `${id}-error`
  const term = useController({ control, name: 'term' })
  const unit = useController({ control, name: 'termUnit' })
  // ошибка единиц возможна, только если в select подставили чужое значение, — показываем под тем же полем
  const error = term.fieldState.error ?? unit.fieldState.error

  return (
    <div className={styles.field}>
      <label className={styles.label} htmlFor={id}>
        Срок
      </label>
      <div className={styles.term}>
        <input
          {...term.field}
          id={id}
          type="text"
          inputMode="numeric"
          aria-invalid={term.fieldState.invalid}
          aria-describedby={error ? errorId : undefined}
        />
        <select
          {...unit.field}
          aria-label="Единицы срока"
          aria-invalid={unit.fieldState.invalid}
          onChange={(event) => {
            const next = event.target.value as TermUnit
            // без пересчёта 20 лет молча стали бы 20 месяцами
            term.field.onChange(convertTerm(term.field.value, unit.field.value, next))
            unit.field.onChange(next)
          }}
        >
          {Object.entries(TERM_UNITS).map(([value, title]) => (
            <option key={value} value={value}>
              {title}
            </option>
          ))}
        </select>
      </div>
      {error && (
        <p className={styles.error} id={errorId}>
          {error.message}
        </p>
      )}
    </div>
  )
}

function emptyValues(): MortgageFormValues {
  return {
    propertyPrice: '',
    downPayment: '',
    annualRate: '',
    term: '',
    // ипотеку берут «на 20 лет», а не «на 240 месяцев» (дизайн 7.1)
    termUnit: 'years',
    repaymentType: 'annuity',
    startDate: today(),
  }
}

function toFormValues({ termMonths, ...parameters }: MortgageParameters): MortgageFormValues {
  // кратно 12 — в годах, иначе в месяцах: 245 мес. в годах не записать целым числом
  const { term, unit } = fromMonths(termMonths)

  return { ...parameters, term: String(term), termUnit: unit }
}

/** Сегодня по локальному времени: toISOString() дал бы дату по UTC (в Москве до 03:00 — вчера). */
function today(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${now.getFullYear()}-${month}-${day}`
}

interface DecimalInputProps extends Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'ref'> {
  value: string
  fieldRef: RefCallback<HTMLInputElement>
  // null — ввод отклонён маской
  onValueChange: (value: string | null) => void
}

/** Поле суммы: показывает разряды через узкий пробел, в форму отдаёт значение без пробелов. */
function DecimalInput({ value, fieldRef, onValueChange, ...props }: DecimalInputProps) {
  const input = useRef<HTMLInputElement | null>(null)
  // сколько цифр и точек было слева от курсора до расстановки пробелов
  const caret = useRef<number | null>(null)

  // пробелы меняют длину строки — без этого курсор при правке в середине прыгал бы в конец
  useLayoutEffect(() => {
    if (caret.current !== null && input.current !== null) {
      const position = caretPosition(input.current.value, caret.current)
      input.current.setSelectionRange(position, position)
      caret.current = null
    }
  })

  return (
    <input
      {...props}
      ref={(element) => {
        input.current = element
        fieldRef(element)
      }}
      type="text"
      inputMode="decimal"
      value={groupThousands(value)}
      onChange={(event) => {
        const { value: raw, selectionStart } = event.target
        caret.current = raw.slice(0, selectionStart ?? raw.length).replace(/\s/g, '').length
        onValueChange(applyDecimalMask(raw))
      }}
    />
  )
}

/** Позиция в отформатированной строке, слева от которой ровно `significant` символов-не-пробелов. */
function caretPosition(formatted: string, significant: number): number {
  let seen = 0
  for (let i = 0; i < formatted.length; i++) {
    if (seen === significant) {
      return i
    }
    if (!/\s/.test(formatted[i])) {
      seen++
    }
  }

  return formatted.length
}
