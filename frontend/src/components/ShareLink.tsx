import { useState } from 'react'
import { saveCalculation, toErrorDetails } from '../api/client'
import type { MortgageParameters } from '../api/types'
import { errorMessage } from '../lib/errorMessage'
import styles from './ShareLink.module.css'

interface Props {
  // параметры последнего выполненного расчёта, а не текущее состояние формы (дизайн 7.1)
  parameters: MortgageParameters
  // id уже сохранённого расчёта: повторное нажатие не создаёт дубликат
  sharedId: string | null
  onShared: (id: string) => void
}

const COPIED = 'Ссылка скопирована'
const COPY_MANUALLY = 'Скопируйте ссылку вручную'

export function ShareLink({ parameters, sharedId, onShared }: Props) {
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState<string | null>(null)

  async function share() {
    setMessage(null)
    let id = sharedId

    if (id === null) {
      setSaving(true)
      try {
        id = (await saveCalculation(parameters)).id
        onShared(id)
      } catch (e) {
        setMessage(errorMessage(toErrorDetails(e), 'Не удалось сохранить расчёт. Попробуйте ещё раз.'))
        return
      } finally {
        setSaving(false)
      }
    }

    setMessage(await shareOrCopy(shareUrl(id)))
  }

  async function copy(url: string) {
    setMessage(await copyToClipboard(url))
  }

  // фрагмент: элементы встают в строку кнопок формы (MortgageForm, actions) —
  // кнопка рядом с «Рассчитать», линия и ссылка — своими строками ниже
  return (
    <>
      <button className={styles.shareButton} type="button" onClick={() => void share()} disabled={saving}>
        {saving ? 'Сохраняем…' : 'Поделиться'}
      </button>

      {/* ссылка видна всегда: запасной путь, если браузер не дал скопировать (дизайн 7.1) */}
      {sharedId && (
        <>
          <hr className={styles.row} />
          <div className={`${styles.row} ${styles.link}`}>
            <input
              className={styles.url}
              readOnly
              value={shareUrl(sharedId)}
              aria-label="Ссылка на расчёт"
              onFocus={(event) => event.target.select()}
            />
            <button type="button" onClick={() => void copy(shareUrl(sharedId))}>
              Скопировать
            </button>
          </div>
        </>
      )}

      {message && (
        <p className={`${styles.row} ${styles.message}`} role="status">
          {message}
        </p>
      )}
    </>
  )
}

function shareUrl(id: string): string {
  return `${location.origin}/c/${id}`
}

/** Web Share — только на сенсорных устройствах: на десктопе пользователь ждёт копирования (дизайн 7.1). */
async function shareOrCopy(url: string): Promise<string | null> {
  if (typeof navigator.share !== 'function' || !matchMedia('(pointer: coarse)').matches) {
    return copyToClipboard(url)
  }

  try {
    await navigator.share({ url })
    return null
  } catch (e) {
    // пользователь закрыл меню «Поделиться» — это не ошибка
    return e instanceof DOMException && e.name === 'AbortError' ? null : COPY_MANUALLY
  }
}

async function copyToClipboard(url: string): Promise<string> {
  try {
    // вне https/localhost navigator.clipboard нет — TypeError попадёт в тот же catch
    await navigator.clipboard.writeText(url)
    return COPIED
  } catch {
    // Safari: после запроса к API клик уже «израсходован» — NotAllowedError
    return COPY_MANUALLY
  }
}
