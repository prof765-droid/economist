// Не даёт ввести больше 2 знаков после точки: домен их округлил бы (дизайн 1, «Деньги в трёх слоях»).
// Незаконченный ввод («12.», «») пропускается — его ловит схема формы.
const ALLOWED = /^\d*(\.\d{0,2})?$/

/** Возвращает новое значение поля или `null`, если ввод отклонён (поле не меняется). */
export function applyDecimalMask(input: string): string | null {
  // пробелы-разделители разрядов («5 000 000», в т. ч. неразрывные) — чтобы работала вставка суммы
  const value = input.replace(/\s/g, '').replace(',', '.')

  return ALLOWED.test(value) ? value : null
}

/** Только для показа в поле: «5000000.5» → «5 000 000.5» (узкий неразрывный пробел U+202F). */
export function groupThousands(value: string): string {
  const [integer, fraction] = value.split('.')
  const grouped = integer.replace(/\B(?=(\d{3})+$)/g, '\u202f')

  return fraction === undefined ? grouped : `${grouped}.${fraction}`
}
