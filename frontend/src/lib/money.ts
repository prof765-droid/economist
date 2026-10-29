/**
 * Сумма в копейках для точного сравнения: float ошибается даже на вычитании
 * (`1000000.07 - 500000.07 = 499999.99999999994`). Только для проверок формы —
 * в поле и в запросе остаётся исходная строка. Ждёт строку вида `\d+(\.\d{1,2})?`.
 */
export function toCents(value: string): bigint {
  const [rubles, kopecks = ''] = value.split('.')

  return BigInt(rubles + kopecks.padEnd(2, '0'))
}
