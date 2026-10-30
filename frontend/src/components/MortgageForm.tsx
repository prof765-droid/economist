import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useId } from 'react'
import { type Control, Controller, type FieldPath, useForm } from 'react-hook-form'
import type { MortgageParameters, Violation } from '../api/types'
import { applyDecimalMask } from '../lib/decimalMask'
import { formatRepaymentType } from '../lib/format'
import { type MortgageFormValues, mortgageFormSchema } from '../schema/mortgageForm'

interface Props {
  onSubmit: (parameters: MortgageParameters) => Promise<void> | void
  serverErrors: Violation[]
  // параметры сохранённого расчёта — пришли со страницы /c/:id по «Открыть в калькуляторе»
  initialParameters?: MortgageParameters
}

const FIELDS: ReadonlySet<string> = new Set<FieldPath<MortgageFormValues>>([
  'propertyPrice',
  'downPayment',
  'annualRate',
  'termMonths',
  'repaymentType',
  'startDate',
])

export function MortgageForm({ onSubmit, serverErrors, initialParameters }: Props) {
  const { control, handleSubmit, setError, formState } = useForm<MortgageFormValues, unknown, MortgageParameters>({
    resolver: zodResolver(mortgageFormSchema),
    defaultValues: initialParameters ? toFormValues(initialParameters) : emptyValues(),
  })

  useEffect(() => {
    for (const { field, message } of serverErrors) {
      // нарушение без поля формы не теряем — показываем над кнопкой
      setError(FIELDS.has(field) ? (field as FieldPath<MortgageFormValues>) : 'root.server', { message })
    }
  }, [serverErrors, setError])

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <TextField control={control} name="propertyPrice" label="Стоимость недвижимости, ₽" masked />
      <TextField control={control} name="downPayment" label="Первоначальный взнос, ₽" masked />
      <TextField control={control} name="annualRate" label="Ставка, % годовых" masked />
      <TextField control={control} name="termMonths" label="Срок, месяцев" inputMode="numeric" />

      <Controller
        control={control}
        name="repaymentType"
        render={({ field, fieldState }) => (
          <fieldset aria-invalid={fieldState.invalid}>
            <legend>Тип погашения</legend>
            <label>
              <input type="radio" {...field} value="annuity" checked={field.value === 'annuity'} />
              {formatRepaymentType('annuity')}
            </label>
            <label>
              <input type="radio" {...field} value="differentiated" checked={field.value === 'differentiated'} />
              {formatRepaymentType('differentiated')}
            </label>
            {fieldState.error && <p role="alert">{fieldState.error.message}</p>}
          </fieldset>
        )}
      />

      <TextField control={control} name="startDate" label="Дата выдачи кредита" type="date" />

      {formState.errors.root?.server && <p role="alert">{formState.errors.root.server.message}</p>}

      <button type="submit" disabled={formState.isSubmitting}>
        {formState.isSubmitting ? 'Считаем…' : 'Рассчитать'}
      </button>
    </form>
  )
}

interface TextFieldProps {
  control: Control<MortgageFormValues, unknown, MortgageParameters>
  name: Exclude<FieldPath<MortgageFormValues>, 'repaymentType'>
  label: string
  masked?: boolean
  type?: 'text' | 'date'
  inputMode?: 'decimal' | 'numeric'
}

function TextField({ control, name, label, masked = false, type = 'text', inputMode }: TextFieldProps) {
  const id = useId()
  const errorId = `${id}-error`

  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <div>
          <label htmlFor={id}>{label}</label>
          <input
            {...field}
            id={id}
            type={type}
            inputMode={masked ? 'decimal' : inputMode}
            aria-invalid={fieldState.invalid}
            aria-describedby={fieldState.error ? errorId : undefined}
            onChange={(event) => {
              const value = masked ? applyDecimalMask(event.target.value) : event.target.value
              // поле управляемое: не вызвали onChange — React вернёт прежнее значение
              if (value !== null) {
                field.onChange(value)
              }
            }}
          />
          {fieldState.error && <p id={errorId}>{fieldState.error.message}</p>}
        </div>
      )}
    />
  )
}

function emptyValues(): MortgageFormValues {
  return {
    propertyPrice: '',
    downPayment: '',
    annualRate: '',
    termMonths: '',
    repaymentType: 'annuity',
    startDate: today(),
  }
}

function toFormValues(parameters: MortgageParameters): MortgageFormValues {
  return { ...parameters, termMonths: String(parameters.termMonths) }
}

/** Сегодня по локальному времени: toISOString() дал бы дату по UTC (в Москве до 03:00 — вчера). */
function today(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${now.getFullYear()}-${month}-${day}`
}
