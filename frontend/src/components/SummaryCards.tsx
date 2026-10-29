import type { Summary } from '../api/types'
import { formatMoney } from '../lib/format'

const CARDS: { key: keyof Summary; title: string }[] = [
  { key: 'monthlyPayment', title: 'Ежемесячный платёж' },
  { key: 'creditAmount', title: 'Сумма кредита' },
  { key: 'overpayment', title: 'Переплата' },
  { key: 'totalPayment', title: 'Всего выплат' },
]

export function SummaryCards({ summary }: { summary: Summary }) {
  return (
    <dl>
      {CARDS.map(({ key, title }) => (
        <div key={key}>
          <dt>{title}</dt>
          <dd>{formatMoney(summary[key])} ₽</dd>
        </div>
      ))}
    </dl>
  )
}
