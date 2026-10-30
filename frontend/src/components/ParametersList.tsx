import type { MortgageParameters } from '../api/types'
import { formatDate, formatMoney, formatRate, formatRepaymentType } from '../lib/format'

// Условия сохранённого расчёта: получатель ссылки не видел формы (дизайн 7.1)
export function ParametersList({ parameters }: { parameters: MortgageParameters }) {
  const items: [string, string][] = [
    ['Стоимость недвижимости', `${formatMoney(parameters.propertyPrice)} ₽`],
    ['Первоначальный взнос', `${formatMoney(parameters.downPayment)} ₽`],
    ['Ставка', `${formatRate(parameters.annualRate)} % годовых`],
    ['Срок', `${parameters.termMonths} мес.`],
    ['Тип погашения', formatRepaymentType(parameters.repaymentType)],
    ['Дата выдачи кредита', formatDate(parameters.startDate)],
  ]

  return (
    <dl aria-label="Условия кредита">
      {items.map(([title, value]) => (
        <div key={title}>
          <dt>{title}</dt>
          <dd>{value}</dd>
        </div>
      ))}
    </dl>
  )
}
