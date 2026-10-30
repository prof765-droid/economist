import styles from './ErrorBanner.module.css'

interface Props {
  message: string
  // без onRetry кнопки нет: например, при 429 повтор сразу снова получит 429
  onRetry?: () => void
}

export function ErrorBanner({ message, onRetry }: Props) {
  return (
    <div className={styles.banner} role="alert">
      <p>{message}</p>
      {onRetry && (
        <button className={styles.retry} type="button" onClick={onRetry}>
          Повторить
        </button>
      )}
    </div>
  )
}
