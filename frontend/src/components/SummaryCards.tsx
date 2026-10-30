import type { Summary } from '../api/types'
import { formatMoney } from '../lib/format'
import styles from './SummaryCards.module.css'

const CARDS: { key: keyof Summary; title: string }[] = [
  { key: 'monthlyPayment', title: 'Ежемесячный платёж' },
  { key: 'creditAmount', title: 'Сумма кредита' },
  { key: 'overpayment', title: 'Переплата' },
  { key: 'totalPayment', title: 'Всего выплат' },
]

export function SummaryCards({ summary }: { summary: Summary }) {
  return (
    <dl className={styles.cards}>
      {CARDS.map(({ key, title }) => (
        <div className={styles.card} key={key}>
          <dt className={styles.title}>{title}</dt>
          <dd className={styles.value}>{formatMoney(summary[key])} ₽</dd>
        </div>
      ))}
    </dl>
  )
}
