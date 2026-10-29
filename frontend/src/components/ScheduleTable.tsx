import type { Payment } from '../api/types'
import { formatDate, formatMoney } from '../lib/format'

// До 360 строк — без виртуализации (дизайн 7.1)
export function ScheduleTable({ schedule }: { schedule: Payment[] }) {
  return (
    <table>
      <caption>График платежей</caption>
      <thead>
        <tr>
          <th scope="col">№</th>
          <th scope="col">Дата</th>
          <th scope="col">Платёж</th>
          <th scope="col">Проценты</th>
          <th scope="col">Основной долг</th>
          <th scope="col">Остаток</th>
        </tr>
      </thead>
      <tbody>
        {schedule.map((payment) => (
          <tr key={payment.number}>
            <td>{payment.number}</td>
            <td>{formatDate(payment.date)}</td>
            <td>{formatMoney(payment.payment)}</td>
            <td>{formatMoney(payment.interest)}</td>
            <td>{formatMoney(payment.principal)}</td>
            <td>{formatMoney(payment.balance)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
