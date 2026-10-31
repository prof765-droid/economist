import type { MortgageParameters } from '../api/types'
import { formatDate, formatMoney, formatRate, formatRepaymentType } from '../lib/format'
import { formatTerm } from '../lib/term'
import styles from './ParametersList.module.css'

// Условия сохранённого расчёта: получатель ссылки не видел формы (дизайн 7.1)
export function ParametersList({ parameters }: { parameters: MortgageParameters }) {
  const items: [string, string][] = [
    ['Стоимость недвижимости', `${formatMoney(parameters.propertyPrice)} ₽`],
    ['Первоначальный взнос', `${formatMoney(parameters.downPayment)} ₽`],
    ['Ставка', `${formatRate(parameters.annualRate)} % годовых`],
    ['Срок', formatTerm(parameters.termMonths)],
    ['Тип погашения', formatRepaymentType(parameters.repaymentType)],
    ['Дата выдачи кредита', formatDate(parameters.startDate)],
  ]

  return (
    <dl className={styles.list} aria-label="Условия кредита">
      {items.map(([title, value]) => (
        <div className={styles.item} key={title}>
          <dt className={styles.title}>{title}</dt>
          <dd className={styles.value}>{value}</dd>
        </div>
      ))}
    </dl>
  )
}
